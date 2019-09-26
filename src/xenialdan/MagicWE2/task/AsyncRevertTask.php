<?php

namespace xenialdan\MagicWE2\task;

use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\UUID;
use xenialdan\MagicWE2\clipboard\RevertClipboard;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\session\UserSession;

class AsyncRevertTask extends MWEAsyncTask
{

    const TYPE_UNDO = 0;
    const TYPE_REDO = 1;

    private $clipboard;
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
     * @throws \Exception
     */
    public function onRun()
    {
        $this->publishProgress([0, "Start"]);
        /** @var RevertClipboard $clipboard */
        $clipboard = unserialize($this->clipboard);
        $totalCount = count($clipboard->blocksAfter);
        $manager = $clipboard::getChunkManager($clipboard->chunks);
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
     * @return \Generator|Block[]
     */
    private function undoChunks(AsyncChunkManager $manager, RevertClipboard $clipboard): \Generator
    {
        $count = count($clipboard->blocksAfter);
        $changed = 0;
        $this->publishProgress([0, "Reverted $changed blocks out of $count"]);
        foreach ($clipboard->blocksAfter as $block) {
            yield $manager->getBlockAt($block->getX(), $block->getY(), $block->getZ())->setComponents($block->getX(), $block->getY(), $block->getZ());
            $manager->setBlockAt($block->getX(), $block->getY(), $block->getZ(), $block);
            $changed++;
            $this->publishProgress([$changed / $count, "Reverted $changed blocks out of $count"]);
        }
    }

    /**
     * @param AsyncChunkManager $manager
     * @param RevertClipboard $clipboard
     * @return \Generator|Block[]
     */
    private function redoChunks(AsyncChunkManager $manager, RevertClipboard $clipboard): \Generator
    {
        $count = count($clipboard->blocksAfter);
        $changed = 0;
        $this->publishProgress([0, "Redone $changed blocks out of $count"]);
        foreach ($clipboard->blocksAfter as $block) {
            yield $manager->getBlockAt($block->getX(), $block->getY(), $block->getZ())->setComponents($block->getX(), $block->getY(), $block->getZ());
            $manager->setBlockAt($block->getX(), $block->getY(), $block->getZ(), $block);
            $changed++;
            $this->publishProgress([$changed / $count, "Redone $changed blocks out of $count"]);
        }
    }

    /**
     * @param Server $server
     * @throws \Exception
     */
    public function onCompletion(Server $server)
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
        $clipboard = unserialize($this->clipboard);
        $clipboard->chunks = $result["chunks"];
        $totalCount = $result["totalCount"];
        $changed = count($result["oldBlocks"]);
        $clipboard->blocksAfter = $result["oldBlocks"];
        /** @var Level $level */
        $level = $clipboard->getLevel();
        foreach ($clipboard->chunks as $chunk) {
            $level->setChunk($chunk->getX(), $chunk->getZ(), $chunk, false);
        }
        if (!is_null($session)) {
            switch ($this->type) {
                case self::TYPE_UNDO:
                    {
                        $session->sendMessage(TF::GREEN . Loader::getInstance()->getLanguage()->translateString('task.revert.undo.success', [$this->generateTookString(), $changed, $totalCount]));
                        $session->redoHistory->push($clipboard);
                        break;
                    }
                case self::TYPE_REDO:
                    {
                        $session->sendMessage(TF::GREEN . Loader::getInstance()->getLanguage()->translateString('task.revert.redo.success', [$this->generateTookString(), $changed, $totalCount]));
                        $session->undoHistory->push($clipboard);
                        break;
                    }
            }
        }
    }
}