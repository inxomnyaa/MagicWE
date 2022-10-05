<?php

namespace xenialdan\MagicWE2\task;

use Exception;
use Generator;
use InvalidArgumentException;
use MultipleIterator;
use pocketmine\block\Block;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use TypeError;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\clipboard\RevertClipboard;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\AsyncWorld;
use xenialdan\MagicWE2\helper\BlockPalette;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\session\UserSession;
use function igbinary_serialize;
use function igbinary_unserialize;

class AsyncFillTask extends MWEAsyncTask
{
	private string $selection;
	//private string $newBlocks;
	private BlockPalette $newBlocks;

	/**
	 * AsyncFillTask constructor.
	 * @param UuidInterface $sessionUUID
	 * @param Selection $selection
	 * @param BlockPalette $newBlocks
	 * @throws Exception
	 */
	public function __construct(UuidInterface $sessionUUID, Selection $selection, BlockPalette $newBlocks)
	{
		$this->start = microtime(true);
		$this->sessionUUID = $sessionUUID->toString();
		$this->selection = igbinary_serialize($selection);
		//$this->newBlocks = BlockPalette::encode($newBlocks);
		$this->newBlocks = $newBlocks;//TODO check if serializes
		$this->manager = $selection->getIterator()->getManager();
	}

	/**
	 * Actions to execute when run
	 *
	 * @return void
	 * @throws Exception
	 */
	public function onRun(): void{
		$this->publishProgress([0, "Start"]);

		/** @var Selection $selection */
		$selection = igbinary_unserialize($this->selection/*, ['allowed_classes' => [Selection::class]]*/);//TODO test pm4

		///** @var Block[] $newBlocks */
		//$newBlocks = BlockPalette::decode($this->newBlocks);//TODO test pm4
		//$oldBlocks = iterator_to_array($this->execute($selection, $manager, $newBlocks, $changed));
		$manager = $this->manager;
		$oldBlocks = iterator_to_array($this->execute($selection, $this->newBlocks, $changed, $manager));

		$resultChunks = $manager->getChunks();
		$resultChunks = array_filter($resultChunks, static function(Chunk $chunk){
			return $chunk->isTerrainDirty();
		});
		#$this->setResult(compact("resultChunks", "oldBlocks", "changed"));
		$this->setResult([
			"resultChunks" => $resultChunks,
			"oldBlocks" => $oldBlocks,
			"changed" => $changed
		]);
	}

	/**
	 * @param Selection    $selection
	 * @param BlockPalette $newBlocks
	 * @param null|int     $changed
	 * @param AsyncWorld   $manager
	 *
	 * @return Generator
	 * @throws InvalidArgumentException
	 * @phpstan-return Generator<int, array{int, Position|null}, void, void>
	 */
	private function execute(Selection $selection, BlockPalette $newBlocks, ?int &$changed, AsyncWorld &$manager) : Generator{
		$blockCount = $selection->getShape()->getTotalCount();
		$lastchunkx = $lastchunkz = null;
		$lastprogress = 0;
		$i = 0;
		$changed = 0;
		$this->publishProgress([0, "Running, changed $changed blocks out of $blockCount"]);
		$iterators = new MultipleIterator();
		$iterators->attachIterator($selection->getShape()->getBlocks($manager, BlockPalette::CREATE()));
		$iterators->attachIterator($newBlocks->blocks($blockCount));
		foreach ($iterators as [$block, $new]) {
			/**
			 * @var Block $block
			 * @var Block $new
			 */
			/*if (API::hasFlag($this->flags, API::FLAG_POSITION_RELATIVE)){
				$rel = $block->subtract($selection->shape->getPasteVector());
				$block = API::setComponents($block,$rel->x,$rel->y,$rel->z);//TODO COPY TO ALL TASKS
			}*/
			if (is_null($lastchunkx) || ($block->getPosition()->x >> 4 !== $lastchunkx && $block->getPosition()->z >> 4 !== $lastchunkz)) {
				$lastchunkx = $block->getPosition()->x >> 4;
				$lastchunkz = $block->getPosition()->z >> 4;
				if (is_null($manager->getChunk($block->getPosition()->x >> 4, $block->getPosition()->z >> 4))) {
					#print PHP_EOL . "Not found: " . strval($block->x >> 4) . ":" . strval($block->z >> 4) . PHP_EOL;
					continue;
				}
			}
			if ($new->getId() === $block->getId() && $new->getMeta() === $block->getMeta()) continue;//skip same blocks//TODO better method
			#yield self::undoBlockHackToArray($manager->getBlockAt($block->getPosition()->getFloorX(), $block->getPosition()->getFloorY(), $block->getPosition()->getFloorZ()),$block->getPosition());
			yield self::singleBlockToData(API::setComponents($manager->getBlockAt($block->getPosition()->getFloorX(), $block->getPosition()->getFloorY(), $block->getPosition()->getFloorZ()), (int)$block->getPosition()->x, (int)$block->getPosition()->y, (int)$block->getPosition()->z));
			#yield $block;//backup
			$manager->setBlockAt($block->getPosition()->getFloorX(), $block->getPosition()->getFloorY(), $block->getPosition()->getFloorZ(), $new);
			/** @noinspection PhpInternalEntityUsedInspection */
			if ($manager->getBlockFullIdAt($block->getPosition()->getFloorX(), $block->getPosition()->getFloorY(), $block->getPosition()->getFloorZ()) !== $block->getFullId()) {
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
	 * @throws TypeError
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
//		}, igbinary_unserialize($this->touchedChunks/*, ['allowed_classes' => false]*/));//TODO test pm4)
		#$oldBlocks = igbinary_unserialize($result["oldBlocks"]);
		$oldBlocks = $result["oldBlocks"];//this is already a data map
//		$oldBlocks2 = [];
//		/**
//		 * @var int $fullId
//		 * @var Vector3 $pos
//		 */
//		foreach ($oldBlocks as [$fullId, $pos]) {
//			$b = BlockFactory::getInstance()->fromFullBlock($fullId);
//			$b->getPosition()->x = $pos->x;
//			$b->getPosition()->y = $pos->y;
//			$b->getPosition()->z = $pos->z;
//			$oldBlocks2[] = $b;
//		}
//		var_dump($oldBlocks2);

		$changed = $result["changed"];
		/** @var Selection $selection */
		$selection = igbinary_unserialize($this->selection/*, ['allowed_classes' => [Selection::class]]*/);//TODO test pm4
		$undoChunks = $selection->getIterator()->getManager()->getChunks();
		$totalCount = $selection->getShape()->getTotalCount();
		$world = $selection->getWorld();
		foreach($resultChunks as $hash => $chunk){
			World::getXZ($hash, $x, $z);
			$world->setChunk($x, $z, $chunk);
		}
		if(!is_null($session)){
			$session->sendMessage(TF::GREEN . $session->getLanguage()->translateString('task.fill.success', [$this->generateTookString(), $changed, $totalCount]));
			$session->addRevert(new RevertClipboard($selection->worldId, $undoChunks, $oldBlocks));
		}
	}
}