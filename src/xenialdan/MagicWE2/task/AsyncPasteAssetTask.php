<?php

namespace xenialdan\MagicWE2\task;

use BlockHorizons\libschematic\Schematic;
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
use pocketmine\world\World;
use xenialdan\libstructure\format\MCStructure;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\clipboard\RevertClipboard;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\helper\BlockEntry;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\selection\shape\Shape;
use xenialdan\MagicWE2\session\data\Asset;
use xenialdan\MagicWE2\session\UserSession;

class AsyncPasteAssetTask extends MWEAsyncTask
{
	/** @var string */
	private $touchedChunks;
	/** @var Selection */
	private $selection;
	/** @var string */
	private $asset;
	/** @var Vector3 */
	private $pasteVector;

	/**
	 * AsyncPasteTask constructor.
	 * @param UUID $sessionUUID
	 * @param Selection $selection
	 * @param string[] $touchedChunks serialized chunks
	 * @param Asset $asset
	 */
	public function __construct(UUID $sessionUUID, Selection $selection, array $touchedChunks, Asset $asset)
	{
		$this->start = microtime(true);
		$this->pasteVector = $selection->getShape()->getPasteVector();#->addVector($asset->getOrigin())->floor();
		#var_dump("paste", $selection->getShape()->getPasteVector(), "cb position", $clipboard->position, "offset", $this->offset, $clipboard);
		$this->sessionUUID = $sessionUUID->toString();
		$this->selection = $selection;
		$this->touchedChunks = serialize($touchedChunks);
		$this->asset = serialize($asset);
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

		$touchedChunks = array_map(static function ($chunk) {//todo add hash as key
			return FastChunkSerializer::deserialize($chunk);
		}, unserialize($this->touchedChunks/*, ['allowed_classes' => false]*/));//TODO test pm4

		$manager = Shape::getChunkManager($touchedChunks);
		unset($touchedChunks);

		/** @var Selection $selection */
		//$selection = unserialize($this->selection/*, ['allowed_classes' => [Selection::class]]*/);//TODO test pm4
		$selection = $this->selection;

		/** @var Asset $asset */
		$asset = unserialize($this->asset/*, ['allowed_classes' => [SingleClipboard::class]]*/);//TODO test pm4
		$oldBlocks = iterator_to_array($this->execute($manager, $asset, $changed));

		$resultChunks = $manager->getChunks();
		$resultChunks = array_filter($resultChunks, static function (Chunk $chunk) {
			return $chunk->isDirty();
		});
		$this->setResult(compact("resultChunks", "oldBlocks", "changed"));
	}

