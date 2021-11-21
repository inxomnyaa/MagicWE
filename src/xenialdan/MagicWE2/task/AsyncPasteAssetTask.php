<?php

namespace xenialdan\MagicWE2\task;

use BlockHorizons\libschematic\Schematic;
use Exception;
use Generator;
use InvalidArgumentException;
use OutOfBoundsException;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use xenialdan\libblockstate\BlockEntry;
use xenialdan\libstructure\format\MCStructure;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\clipboard\RevertClipboard;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\AsyncWorld;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\session\data\Asset;
use xenialdan\MagicWE2\session\UserSession;
use function igbinary_serialize;
use function igbinary_unserialize;

class AsyncPasteAssetTask extends MWEAsyncTask
{
	private string $selection;
	private string $asset;
//	private ?Vector3 $pasteVector;
	private Vector3 $target;

	/**
	 * AsyncPasteTask constructor.
	 * @param UuidInterface $sessionUUID
	 * @param Vector3 $target
	 * @param Selection $selection
	 * @param Asset $asset
	 * @throws Exception
	 */
	public function __construct(UuidInterface $sessionUUID, Vector3 $target, Selection $selection, Asset $asset)
	{
		$this->start = microtime(true);
//		$this->pasteVector = $selection->getShape()->getPasteVector();#->addVector($asset->getOrigin())->floor();
		$this->target = $target;
		#var_dump("paste", $selection->getShape()->getPasteVector(), "cb position", $clipboard->position, "offset", $this->offset, $clipboard);
		$this->sessionUUID = $sessionUUID->toString();

		$this->selection = igbinary_serialize($selection);
		$this->asset = igbinary_serialize($asset);
	}

	/**
	 * Actions to execute when run
	 *
	 * @return void
	 * @throws InvalidArgumentException
	 * @throws OutOfBoundsException
	 */
	public function onRun(): void
	{
		$this->publishProgress([0, "Start"]);

		/** @var Selection $selection */
		$selection = igbinary_unserialize($this->selection/*, ['allowed_classes' => [Selection::class]]*/);//TODO test pm4

		$manager = $selection->getIterator()->getManager();
		unset($touchedChunks);

		//$selection = igbinary_unserialize($this->selection/*, ['allowed_classes' => [Selection::class]]*/);//TODO test pm4
		//$selection = $this->selection;

		/** @var Asset $asset */
		$asset = igbinary_unserialize($this->asset/*, ['allowed_classes' => [SingleClipboard::class]]*/);//TODO test pm4
		$oldBlocks = iterator_to_array($this->execute($manager, $asset, $changed));

		$resultChunks = $manager->getChunks();
		$resultChunks = array_filter($resultChunks, static function (Chunk $chunk) {
			return $chunk->isTerrainDirty();
		});
		$this->setResult(compact("resultChunks", "oldBlocks", "changed"));
	}

	/**
	 * @param AsyncWorld $manager
	 * @param Asset $asset
	 * @param null|int $changed
	 * @return Generator
	 * @throws InvalidArgumentException
	 * @throws OutOfBoundsException
	 * @phpstan-return Generator<int, array{int, Position|null}, void, void>
	 */
	private function execute(AsyncWorld $manager, Asset $asset, ?int &$changed): Generator
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
			foreach ($structure->blocks() as $block) {// [0,0,0 -> sizex,sizey,sizez]
				#var_dump($block->getPosition()->asVector3(), $this->pasteVector, $this->selection);
				$pos = $block->getPosition()->addVector($this->target)->subtract($asset->getSize()->getX() / 2, 0, $asset->getSize()->getZ() / 2);
				[$block->getPosition()->x, $block->getPosition()->y, $block->getPosition()->z] = [$x, $y, $z] = [$pos->getX(), $pos->getY(), $pos->getZ()];
				#var_dump($block->getPosition()->asVector3());
				if (($x >> 4 !== $lastchunkx && $z >> 4 !== $lastchunkz) || is_null($lastchunkx)) {
					$lastchunkx = $x >> 4;
					$lastchunkz = $z >> 4;
					if (is_null($manager->getChunk($x >> 4, $z >> 4))) {
						print PHP_EOL . "Paste chunk not found in async paste manager: " . ($x >> 4) . ":" . ($z >> 4) . PHP_EOL;
						continue;
					}
				}
				$new = $block;
				yield self::singleBlockToData(API::setComponents($manager->getBlockAt((int)$x, (int)$y, (int)$z), (int)$x, (int)$y, (int)$z));
				$manager->setBlockAt((int)$x, (int)$y, (int)$z, $new);
				$changed++;
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
				$pos = $v->addVector($this->target)->subtract($asset->getSize()->getX() / 2, 0, $asset->getSize()->getZ() / 2);
				[$v->x, $v->y, $v->z] = /*[$x, $y, $z] =*/
					[$pos->getX(), $pos->getY(), $pos->getZ()];
				if (($v->x >> 4 !== $lastchunkx && $v->z >> 4 !== $lastchunkz) || is_null($lastchunkx)) {
					$lastchunkx = $v->x >> 4;
					$lastchunkz = $v->z >> 4;
					if (is_null($manager->getChunk($v->x >> 4, $v->z >> 4))) {
						print PHP_EOL . "Paste chunk not found in async paste manager: " . ($v->x >> 4) . ":" . ($v->z >> 4) . PHP_EOL;
						continue;
					}
				}
				$new = $entry->toBlock();
				yield self::singleBlockToData(API::setComponents($manager->getBlockAt((int)$v->x, (int)$v->y, (int)$v->z), (int)$v->x, (int)$v->y, (int)$v->z));
				$manager->setBlockAt((int)$v->x, (int)$v->y, (int)$v->z, $new);
				$changed++;
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
				$pos = $block->getPosition()->addVector($this->target)->subtract($asset->getSize()->getX() / 2, 0, $asset->getSize()->getZ() / 2);
				[$block->getPosition()->x, $block->getPosition()->y, $block->getPosition()->z] = [$x, $y, $z] = [$pos->getX(), $pos->getY(), $pos->getZ()];
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
		/** @var Selection $selection */
		$selection = igbinary_unserialize($this->selection/*, ['allowed_classes' => [Selection::class]]*/);//TODO test pm4
		$undoChunks = $selection->getIterator()->getManager()->getChunks();
		$totalCount = $selection->getShape()->getTotalCount();
		$world = $selection->getWorld();
		foreach ($resultChunks as $hash => $chunk) {
			World::getXZ($hash, $x, $z);
			$world->setChunk($x, $z, $chunk);
		}
		if (!is_null($session)) {
			$session->sendMessage(TF::GREEN . $session->getLanguage()->translateString('task.fill.success', [$this->generateTookString(), $changed, $totalCount]));
			$session->addRevert(new RevertClipboard($selection->worldId, $undoChunks, $oldBlocks));
		}
	}
}