<?php

namespace xenialdan\MagicWE2\task;

use Exception;
use Generator;
use InvalidArgumentException;
use pocketmine\math\Vector3;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use xenialdan\libblockstate\BlockEntry;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\clipboard\RevertClipboard;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\session\UserSession;
use function igbinary_serialize;
use function igbinary_unserialize;

class AsyncPasteTask extends MWEAsyncTask
{
	private string $clipboard;
	private Vector3 $offset;

	/**
	 * AsyncPasteTask constructor.
	 * @param UuidInterface $sessionUUID
	 * @param SingleClipboard $clipboard
	 * @throws Exception
	 */
	public function __construct(UuidInterface $sessionUUID, SingleClipboard $clipboard)
	{
		$this->start = microtime(true);
		$this->offset = $clipboard->selection->getShape()->getPasteVector()->addVector($clipboard->position)->floor();
		#var_dump("paste", $selection->getShape()->getPasteVector(), "cb position", $clipboard->position, "offset", $this->offset, $clipboard);
		$this->sessionUUID = $sessionUUID->toString();
		$this->clipboard = igbinary_serialize($clipboard);
	}

	/**
	 * Actions to execute when run
	 *
	 * @return void
	 * @throws InvalidArgumentException
	 */
	public function onRun(): void
	{
		$this->publishProgress([0, "Start"]);

//		$touchedChunks = array_map(static function ($chunk) {//todo add hash as key
//			return FastChunkSerializer::deserializeTerrain($chunk);
//		}, igbinary_unserialize($this->touchedChunks/*, ['allowed_classes' => false]*/));//TODO test pm4
//		unset($touchedChunks);

		/** @var SingleClipboard $clipboard */
		$clipboard = igbinary_unserialize($this->clipboard/*, ['allowed_classes' => [SingleClipboard::class]]*/);//TODO test pm4

		$oldBlocks = iterator_to_array($this->execute($clipboard, $changed));

		$manager = $clipboard->selection->getIterator()->getManager();
		$resultChunks = $manager->getChunks();
		$resultChunks = array_filter($resultChunks, static function (Chunk $chunk) {
			return $chunk->isTerrainDirty();
		});
		$this->setResult(compact("resultChunks", "oldBlocks", "changed"));
	}

	/**
	 * @param SingleClipboard $clipboard
	 * @param null|int $changed
	 * @return Generator
	 * @throws InvalidArgumentException
	 * @phpstan-return Generator<int, array{int, Position|null}, void, void>
	 */
	private function execute(SingleClipboard $clipboard, ?int &$changed): Generator
	{
		$manager = $clipboard->selection->getIterator()->getManager();
		$blockCount = $clipboard->getTotalCount();
		$x = $y = $z = null;
		$lastprogress = 0;
		$i = 0;
		$changed = 0;
		$this->publishProgress([0, "Running, changed $changed blocks out of $blockCount"]);
		/** @var BlockEntry $entry */
		foreach ($clipboard->iterateEntries($x, $y, $z) as $entry) {
			#var_dump("at cb xyz $x $y $z: $entry");
			$x += $this->offset->getFloorX();
			$y += $this->offset->getFloorY();
			$z += $this->offset->getFloorZ();
			#var_dump("add offset xyz $x $y $z");
			/*if (API::hasFlag($this->flags, API::FLAG_POSITION_RELATIVE)){
				$rel = $block->subtract($selection->shape->getPasteVector());
				$block = API::setComponents($block,$rel->x,$rel->y,$rel->z);//TODO COPY TO ALL TASKS
			}*/
			$new = $entry->toBlock();
			#$new->position(($pos = Position::fromObject(new Vector3($x, $y, $z)))->getWorld(), $pos->getX(), $pos->getY(), $pos->getZ());
			#$old->position(($pos = Position::fromObject(new Vector3($x, $y, $z)))->getWorld(), $pos->getX(), $pos->getY(), $pos->getZ());
			#var_dump("old", $old, "new", $new);
			yield self::singleBlockToData(API::setComponents($manager->getBlockAt($x, $y, $z), (int)$x, (int)$y, (int)$z));
			$manager->setBlockAt($x, $y, $z, $new);
			$changed++;
			///
			$i++;
			$progress = floor($i / $blockCount * 100);
			if ($lastprogress < $progress) {//this prevents spamming packets
				$this->publishProgress([$progress, "Running, changed $changed blocks out of $blockCount"]);
				$lastprogress = $progress;
			}
		}
	}

	/**
	 * @throws AssumptionFailedError
	 */
	public function onCompletion(): void
	{
		try {
			$session = SessionHelper::getSessionByUUID(Uuid::fromString($this->sessionUUID));
			if ($session instanceof UserSession) $session->getBossBar()->hideFromAll();
		} catch (SessionException $e) {
			Loader::getInstance()->getLogger()->logException($e);
			$session = null;
		}
		$result = $this->getResult();
		/** @var Chunk[] $resultChunks */
		$resultChunks = $result["resultChunks"];
//		$undoChunks = array_map(static function ($chunk) {
//			return FastChunkSerializer::deserializeTerrain($chunk);
//		}, igbinary_unserialize($this->touchedChunks/*, ['allowed_classes' => false]*/));//TODO test pm4
		$oldBlocks = $result["oldBlocks"];//already data array
		$changed = $result["changed"];
		/** @var SingleClipboard $clipboard */
		$clipboard = igbinary_unserialize($this->clipboard/*, ['allowed_classes' => [Selection::class]]*/);//TODO test pm4
		$selection = $clipboard->selection;
		$undoChunks = $selection->getIterator()->getManager()->getChunks();
		$totalCount = $selection->getShape()->getTotalCount();
		$world = $selection->getWorld();
		foreach ($resultChunks as $hash => $chunk) {
			World::getXZ($hash, $x, $z);
			$world->setChunk($x, $z, $chunk);
		}
		if (!is_null($session)) {
			$session->sendMessage(TF::GREEN . $session->getLanguage()->translateString('task.paste.success', [$this->generateTookString(), $changed, $totalCount]));
			$session->addRevert(new RevertClipboard($selection->worldId, $undoChunks, $oldBlocks));
		}
	}
}