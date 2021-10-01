<?php

namespace xenialdan\MagicWE2\task;

use Exception;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\format\io\FastChunkSerializer;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\helper\BlockEntry;
use xenialdan\MagicWE2\helper\BlockPalette;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\selection\shape\Shape;
use xenialdan\MagicWE2\session\UserSession;

class AsyncCopyTask extends MWEAsyncTask
{

	/** @var string */
	private string $chunks;
	/** @var string */
	private string $selection;
	/** @var Vector3 */
	private Vector3 $offset;
	/** @var int */
	private int $flags;

	/**
	 * AsyncCopyTask constructor.
	 * @param Selection $selection
	 * @param Vector3 $offset
	 * @param UuidInterface $sessionUUID
	 * @param string[] $chunks serialized chunks
	 * @param int $flags
	 * @throws Exception
	 */
	public function __construct(UuidInterface $sessionUUID, Selection $selection, Vector3 $offset, array $chunks, int $flags)
	{
		$this->start = microtime(true);
		$this->chunks = serialize($chunks);
		$this->sessionUUID = $sessionUUID->toString();
		$this->selection = serialize($selection);
		$this->offset = $offset->asVector3()->floor();
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
		$chunks = array_map(static function ($chunk) {
			return FastChunkSerializer::deserialize($chunk);
		}, unserialize($this->chunks/*, ['allowed_classes' => false]*/));//TODO test pm4
		/** @var Selection $selection */
		$selection = unserialize($this->selection/*, ['allowed_classes' => [Selection::class]]*/);//TODO test pm4
		#var_dump("shape", $selection->getShape());
		$manager = Shape::getChunkManager($chunks);
		unset($chunks);
		#var_dump($this->offset);
		$clipboard = new SingleClipboard($this->offset);
		$clipboard->selection = $selection;
		#$clipboard->setCenter(unserialize($this->offset));
		$totalCount = $selection->getShape()->getTotalCount();
		$copied = $this->copyBlocks($selection, $manager, $clipboard);
		#$clipboard->setShape($selection->getShape());
		#$clipboard->chunks = $manager->getChunks();
		$this->setResult(compact("clipboard", "copied", "totalCount"));
	}

	/**
	 * @param Selection $selection
	 * @param AsyncChunkManager $manager
	 * @param SingleClipboard $clipboard
	 * @return int
	 * @throws Exception
	 */
	private function copyBlocks(Selection $selection, AsyncChunkManager $manager, SingleClipboard $clipboard): int
	{
		$blockCount = $selection->getShape()->getTotalCount();
		$i = 0;
		$lastprogress = 0;
		$this->publishProgress([0, "Running, copied $i blocks out of $blockCount"]);
		$min = $selection->getShape()->getMinVec3();
		/** @var Block $block */
		foreach ($selection->getShape()->getBlocks($manager, BlockPalette::CREATE(), $this->flags) as $block) {
			#var_dump("copy chunk X: " . ($block->getX() >> 4) . " Y: " . ($block->getY() >> 4));
			$newv3 = $block->getPosition()->subtractVector($min)->floor();
			/** @noinspection PhpInternalEntityUsedInspection */
			$clipboard->addEntry($newv3->getFloorX(), $newv3->getFloorY(), $newv3->getFloorZ(), new BlockEntry($block->getFullId()));//TODO test tiles
			#var_dump("copied selection block", $block);
			$i++;
			$progress = floor($i / $blockCount * 100);
			if ($lastprogress < $progress) {//this prevents spamming packets
				$this->publishProgress([$progress, "Running, copied $i blocks out of $blockCount"]);
				$lastprogress = $progress;
			}
		}
		return $i;
	}

	public function onCompletion(): void
	{
		try {
			$session = SessionHelper::getSessionByUUID(Uuid::fromString($this->sessionUUID));
			if ($session instanceof UserSession) $session->getBossBar()->hideFromAll();
			$result = $this->getResult();
			$copied = $result["copied"];
			/** @var SingleClipboard $clipboard */
			$clipboard = $result["clipboard"];
			$totalCount = $result["totalCount"];
			$session->sendMessage(TF::GREEN . $session->getLanguage()->translateString('task.copy.success', [$this->generateTookString(), $copied, $totalCount]));
			$session->addClipboard($clipboard);
		} catch (SessionException | AssumptionFailedError $e) {
			Loader::getInstance()->getLogger()->logException($e);
		}
	}
}