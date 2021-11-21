<?php

namespace xenialdan\MagicWE2\task;

use Exception;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\BlockPalette;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\session\UserSession;
use function igbinary_serialize;
use function igbinary_unserialize;

class AsyncCountTask extends MWEAsyncTask
{
	/** @var string */
	private string $selection;
	/** @var BlockPalette */
	private BlockPalette $filterblocks;

	/**
	 * AsyncCountTask constructor.
	 * @param Selection $selection
	 * @param UuidInterface $sessionUUID
	 * @param BlockPalette $filterblocks
	 * @throws Exception
	 */
	public function __construct(UuidInterface $sessionUUID, Selection $selection, BlockPalette $filterblocks)
	{
		$this->start = microtime(true);
		$this->sessionUUID = $sessionUUID->toString();
		$this->selection = igbinary_serialize($selection);
		$this->filterblocks = $filterblocks;
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
		$totalCount = $selection->getShape()->getTotalCount();
		$counts = $this->countBlocks($selection, $this->filterblocks);
		$this->setResult(compact("counts", "totalCount"));
	}

	/**
	 * @param Selection $selection
	 * @param BlockPalette $filterblocks
	 * @return array
	 * @throws Exception
	 */
	private function countBlocks(Selection $selection, BlockPalette $filterblocks): array
	{
		$manager = $selection->getIterator()->getManager();
		$blockCount = $selection->getShape()->getTotalCount();
		$changed = 0;
		$this->publishProgress([0, "Running, counting $changed blocks out of $blockCount"]);
		$lastchunkx = $lastchunkz = null;
		$lastprogress = 0;
		$counts = [];
		/** @var Block $block */
		foreach ($selection->getShape()->getBlocks($manager, $filterblocks) as $block) {
			if (is_null($lastchunkx) || ($block->getPosition()->x >> 4 !== $lastchunkx && $block->getPosition()->z >> 4 !== $lastchunkz)) {
				$lastchunkx = $block->getPosition()->x >> 4;
				$lastchunkz = $block->getPosition()->z >> 4;
				if (is_null($manager->getChunk($block->getPosition()->x >> 4, $block->getPosition()->z >> 4))) {
					#print PHP_EOL . "Not found: " . strval($block->x >> 4) . ":" . strval($block->z >> 4) . PHP_EOL;
					continue;
				}
			}
			BlockFactory::getInstance();
			$block1 = $manager->getBlockAt($block->getPosition()->getFloorX(), $block->getPosition()->getFloorY(), $block->getPosition()->getFloorZ());
			$tostring = $block1->getName() . " " . $block1->getId() . ":" . $block1->getMeta();
			if (!array_key_exists($tostring, $counts)) $counts[$tostring] = 0;
			$counts[$tostring]++;
			$changed++;
			$progress = floor($changed / $blockCount * 100);
			if ($lastprogress < $progress) {//this prevents spamming packets
				$this->publishProgress([$progress, "Running, counting $changed blocks out of $blockCount"]);
				$lastprogress = $progress;
			}
		}
		return $counts;
	}

	/**
	 * @throws AssumptionFailedError
	 */
	public function onCompletion(): void
	{
		try {
			$session = SessionHelper::getSessionByUUID(Uuid::fromString($this->sessionUUID));
			if ($session instanceof UserSession) $session->getBossBar()->hideFromAll();
			$result = $this->getResult();
			$counts = $result["counts"];
			$totalCount = $result["totalCount"];
			$session->sendMessage(TF::GREEN . $session->getLanguage()->translateString('task.count.success', [$this->generateTookString()]));
			$session->sendMessage(TF::DARK_AQUA . $session->getLanguage()->translateString('task.count.result', [count($counts), $totalCount]));
			uasort($counts, static function ($a, $b) {
				if ($a === $b) return 0;
				return ($a > $b) ? -1 : 1;
			});
			foreach ($counts as $block => $count) {
				$session->sendMessage(TF::AQUA . $count . "x | " . round($count / $totalCount * 100) . "% | " . $block);
			}
		} catch (SessionException $e) {
			Loader::getInstance()->getLogger()->logException($e);
		}
	}
}