	/**
	 * @param AsyncChunkManager $manager
	 * @param Asset $asset
	 * @param null|int $changed
	 * @return Generator
	 * @throws InvalidArgumentException
	 * @throws \UnexpectedValueException
	 * @phpstan-return Generator<int, array{int, \pocketmine\world\Position|null}, void, void>
	 */
	private function execute(AsyncChunkManager $manager, Asset $asset, ?int &$changed): Generator
	{
		$blockCount = $asset->getTotalCount();
		$lastchunkx = $lastchunkz = $x = $y = $z = null;
		$lastprogress = 0;
		$i = 0;
		$changed = 0;
		$this->publishProgress([0, "Running, changed $changed blocks out of $blockCount"]);
		$structure = $asset->structure;
		if ($structure instanceof MCStructure) {
			/** @var Block $block */
			foreach ($structure->blocks() as $block) {
				var_dump($block->getPos()->asVector3(), $this->pasteVector, $this->selection);
				$pos = $block->getPos()->addVector($this->pasteVector);
				[$block->getPos()->x, $block->getPos()->y, $block->getPos()->z] = [$x, $y, $z] = [$pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ()];
				var_dump($block->getPos()->asVector3());
				if (($x >> 4 !== $lastchunkx && $z >> 4 !== $lastchunkz) || is_null($lastchunkx)) {
					$lastchunkx = $x >> 4;
					$lastchunkz = $z >> 4;
					if (is_null($manager->getChunk($x >> 4, $z >> 4))) {
						print PHP_EOL . "Paste chunk not found in async paste manager: " . ($x >> 4) . ":" . ($z >> 4) . PHP_EOL;
						continue;
					}
				}
				$new = $block;
				yield self::singleBlockToData(API::setComponents($manager->getBlockAt($x, $y, $z), (int)$x, (int)$y, (int)$z));
				$manager->setBlockAt($x, $y, $z, $new);
				if ($manager->getBlockArrayAt($x, $y, $z) !== [$manager->getBlockAt($x, $y, $z)->getId(), $manager->getBlockAt($x, $y, $z)->getMeta()]) {//TODO remove? Just useless waste imo
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
		} else if ($structure instanceof SingleClipboard) {
			/** @var BlockEntry $entry */
			foreach ($structure->iterateEntries($x, $y, $z) as $entry) {
				$v = new Vector3($x, $y, $z);
				var_dump($v);
				$pos = $v->addVector($this->pasteVector);
				[$x, $y, $z] = [$pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ()];
				if (($x >> 4 !== $lastchunkx && $z >> 4 !== $lastchunkz) || is_null($lastchunkx)) {
					$lastchunkx = $x >> 4;
					$lastchunkz = $z >> 4;
					if (is_null($manager->getChunk($x >> 4, $z >> 4))) {
						print PHP_EOL . "Paste chunk not found in async paste manager: " . ($x >> 4) . ":" . ($z >> 4) . PHP_EOL;
						continue;
					}
				}
				$new = $entry->toBlock();
				yield self::singleBlockToData(API::setComponents($manager->getBlockAt($x, $y, $z), (int)$x, (int)$y, (int)$z));
				$manager->setBlockAt($x, $y, $z, $new);
				if ($manager->getBlockArrayAt($x, $y, $z) !== [$manager->getBlockAt($x, $y, $z)->getId(), $manager->getBlockAt($x, $y, $z)->getMeta()]) {//TODO remove? Just useless waste imo
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
		} else if ($structure instanceof Schematic) {
			foreach ($structure->blocks() as $block) {
				$pos = $block->getPos()->addVector($this->pasteVector);
				var_dump($pos);
				[$block->getPos()->x, $block->getPos()->y, $block->getPos()->z] = [$x, $y, $z] = [$pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ()];
				if (($x >> 4 !== $lastchunkx && $z >> 4 !== $lastchunkz) || is_null($lastchunkx)) {
					$lastchunkx = $x >> 4;
					$lastchunkz = $z >> 4;
					if (is_null($manager->getChunk($x >> 4, $z >> 4))) {
						print PHP_EOL . "Paste chunk not found in async paste manager: " . ($x >> 4) . ":" . ($z >> 4) . PHP_EOL;
						continue;
					}
				}
				$new = $block;
				yield self::singleBlockToData(API::setComponents($manager->getBlockAt($x, $y, $z), (int)$x, (int)$y, (int)$z));
				$manager->setBlockAt($x, $y, $z, $new);
				if ($manager->getBlockArrayAt($x, $y, $z) !== [$manager->getBlockAt($x, $y, $z)->getId(), $manager->getBlockAt($x, $y, $z)->getMeta()]) {//TODO remove? Just useless waste imo
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
		$undoChunks = array_map(static function ($chunk) {
			return FastChunkSerializer::deserialize($chunk);
		}, unserialize($this->touchedChunks/*, ['allowed_classes' => false]*/));//TODO test pm4
		$oldBlocks = $result["oldBlocks"];//already data array
		$changed = $result["changed"];
		/** @var Selection $selection */
		//$selection = unserialize($this->selection/*, ['allowed_classes' => [Selection::class]]*/);//TODO test pm4
		$selection = $this->selection;
		$totalCount = $selection->getShape()->getTotalCount();
		$world = $this->selection->getWorld();
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