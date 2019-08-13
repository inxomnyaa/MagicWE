<?php

namespace xenialdan\MagicWE2\task;

use pocketmine\block\Block;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\UUID;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\clipboard\Clipboard;
use xenialdan\MagicWE2\clipboard\CopyClipboard;
use xenialdan\MagicWE2\clipboard\RevertClipboard;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\session\UserSession;

class AsyncClipboardTask extends MWEAsyncTask
{

    const TYPE_PASTE = 0;
    const TYPE_SCHEMATIC = 1;
    const TYPE_STRUCTURE = 2;

    private $start;
    private $undoChunks;
    private $clipboard;
    private $type;
    private $flags;

    /**
     * AsyncClipboardTask constructor.
     * @param CopyClipboard $clipboard
     * @param UUID $sessionUUID
     * @param Chunk[] $chunks
     * @param int $type The type of clipboard pasting.
     * @param int $flags
     */
    public function __construct(CopyClipboard $clipboard, UUID $sessionUUID, array $chunks, $type = self::TYPE_PASTE, int $flags = API::FLAG_BASE)
    {
        $this->start = microtime(true);
        #var_dump(__CLASS__ . " clipboard chunks count", count($clipboard->chunks));
        #var_dump(__CLASS__ . " clipboard chunks paste count", count($chunks));
        $clipboard->pasteChunks = $chunks;
        /*foreach ($chunks as $chunk) {
            $chunk = Chunk::fastDeserialize($chunk);
            #var_dump("Clipboard Paste Chunk " . $chunk->getX() . "|" . $chunk->getZ());
            $clipboard->pasteChunks[Level::chunkHash($chunk->getX(), $chunk->getZ())] = $chunk;
        }*/
        $this->undoChunks = serialize($chunks);
        $this->sessionUUID = $sessionUUID->toString();
        $this->clipboard = serialize($clipboard);
        $this->flags = $flags;
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
        /** @var CopyClipboard $clipboard */
        $clipboard = unserialize($this->clipboard);
        array_walk($clipboard->pasteChunks, function ($chunk) {
            return Chunk::fastDeserialize($chunk);
        });
        $pasteChunkManager = Clipboard::getChunkManager($clipboard->pasteChunks);
        $totalCount = $clipboard->getTotalCount();
        $changed = $this->editBlocks($clipboard, $pasteChunkManager);
        $chunks = $pasteChunkManager->getChunks();
        $chunks = array_filter($chunks, function (Chunk $chunk) {
            return $chunk->hasChanged();
        });
        $this->setResult(compact("chunks", "changed", "totalCount"));
    }

    /**
     * @param CopyClipboard $clipboard
     * @param AsyncChunkManager $pasteChunkManager
     * @return int
     * @throws \Exception
     */
    private function editBlocks(CopyClipboard $clipboard, AsyncChunkManager $pasteChunkManager): int
    {
        $blockCount = $clipboard->getTotalCount();
        $chunkManager = Clipboard::getChunkManager($clipboard->chunks);
        $lastprogress = 0;
        $lastchunkx = $lastchunkz = null;
        $changed = 0;
        $this->publishProgress([0, "Running, changed $changed blocks out of $blockCount | 0% done"]);
        /** @var Block $block */
        foreach ($clipboard->getBlocks($chunkManager, $this->flags) as $block) {
            var_dump("Block clipboard used", $block);
            if (is_null($lastchunkx) || $block->x >> 4 !== $lastchunkx && $block->z >> 4 !== $lastchunkz) {
                $lastchunkx = $block->x >> 4;
                $lastchunkz = $block->z >> 4;
                if (is_null($pasteChunkManager->getChunk($block->x >> 4, $block->z >> 4))) {
                    print PHP_EOL . "Not found: " . strval($block->x >> 4) . ":" . strval($block->z >> 4) . PHP_EOL;
                    continue;
                }
            }
            $pasteChunkManager->setBlockAt($block->x, $block->y, $block->z, $block);
            $changed++;
            $progress = floor($changed / $blockCount * 100);
            if ($lastprogress < $progress) {//this prevents spamming packets
                $this->publishProgress([$progress, "Running, changed $changed blocks out of $blockCount | " . $progress . "% done"]);
                $lastprogress = $progress;
            }
        }
        return $changed;
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
        $undoChunks = unserialize($this->undoChunks);
        array_walk($undoChunks, function ($chunk) {
            return Chunk::fastDeserialize($chunk);
        });
        $changed = $result["changed"];
        $totalCount = $result["totalCount"];
        /** @var Chunk[] $chunks */
        $chunks = $result["chunks"];
        /** @var CopyClipboard $clipboard */
        $clipboard = unserialize($this->clipboard);
        /** @var Level $level */
        $level = $clipboard->getLevel();
        foreach ($chunks as $hash => $chunk) {
            $level->setChunk($chunk->getX(), $chunk->getZ(), $chunk, false);
        }
        if (is_null($session)) return;
        switch ($this->type) {
            case self::TYPE_PASTE:
                {
                    $session->sendMessage(TF::GREEN . "Async " . (API::hasFlag($this->flags, API::FLAG_POSITION_RELATIVE) ? "relative" : "absolute") . " Clipboard pasting succeed, took " . date("i:s:", microtime(true) - $this->start) . strval(round(microtime(true) - $this->start, 1, PHP_ROUND_HALF_DOWN)) . ", $changed blocks out of $totalCount changed.");
                    $session->addUndo(new RevertClipboard($clipboard->levelid, $undoChunks));
                    break;
                }
        }
    }
}