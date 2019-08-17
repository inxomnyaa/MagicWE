<?php

namespace xenialdan\MagicWE2\task;

use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\UUID;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\clipboard\RevertClipboard;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
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
        $oldBlocks = iterator_to_array($this->editChunks($manager, $clipboard));
        $chunks = $manager->getChunks();
        $this->setResult(compact("chunks", "oldBlocks", "totalCount"));
    }

    /**
     * @param AsyncChunkManager $manager
     * @param RevertClipboard $clipboard
     * @return \Generator|Block[]
     */
    private function editChunks(AsyncChunkManager $manager, RevertClipboard $clipboard): \Generator
    {
        $count = count($clipboard->blocksAfter);
        $changed = 0;
        $this->publishProgress([0, "Reverted $changed blocks out of $count | 0% done"]);
        foreach ($clipboard->blocksAfter as $block) {
            yield $manager->getBlockAt($block->getX(), $block->getY(), $block->getZ())->setComponents($block->getX(), $block->getY(), $block->getZ());
            $manager->setBlockAt($block->getX(), $block->getY(), $block->getZ(), $block);
            $changed++;
            $this->publishProgress([round($changed / $count), "Reverted $changed blocks out of $count | " . round($changed / $count) . "% done"]);
        }
    }

    /**
     * @param Server $server
     * @throws \Exception
     */
    public function onCompletion(Server $server)
    {
        $result = $this->getResult();
        $session = API::getSessions()[$this->sessionUUID];
        if ($session instanceof UserSession) $session->getBossBar()->hideFromAll();
        /** @var RevertClipboard $clipboard */
        $clipboard = unserialize($this->clipboard);
        $clipboard->chunks = $result["chunks"];
        $clipboard->blocksAfter = $result["oldBlocks"];
        $totalCount = $result["totalCount"];
        $changed = count($clipboard->blocksAfter);
        /** @var Level $level */
        $level = $clipboard->getLevel();
        foreach ($clipboard->chunks as $chunk) {
            $level->setChunk($chunk->getX(), $chunk->getZ(), $chunk, false);
        }
        switch ($this->type) {
            case self::TYPE_UNDO:
                {
                    $session->sendMessage(TF::GREEN . "Async Undo succeed, took " . $this->generateTookString() . ", $changed blocks out of $totalCount changed.");
                    $session->redoHistory->push($clipboard);
                    break;
                }
            case self::TYPE_REDO:
                {
                    $session->sendMessage(TF::GREEN . "Async Redo succeed, took " . $this->generateTookString() . ", $changed blocks out of $totalCount changed.");
                    $session->undoHistory->push($clipboard);
                    break;
                }
        }
    }
}