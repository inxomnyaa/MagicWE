<?php

namespace xenialdan\MagicWE2\task;

use Exception;
use Generator;
use InvalidArgumentException;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\math\Vector3;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\format\BiomeArray;
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
use xenialdan\MagicWE2\helper\AsyncWorld;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\session\UserSession;
use function igbinary_serialize;
use function igbinary_unserialize;
use function var_dump;

class AsyncPasteTask extends MWEAsyncTask
{
	private string $clipboard;
	private Vector3 $offset;
	private AsyncWorld $manager;

	/**
	 * AsyncPasteTask constructor.
	 * @param UuidInterface $sessionUUID
	 * @param SingleClipboard $clipboard
	 * @throws Exception
	 */
	public function __construct(UuidInterface $sessionUUID, SingleClipboard $clipboard, Vector3 $pasteVector){
		$this->start = microtime(true);
		/*
		 backup
		$this->offset = $pasteVector->addVector($clipboard->position);
		var_dump("paste", $clipboard->selection->getShape()->getPasteVector(), "cb position", $clipboard->position, "offset", $this->offset);
		$clipboard->selection->getShape()->setPasteVector($clipboard->selection->getShape()->getPasteVector()->addVector($clipboard->position));
		var_dump("new paste", $clipboard->selection->getShape()->getPasteVector());
		 */
		$this->offset = $pasteVector->addVector($clipboard->position);
		var_dump("paste", $clipboard->selection->getShape()->getPasteVector(), "cb position", $clipboard->position, "offset", $this->offset, "aabb", $clipboard->selection->getShape()->getAABB());
		var_dump("new paste vec", $pasteVector);
		var_dump("orig paste", $clipboard->selection->getShape()->getPasteVector());
		var_dump("pasteV minus offset", $clipboard->selection->getShape()->getPasteVector()->subtractVector($this->offset));
		var_dump("old minus new pos", $clipboard->selection->getShape()->getPasteVector()->subtractVector($pasteVector));
		$clipboard->selection->getShape()->setPasteVector($this->offset->add($clipboard->selection->getSizeX() / 2, $clipboard->selection->getSizeY() / 2, $clipboard->selection->getSizeZ() / 2));
		var_dump("new paste", $clipboard->selection->getShape()->getPasteVector());
		$this->sessionUUID = $sessionUUID->toString();
		$this->clipboard = igbinary_serialize($clipboard);
		$this->manager = $clipboard->selection->getIterator()->getManager();
		var_dump(__METHOD__ . __LINE__, count($this->manager->getChunks()));
		foreach($this->manager->getChunks() as $hash => $chunk){
			World::getXZ($hash, $x, $z);
			var_dump(__METHOD__ . __LINE__, $hash, $x, $z);
		}
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

		$manager = $this->manager;
		$oldBlocks = iterator_to_array($this->execute($clipboard, $changed, $manager));

		$resultChunks = $manager->getChunks();
//		$resultChunks = array_filter($resultChunks, static function (Chunk $chunk) {
//			return $chunk->isTerrainDirty();
//		});
		$this->setResult(compact("resultChunks", "oldBlocks", "changed"));
	}

	/**
	 * @param SingleClipboard $clipboard
	 * @param null|int $changed
	 * @return Generator
	 * @throws InvalidArgumentException
	 * @phpstan-return Generator<int, array{int, Position|null}, void, void>
	 */
	private function execute(SingleClipboard $clipboard, ?int &$changed, AsyncWorld &$manager) : Generator{
		var_dump("MANAGER CHUNK COUNT", count($manager->getChunks()));
		$blockCount = $clipboard->getTotalCount();
		$x = $y = $z = null;
		$lastprogress = 0;
		$i = 0;
		$changed = 0;
		$this->publishProgress([0, "Running, changed $changed blocks out of $blockCount"]);
		/** @var BlockEntry $entry */
		foreach($clipboard->iterateEntries($x, $y, $z) as $entry){
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
			yield self::singleBlockToData(API::setComponents($manager->getBlockAt($x, $y, $z), (int) $x, (int) $y, (int) $z));

			var_dump(__METHOD__ . __LINE__, World::chunkHash($x, $z), $x, $z);
			if($manager->getChunk($x >> 4, $z >> 4) === null){
				$manager->setChunk($x >> 4, $z >> 4, new Chunk([], BiomeArray::fill(BiomeIds::OCEAN), false));
			}
//			var_dump("ToPlace", $entry->toBlock());
//			var_dump("Before setBlockAt", API::setComponents($manager->getBlockAt($x, $y, $z), (int)$x, (int)$y, (int)$z));
			$manager->setBlockAt($x, $y, $z, $new);
//			var_dump("After setBlockAt", API::setComponents($manager->getBlockAt($x, $y, $z), (int)$x, (int)$y, (int)$z));
			$changed++;
			///
			$i++;
			$progress = floor($i / $blockCount * 100);
			if($lastprogress < $progress){//this prevents spamming packets
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
		$undoChunks = $selection->getIterator()->getManager()->getChunks();//?
		$totalCount = $selection->getShape()->getTotalCount();
		$world = $selection->getWorld();
		foreach($resultChunks as $hash => $chunk){
			World::getXZ($hash, $x, $z);
			$world->setChunk($x, $z, $chunk);
			var_dump("setChunk", $x, $z, $hash);
		}
		if(!is_null($session)){
			$session->sendMessage(TF::GREEN . $session->getLanguage()->translateString('task.paste.success', [$this->generateTookString(), $changed, $totalCount]));
			$session->addRevert(new RevertClipboard($selection->worldId, $undoChunks, $oldBlocks));
		}
	}
}