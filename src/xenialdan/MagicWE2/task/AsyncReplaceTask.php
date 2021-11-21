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
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\Position;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
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

class AsyncReplaceTask extends MWEAsyncTask
{
	/** @var string */
	private string $touchedChunks;
	/** @var string */
	private string $selection;
	/** @var int */
	private int $flags;
	/** @var BlockPalette */
	private BlockPalette $replaceBlocks;
	/** @var BlockPalette */
	private BlockPalette $newBlocks;

	/**
	 * AsyncReplaceTask constructor.
	 * @param Selection $selection
	 * @param UuidInterface $sessionUUID
	 * @param string[] $touchedChunks serialized chunks
	 * @param BlockPalette $replaceBlocks
	 * @param BlockPalette $newBlocks
	 * @param int $flags
	 * @throws Exception
	 */
	public function __construct(UuidInterface $sessionUUID, Selection $selection, array $touchedChunks, BlockPalette $replaceBlocks, BlockPalette $newBlocks, int $flags)
	{
		$this->start = microtime(true);
		$this->sessionUUID = $sessionUUID->toString();
		$this->selection = serialize($selection);
		$this->touchedChunks = serialize($touchedChunks);
		$this->replaceBlocks = $replaceBlocks;
		$this->newBlocks = $newBlocks;
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
			return FastChunkSerializer::deserializeTerrain($chunk);
		}, unserialize($this->touchedChunks/*, ['allowed_classes' => false]*/));//TODO test pm4

		$manager = Shape::getChunkManager($touchedChunks);
		unset($touchedChunks);

		/** @var Selection $selection */
		$selection = unserialize($this->selection/*, ['allowed_classes' => [Selection::class]]*/);//TODO test pm4

		$oldBlocks = iterator_to_array($this->execute($selection, $manager, $this->replaceBlocks, $this->newBlocks, $changed));

		$resultChunks = $manager->getChunks();
		$resultChunks = array_filter($resultChunks, static function (Chunk $chunk) {
			return $chunk->isTerrainDirty();
		});
		$this->setResult(compact("resultChunks", "oldBlocks", "changed"));
	}

	/**
	 * @param Selection $selection
	 * @param AsyncChunkManager $manager
	 * @param BlockPalette $replaceBlocks
	 * @param BlockPalette $newBlocks
	 * @param null|int $changed
	 * @return Generator
	 * @throws InvalidArgumentException
	 * @phpstan-return Generator<int, array{int, Position|null}, void, void>
	 */
	private function execute(Selection $selection, AsyncChunkManager $manager, BlockPalette $replaceBlocks, BlockPalette $newBlocks, ?int &$changed): Generator
	{
		$blockCount = $selection->getShape()->getTotalCount();
		$lastchunkx = $lastchunkz = null;
		$lastprogress = 0;
		$i = 0;
		$changed = 0;
		$this->publishProgress([0, "Running, changed $changed blocks out of $blockCount"]);
		$iterators = new MultipleIterator();
		$iterators->attachIterator($selection->getShape()->getBlocks($manager, $replaceBlocks, $this->flags));
		$iterators->attachIterator($newBlocks->blocks($blockCount));
		foreach ($iterators as [$block, $new]) {
			/**
			 * @var Block $block
			 * @var Block $new
			 */
			if (is_null($lastchunkx) || ($block->getPosition()->x >> 4 !== $lastchunkx && $block->getPosition()->z >> 4 !== $lastchunkz)) {
				$lastchunkx = $block->getPosition()->x >> 4;
				$lastchunkz = $block->getPosition()->z >> 4;
				if (is_null($manager->getChunk($block->getPosition()->x >> 4, $block->getPosition()->z >> 4))) {
					#print PHP_EOL . "Not found: " . strval($block->x >> 4) . ":" . strval($block->z >> 4) . PHP_EOL;
					continue;
				}
			}
			if ($new->getId() === $block->getId() && $new->getMeta() === $block->getMeta()) continue;//skip same blocks
			yield self::singleBlockToData(API::setComponents($manager->getBlockAt($block->getPosition()->getFloorX(), $block->getPosition()->getFloorY(), $block->getPosition()->getFloorZ()), (int)$block->getPosition()->x, (int)$block->getPosition()->y, (int)$block->getPosition()->z));
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
		$undoChunks = array_map(static function ($chunk) {
			return FastChunkSerializer::deserializeTerrain($chunk);
		}, unserialize($this->touchedChunks/*, ['allowed_classes' => false]*/));//TODO test pm4
		$oldBlocks = $result["oldBlocks"];//this is already as data
		$changed = $result["changed"];
		/** @var Selection $selection */
		$selection = unserialize($this->selection/*, ['allowed_classes' => [Selection::class]]*/);//TODO test pm4
		$totalCount = $selection->getShape()->getTotalCount();
		$world = $selection->getWorld();
		foreach ($resultChunks as $hash => $chunk) {
			World::getXZ($hash, $x, $z);
			$world->setChunk($x, $z, $chunk);
		}
		if (!is_null($session)) {
			$session->sendMessage(TF::GREEN . $session->getLanguage()->translateString('task.replace.success', [$this->generateTookString(), $changed, $totalCount]));
			$session->addRevert(new RevertClipboard($selection->worldId, $undoChunks, $oldBlocks));
		}
	}
}