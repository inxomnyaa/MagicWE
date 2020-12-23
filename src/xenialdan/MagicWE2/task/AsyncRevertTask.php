<?php

namespace xenialdan\MagicWE2\task;

use Exception;
use Generator;
use InvalidArgumentException;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use pocketmine\uuid\UUID;
use pocketmine\world\World;
use xenialdan\MagicWE2\clipboard\RevertClipboard;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\session\UserSession;

class AsyncRevertTask extends MWEAsyncTask
{

	public const TYPE_UNDO = 0;
	public const TYPE_REDO = 1;

	/** @var string */
	private $clipboard;
	/** @var int */
	private $type;

	/**
	 * AsyncRevertTask constructor.
	 * @param UUID $sessionUUID
	 * @param RevertClipboard $clipboard
	 * @param int $type The type of clipboard pasting.
	 */
	public function __construct(UUID $sessionUUID, RevertClipboard $clipboard, $type = self::TYPE_UNDO)
	{
		$this->sessionUUID = $sessionUUID->toString();
        $this->start = microtime(true);
        $this->clipboard = serialize($clipboard);
        $this->type = $type;
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
		/** @var RevertClipboard $clipboard */
		$clipboard = unserialize($this->clipboard/*, ['allowed_classes' => [RevertClipboard::class]]*/);//TODO test pm4
		$totalCount = count($clipboard->blocksAfter);
		$manager = $clipboard::getChunkManager($clipboard->chunks);
		$oldBlocks = [];
		if ($this->type === self::TYPE_UNDO)
			$oldBlocks = iterator_to_array($this->undoChunks($manager, $clipboard));
		if ($this->type === self::TYPE_REDO)
			$oldBlocks = iterator_to_array($this->redoChunks($manager, $clipboard));
		$chunks = $manager->getChunks();
		$this->setResult(compact("chunks", "oldBlocks", "totalCount"));
	}

	/**
	 * @param AsyncChunkManager $manager
	 * @param RevertClipboard $clipboard
	 * @return Generator|array[]
	 * @phpstan-return Generator<int, array{int, Position|null}, void, void>
	 * @throws InvalidArgumentException
	 */
	private function undoChunks(AsyncChunkManager $manager, RevertClipboard $clipboard): Generator
	{
		$count = count($clipboard->blocksAfter);
		$changed = 0;
		$this->publishProgress([0, "Reverted $changed blocks out of $count"]);
		//$block is "data" array
		foreach ($clipboard->blocksAfter as $block) {
			yield $block;
			$block = self::singleDataToBlock($block);//turn data into real block
			$manager->setBlockAt($block->getPos()->getFloorX(), $block->getPos()->getFloorY(), $block->getPos()->getFloorZ(), $block);
			$changed++;
			$this->publishProgress([$changed / $count, "Reverted $changed blocks out of $count"]);
		}
	}

	/**
	 * @param AsyncChunkManager $manager
	 * @param RevertClipboard $clipboard
	 * @return Generator|array[]
	 * @phpstan-return Generator<int, array{int, Position|null}, void, void>
	 * @throws InvalidArgumentException
	 */
	private function redoChunks(AsyncChunkManager $manager, RevertClipboard $clipboard): Generator
	{
		$count = count($clipboard->blocksAfter);
		$changed = 0;
		$this->publishProgress([0, "Redone $changed blocks out of $count"]);
		//$block is "data" array
		foreach ($clipboard->blocksAfter as $block) {
			yield $block;
			$block = self::singleDataToBlock($block);//turn data into real block
			$manager->setBlockAt($block->getPos()->getFloorX(), $block->getPos()->getFloorY(), $block->getPos()->getFloorZ(), $block);
			$changed++;
			$this->publishProgress([$changed / $count, "Redone $changed blocks out of $count"]);
		}
	}

	/**
	 * @throws InvalidArgumentException
	 * @throws AssumptionFailedError
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
		/** @var RevertClipboard $clipboard */
		$clipboard = unserialize($this->clipboard/*, ['allowed_classes' => [RevertClipboard::class]]*/);//TODO test pm4
		$clipboard->chunks = $result["chunks"];
		$totalCount = $result["totalCount"];
		$changed = count($result["oldBlocks"]);
		$clipboard->blocksAfter = $result["oldBlocks"];//already is a array of data
		$world = $clipboard->getWorld();
		foreach ($clipboard->chunks as $hash => $chunk) {
			World::getXZ($hash, $x, $z);
			$world->setChunk($x, $z, $chunk, false);
		}
		if (!is_null($session)) {
			switch ($this->type) {
				case self::TYPE_UNDO:
				{
					$session->sendMessage(TF::GREEN . $session->getLanguage()->translateString('task.revert.undo.success', [$this->generateTookString(), $changed, $totalCount]));
					$session->redoHistory->push($clipboard);
					break;
				}
				case self::TYPE_REDO:
                {
                    $session->sendMessage(TF::GREEN . $session->getLanguage()->translateString('task.revert.redo.success', [$this->generateTookString(), $changed, $totalCount]));
                    $session->undoHistory->push($clipboard);
                    break;
                }
            }
        }
    }
}