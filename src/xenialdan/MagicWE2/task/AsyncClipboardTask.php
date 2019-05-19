<?php

namespace xenialdan\MagicWE2\task;

use pocketmine\block\Block;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\utils\UUID;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\AsyncChunkManager;
use xenialdan\MagicWE2\clipboard\Clipboard;
use xenialdan\MagicWE2\clipboard\CopyClipboard;
use xenialdan\MagicWE2\clipboard\RevertClipboard;
use xenialdan\MagicWE2\Loader;

class AsyncClipboardTask extends AsyncTask
{

    const TYPE_PASTE = 0;
    const TYPE_SCHEMATIC = 1;
    const TYPE_STRUCTURE = 2;

    private $start;
    private $chunks;
    private $playerUUID;
    private $clipboard;
    private $type;
    private $flags;

    /**
     * AsyncClipboardTask constructor.
     * @param CopyClipboard $clipboard
     * @param UUID $playerUUID
     * @param Chunk[] $chunks
     * @param int $type The type of clipboard pasting.
     * @param int $flags
     * @throws \Exception
     */
    public function __construct(CopyClipboard $clipboard, UUID $playerUUID, array $chunks, $type = self::TYPE_PASTE, int $flags = API::FLAG_BASE)
    {
        $this->start = microtime(true);
        var_dump(__CLASS__ . " clipboard chunks count", count($clipboard->chunks));
        foreach ($clipboard->chunks as $chunk) {
            #$chunk = Chunk::fastDeserialize($chunk);
            var_dump("Clipboard Chunk " . $chunk->getX() . "|" . $chunk->getZ());
        }
        var_dump(__CLASS__ . " clipboard chunks paste count", count($clipboard->chunks));
        foreach ($chunks as $chunk) {
            $chunk = Chunk::fastDeserialize($chunk);
            var_dump("Clipboard Paste Chunk " . $chunk->getX() . "|" . $chunk->getZ());
            $clipboard->pasteChunks[Level::chunkHash($chunk->getX(), $chunk->getZ())] = $chunk;
        }
        $this->chunks = serialize($chunks);
        $this->playerUUID = serialize($playerUUID);
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
        $chunks = unserialize($this->chunks);
        foreach ($chunks as $hash => $data) {
            $chunks[$hash] = Chunk::fastDeserialize($data);
        }
        /** @var CopyClipboard $clipboard */
        $clipboard = unserialize($this->clipboard);
        $manager = Clipboard::getChunkManager($clipboard->pasteChunks);
        $totalCount = $clipboard->getTotalCount();
        unset($chunks);
        $changed = $this->editBlocks($clipboard, $manager);
        $chunks = $manager->getChunks();
        $chunks = array_filter($chunks, function (Chunk $chunk) {
            return $chunk->hasChanged();
        });
        $this->setResult(compact("chunks", "changed", "totalCount"));
    }

    /**
     * @param CopyClipboard $clipboard
     * @param AsyncChunkManager $manager
     * @return int
     * @throws \Exception
     */
    private function editBlocks(CopyClipboard $clipboard, AsyncChunkManager $manager): int
    {
        $blockCount = $clipboard->getTotalCount();
        $cbmanager = Clipboard::getChunkManager($clipboard->chunks);
        $i = 0;
        $lastprogress = 0;
        $lastchunkx = $lastchunkz = null;
        $changed = 0;
        $this->publishProgress([0, "Running, changed $changed blocks out of $blockCount | 0% done"]);
        /** @var Block $block */
        foreach ($clipboard->getBlocks($cbmanager, $this->flags) as $block) {
            var_dump("Block clipboard used", $block);
            if (is_null($lastchunkx) || $block->x >> 4 !== $lastchunkx && $block->z >> 4 !== $lastchunkz) {
                $lastchunkx = $block->x >> 4;
                $lastchunkz = $block->z >> 4;
                if (is_null($manager->getChunk($block->x >> 4, $block->z >> 4))) {
                    print PHP_EOL . "Not found: " . strval($block->x >> 4) . ":" . strval($block->z >> 4) . PHP_EOL;
                    continue;
                }
            }
            $manager->setBlockAt($block->x, $block->y, $block->z, $block);
            #var_dump("Block clipboard pasted", $block);
            $changed++;
            ///
            $i++;
            $progress = floor($i / $blockCount * 100);
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
        $player = $server->getPlayerByUUID(unserialize($this->playerUUID));
        $undoChunks = unserialize($this->chunks);
        foreach ($undoChunks as $hash => $data) {
            $undoChunks[$hash] = Chunk::fastDeserialize($data);
        }
        /*if(($session = API::getSessions()["fake mwe debug player"]) instanceof Session) {
            $player = $session->getPlayer();
            var_dump($session->getPlayer()->getName());
        }*/
        if ($player instanceof Player) {
            /*if(!$session instanceof Session)*/
            $session = API::getSession($player);
            if (is_null($session)) return;
            $session->getBossBar()->hideFromAll();
            $changed = $result["changed"];//todo use extract()
            $totalCount = $result["totalCount"];
            switch ($this->type) {
                case self::TYPE_PASTE:
                    {
                        $player->sendMessage(Loader::$prefix . TextFormat::GREEN . "Async " . (API::hasFlag($this->flags, API::FLAG_POSITION_RELATIVE) ? "relative" : "absolute") . " Clipboard pasting succeed, took " . date("i:s:", microtime(true) - $this->start) . strval(round(microtime(true) - $this->start, 1, PHP_ROUND_HALF_DOWN)) . ", $changed blocks out of $totalCount changed.");
                        $session->addUndo(new RevertClipboard($player->getLevel()->getId(), $undoChunks));
                        break;
                    }
            }
        }
        /** @var Chunk[] $chunks */
        $chunks = $result["chunks"];
        print "onCompletion chunks count: " . count($chunks);
        /** @var CopyClipboard $clipboard */
        $clipboard = unserialize($this->clipboard);
        if ($clipboard instanceof CopyClipboard) {
            /** @var Level $level */
            $level = $clipboard->getLevel();
            foreach ($chunks as $hash => $chunk) {
                $level->setChunk($chunk->getX(), $chunk->getZ(), $chunk, false);
            }
        } else throw new \Error("Not a CopyClipboard");
    }
}