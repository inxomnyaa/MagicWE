<?php

namespace xenialdan\MagicWE2\task;

use Exception;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use xenialdan\libblockstate\BlockEntry;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\AsyncWorld;
use xenialdan\MagicWE2\helper\BlockPalette;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\session\UserSession;
use function igbinary_serialize;
use function igbinary_unserialize;

class AsyncCopyTask extends MWEAsyncTask
{

	private string $selection;
	private Vector3 $offset;
	private AsyncWorld $manager;

	/**
	 * AsyncCopyTask constructor.
	 * @param UuidInterface $sessionUUID
	 * @param Selection $selection
	 * @param Vector3 $offset
	 * @throws Exception
	 */
	public function __construct(UuidInterface $sessionUUID, Selection $selection, Vector3 $offset)
	{
		$this->start = microtime(true);
		$this->sessionUUID = $sessionUUID->toString();
		$this->selection = igbinary_serialize($selection);
		$this->offset = $offset->asVector3()->floor();
		$this->manager = $selection->getIterator()->getManager();
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
		/** @var Selection $selection */
		$selection = igbinary_unserialize($this->selection/*, ['allowed_classes' => [Selection::class]]*/);//TODO test pm4

		#var_dump("shape", $selection->getShape());
		#var_dump($this->offset);
		$clipboard = new SingleClipboard($this->offset);
		$clipboard->selection = $selection;
		#$clipboard->setCenter(unserialize($this->offset));
		$totalCount = $selection->getShape()->getTotalCount();
		$manager = $this->manager;
		$copied = $this->copyBlocks($selection, $clipboard, $manager);
		#$clipboard->setShape($selection->getShape());
		#$clipboard->chunks = $manager->getChunks();
		$this->setResult(compact("clipboard", "copied", "totalCount"));
	}

	/**
	 * @param Selection $selection
	 * @param SingleClipboard $clipboard
	 * @return int
	 * @throws Exception
	 */
	private function copyBlocks(Selection $selection, SingleClipboard &$clipboard, AsyncWorld &$manager) : int{
		$blockCount = $selection->getShape()->getTotalCount();
		$i = 0;
		$lastprogress = 0;
		$this->publishProgress([0, "Running, copied $i blocks out of $blockCount"]);
		$min = $selection->getShape()->getMinVec3();
		/** @var Block $block */
		foreach($selection->getShape()->getBlocks($manager, BlockPalette::CREATE()) as $block){
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