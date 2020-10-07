<?php

namespace xenialdan\MagicWE2\task;

use Exception;
use Generator;
use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use pocketmine\uuid\UUID;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use xenialdan\MagicWE2\clipboard\RevertClipboard;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\helper\BlockEntry;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\selection\shape\Shape;
use xenialdan\MagicWE2\session\UserSession;

class AsyncPasteTask extends MWEAsyncTask
{
	/** @var string */
	private $touchedChunks;
	/** @var string */
	private $selection;
	/** @var int */
	private $flags;
	/** @var string */
	private $clipboard;
	/** @var Vector3 */
	private $offset;

	/**
	 * AsyncPasteTask constructor.
	 * @param UUID $sessionUUID
	 * @param Selection $selection
	 * @param string[] $touchedChunks serialized chunks
	 * @param SingleClipboard $clipboard
	 * @param int $flags
	 * @throws Exception
	 */
	public function __construct(UUID $sessionUUID, Selection $selection, array $touchedChunks, SingleClipboard $clipboard, int $flags)
    {
		$this->start = microtime(true);
		$this->offset = $selection->getShape()->getPasteVector()->addVector($clipboard->position)->floor();
		#var_dump("paste", $selection->getShape()->getPasteVector(), "cb position", $clipboard->position, "offset", $this->offset, $clipboard);
		$this->sessionUUID = $sessionUUID->toString();
		$this->selection = serialize($selection);
		$this->touchedChunks = serialize($touchedChunks);
		$this->clipboard = serialize($clipboard);
		$this->flags = $flags;
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

		$touchedChunks = array_map(function ($chunk) {
			return FastChunkSerializer::deserialize($chunk);
		}, unserialize($this->touchedChunks, ['allowed_classes' => false]));//TODO test pm4
		foreach ($touchedChunks as $chunk) {
			/** @var Chunk $chunk */
			var_dump("deserialize Chunk x " . $chunk->getX() . " z " . $chunk->getZ());
		}//TODO REMOVE

		$manager = Shape::getChunkManager($touchedChunks);
		unset($touchedChunks);

		/** @var Selection $selection */
		$selection = unserialize($this->selection, ['allowed_classes' => [Selection::class]]);//TODO test pm4

		/** @var SingleClipboard $clipboard */
		$clipboard = unserialize($this->clipboard, ['allowed_classes' => [SingleClipboard::class]]);//TODO test pm4
		$oldBlocks = iterator_to_array($this->execute($selection, $manager, $clipboard, $changed));

		$resultChunks = $manager->getChunks();
		$resultChunks = array_filter($resultChunks, function (Chunk $chunk) {
			return $chunk->isDirty();
		});
		$this->setResult(compact("resultChunks", "oldBlocks", "changed"));
	}

	/**
	 * @param Selection $selection
	 * @param AsyncChunkManager $manager
	 * @param SingleClipboard $clipboard
	 * @param null|int $changed
	 * @return Generator|Block[]
	 * @throws InvalidArgumentException
	 */
	private function execute(Selection $selection, AsyncChunkManager $manager, SingleClipboard $clipboard, ?int &$changed): Generator
	{
		$blockCount = $clipboard->getTotalCount();
		$lastchunkx = $lastchunkz = $x = $y = $z = null;
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
			if (is_null($lastchunkx) || ($x >> 4 !== $lastchunkx && $z >> 4 !== $lastchunkz)) {
				$lastchunkx = $x >> 4;
				$lastchunkz = $z >> 4;
				if (is_null($manager->getChunk($x >> 4, $z >> 4))) {
					print PHP_EOL . "Paste chunk not found in async paste manager: " . strval($x >> 4) . ":" . strval($z >> 4) . PHP_EOL;
					continue;
				}
			}
			/*if (API::hasFlag($this->flags, API::FLAG_POSITION_RELATIVE)){
				$rel = $block->subtract($selection->shape->getPasteVector());
				$block->setComponents($rel->x,$rel->y,$rel->z);//TODO COPY TO ALL TASKS
			}*/
			$new = $entry->toBlock();
			#$new->position(($pos = Position::fromObject(new Vector3($x, $y, $z)))->getWorld(), $pos->getX(), $pos->getY(), $pos->getZ());
			$old = $manager->getBlockAt($x, $y, $z);
			#$old->position(($pos = Position::fromObject(new Vector3($x, $y, $z)))->getWorld(), $pos->getX(), $pos->getY(), $pos->getZ());
			#var_dump("old", $old, "new", $new);
			yield $old;
			$manager->setBlockAt($x, $y, $z, $new);
			if ($manager->getBlockArrayAt($x, $y, $z) !== [$old->getId(), $old->getMeta()]) {//TODO remove? Just useless waste imo
				$changed++;
			}
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
	 * @throws InvalidArgumentException
	 * @throws Exception
	 * @throws Exception
	 */
	public function onCompletion(): void
	{
		try {
			$session = SessionHelper::getSessionByUUID(UUID::fromString($this->sessionUUID));
			if ($session instanceof UserSession) $session->getBossBar()->hideFromAll();
		} catch (SessionException $e) {
			Loader::getInstance()->getLogger()->logException($e);
			$session = null;
		}
		$result = $this->getResult();
		/** @var Chunk[] $resultChunks */
		$resultChunks = $result["resultChunks"];
		$undoChunks = array_map(function ($chunk) {
			return FastChunkSerializer::deserialize($chunk);
		}, unserialize($this->touchedChunks, ['allowed_classes' => false]));//TODO test pm4
		$oldBlocks = $result["oldBlocks"];
		$changed = $result["changed"];
		/** @var Selection $selection */
		$selection = unserialize($this->selection, ['allowed_classes' => [Selection::class]]);//TODO test pm4
		$totalCount = $selection->getShape()->getTotalCount();
		$world = $selection->getWorld();
		foreach ($resultChunks as $hash => $chunk) {
			$world->setChunk($chunk->getX(), $chunk->getZ(), $chunk, false);
		}
		if (!is_null($session)) {
			$session->sendMessage(TF::GREEN . $session->getLanguage()->translateString('task.fill.success', [$this->generateTookString(), $changed, $totalCount]));
			$session->addRevert(new RevertClipboard($selection->worldId, $undoChunks, $oldBlocks));
		}
	}
}