<?php

namespace xenialdan\MagicWE2\task;

use Exception;
use Generator;
use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use pocketmine\uuid\UUID;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\World;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\clipboard\RevertClipboard;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\helper\BlockPalette;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\selection\shape\Shape;
use xenialdan\MagicWE2\session\UserSession;

class AsyncFillTask extends MWEAsyncTask
{
	/** @var string */
	private $touchedChunks;
	/** @var string */
	private $selection;
	/** @var int */
	private $flags;
	/** @var string */
	private $newBlocks;

	/**
	 * AsyncFillTask constructor.
	 * @param UUID $sessionUUID
	 * @param Selection $selection
	 * @param string[] $touchedChunks serialized chunks
	 * @param Block[] $newBlocks
	 * @param int $flags
	 * @throws Exception
	 */
	public function __construct(UUID $sessionUUID, Selection $selection, array $touchedChunks, array $newBlocks, int $flags)
	{
		$this->start = microtime(true);
		$this->sessionUUID = $sessionUUID->toString();
		$this->selection = igbinary_serialize($selection);
		$this->touchedChunks = igbinary_serialize($touchedChunks);
		$this->newBlocks = BlockPalette::encode($newBlocks);
		var_dump($this->newBlocks);
		$this->flags = $flags;
	}

	/**
	 * Actions to execute when run
	 *
	 * @return void
	 * @throws Exception
	 */
	public function onRun(): void
	{
		$this->publishProgress([0, "Start"]);

		$touchedChunks = array_map(static function ($chunk) {
			return FastChunkSerializer::deserialize($chunk);
		}, igbinary_unserialize($this->touchedChunks/*, ['allowed_classes' => false]*/));//TODO test pm4

		$manager = Shape::getChunkManager($touchedChunks);
		unset($touchedChunks);

		/** @var Selection $selection */
		$selection = igbinary_unserialize($this->selection/*, ['allowed_classes' => [Selection::class]]*/);//TODO test pm4

		/** @var Block[] $newBlocks */
		$newBlocks = BlockPalette::decode($this->newBlocks);//TODO test pm4
		$oldBlocks = iterator_to_array($this->execute($selection, $manager, $newBlocks, $changed));

		$resultChunks = $manager->getChunks();
		$resultChunks = array_filter($resultChunks, static function (Chunk $chunk) {
			return $chunk->isDirty();
		});
		#$this->setResult(compact("resultChunks", "oldBlocks", "changed"));
		$this->setResult([
			"resultChunks" => $resultChunks,
			"oldBlocks" => $oldBlocks,
			"changed" => $changed
		]);
	}

	/**
	 * @param Selection $selection
	 * @param AsyncChunkManager $manager
	 * @param Block[] $newBlocks
	 * @param null|int $changed
	 * @return Generator|array[]
	 * @phpstan-return Generator<int, array{int, \pocketmine\world\Position|null}, void, void>
	 * @throws Exception
	 */
	private function execute(Selection $selection, AsyncChunkManager $manager, array $newBlocks, ?int &$changed): Generator
	{
		$blockCount = $selection->getShape()->getTotalCount();
		$lastchunkx = $lastchunkz = null;
		$lastprogress = 0;
		$i = 0;
		$changed = 0;
		$this->publishProgress([0, "Running, changed $changed blocks out of $blockCount"]);
		/** @var Block $block */
		foreach ($selection->getShape()->getBlocks($manager, [], $this->flags) as $block) {
			/*if (API::hasFlag($this->flags, API::FLAG_POSITION_RELATIVE)){
				$rel = $block->subtract($selection->shape->getPasteVector());
				$block = API::setComponents($block,$rel->x,$rel->y,$rel->z);//TODO COPY TO ALL TASKS
			}*/
			if (is_null($lastchunkx) || ($block->getPos()->x >> 4 !== $lastchunkx && $block->getPos()->z >> 4 !== $lastchunkz)) {
				$lastchunkx = $block->getPos()->x >> 4;
				$lastchunkz = $block->getPos()->z >> 4;
				if (is_null($manager->getChunk($block->getPos()->x >> 4, $block->getPos()->z >> 4))) {
					#print PHP_EOL . "Not found: " . strval($block->x >> 4) . ":" . strval($block->z >> 4) . PHP_EOL;
					continue;
				}
			}
			$new = clone $newBlocks[array_rand($newBlocks)];
			if ($new->getId() === $block->getId() && $new->getMeta() === $block->getMeta()) continue;//skip same blocks
			#yield self::undoBlockHackToArray($manager->getBlockAt($block->getPos()->getFloorX(), $block->getPos()->getFloorY(), $block->getPos()->getFloorZ()),$block->getPos());
			yield self::singleBlockToData(API::setComponents($manager->getBlockAt($block->getPos()->getFloorX(), $block->getPos()->getFloorY(), $block->getPos()->getFloorZ()), (int)$block->getPos()->x, (int)$block->getPos()->y, (int)$block->getPos()->z));
			#yield $block;//backup
			$manager->setBlockAt($block->getPos()->getFloorX(), $block->getPos()->getFloorY(), $block->getPos()->getFloorZ(), $new);
			if ($manager->getBlockArrayAt($block->getPos()->getFloorX(), $block->getPos()->getFloorY(), $block->getPos()->getFloorZ()) !== [$block->getId(), $block->getMeta()]) {
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
	 * @throws InvalidArgumentException
	 * @throws AssumptionFailedError
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
		$undoChunks = array_map(static function ($chunk) {
			return FastChunkSerializer::deserialize($chunk);
		}, igbinary_unserialize($this->touchedChunks/*, ['allowed_classes' => false]*/));//TODO test pm4)
		#$oldBlocks = igbinary_unserialize($result["oldBlocks"]);
		$oldBlocks = $result["oldBlocks"];//this is already a data map
//		$oldBlocks2 = [];
//		/**
//		 * @var int $fullId
//		 * @var Vector3 $pos
//		 */
//		foreach ($oldBlocks as [$fullId, $pos]) {
//			$b = BlockFactory::getInstance()->fromFullBlock($fullId);
//			$b->getPos()->x = $pos->x;
//			$b->getPos()->y = $pos->y;
//			$b->getPos()->z = $pos->z;
//			$oldBlocks2[] = $b;
//		}
//		var_dump($oldBlocks2);

		$changed = $result["changed"];
		/** @var Selection $selection */
		$selection = igbinary_unserialize($this->selection/*, ['allowed_classes' => [Selection::class]]*/);//TODO test pm4
		$totalCount = $selection->getShape()->getTotalCount();
		$world = $selection->getWorld();
		foreach ($resultChunks as $hash => $chunk) {
			World::getXZ($hash, $x, $z);
			$world->setChunk($x, $z, $chunk, false);
		}
		if (!is_null($session)) {
			$session->sendMessage(TF::GREEN . $session->getLanguage()->translateString('task.fill.success', [$this->generateTookString(), $changed, $totalCount]));
			$session->addRevert(new RevertClipboard($selection->worldId, $undoChunks, $oldBlocks));
		}
	}
}