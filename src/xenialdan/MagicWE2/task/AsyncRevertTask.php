<?php

namespace xenialdan\MagicWE2\task;

use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\UUID;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\AsyncChunkManager;
use xenialdan\MagicWE2\clipboard\RevertClipboard;
use xenialdan\MagicWE2\Loader;

class AsyncRevertTask extends MWEAsyncTask
{

    const TYPE_UNDO = 0;
    const TYPE_REDO = 1;

    private $start;
    private $chunks;
    private $clipboard;
    private $type;

    /**
     * AsyncRevertTask constructor.
     * @param RevertClipboard $clipboard
     * @param UUID $playerUUID
     * @param Chunk[] $chunks
     * @param int $type The type of clipboard pasting.
     */
    public function __construct(RevertClipboard $clipboard, UUID $playerUUID, array $chunks, $type = self::TYPE_UNDO)
    {
        $this->start = microtime(true);
        $this->playerUUID = serialize($playerUUID);
        $this->chunks = serialize($chunks);
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
        $clipboard = unserialize($this->clipboard, ["allowed_classes" => [RevertClipboard::class]]);
        $chunks = unserialize($this->chunks);
        foreach ($chunks as $hash => $data) {
            $chunks[$hash] = Chunk::fastDeserialize($data);
        }
        $totalCount = count($chunks);
        $manager = RevertClipboard::getChunkManager($clipboard->chunks);
        unset($chunks);
        $changed = $this->editChunks($clipboard, $manager);
        $chunks = $manager->getChunks();
        var_dump("chunks count", count($chunks));
        $this->setResult(compact("chunks", "changed", "totalCount"));
    }

    /**
     * @param RevertClipboard $clipboard
     * @param AsyncChunkManager $manager
     * @return int
     */
    private function editChunks(RevertClipboard $clipboard, AsyncChunkManager $manager): int
    {
        $chunks = $clipboard->chunks;
        $count = count($chunks);
        $changed = 0;
        $this->publishProgress([0, "Running, changed $changed chunks out of $count | 0% done"]);
        foreach ($chunks as $chunk) {
            $manager->setChunk($chunk->getX(), $chunk->getZ(), $chunk);
            $changed++;
            $this->publishProgress([round($changed / $count), "Running, changed $changed chunks out of $count | " . round($changed / $count) . "% done"]);
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
        if ($player instanceof Player) {
            $session = API::getSession($player);
            if (is_null($session)) return;
            $session->getBossBar()->hideFromAll();
            $changed = $result["changed"];//todo use extract()
            $totalCount = $result["totalCount"];
            switch ($this->type) {
                case self::TYPE_UNDO:
                    {
                        $player->sendMessage(Loader::PREFIX . TF::GREEN . "Async Undo succeed, took " . date("i:s:", microtime(true) - $this->start) . strval(round(microtime(true) - $this->start, 1, PHP_ROUND_HALF_DOWN)) . ", $changed chunks out of $totalCount changed.");
                        $session->addRedo(new RevertClipboard($player->getLevel()->getId(), $undoChunks));
                        break;
                    }
                case self::TYPE_REDO:
                    {
                        $player->sendMessage(Loader::PREFIX . TF::GREEN . "Async Redo succeed, took " . date("i:s:", microtime(true) - $this->start) . strval(round(microtime(true) - $this->start, 1, PHP_ROUND_HALF_DOWN)) . ", $changed chunks out of $totalCount changed.");
                        $session->addUndo(new RevertClipboard($player->getLevel()->getId(), $undoChunks));
                        break;
                    }
            }
        }
        /** @var Chunk[] $chunks */
        $chunks = $result["chunks"];
        print "onCompletion chunks count: " . count($chunks);
        /** @var RevertClipboard $clipboard */
        $clipboard = unserialize($this->clipboard);
        if ($clipboard instanceof RevertClipboard) {
            /** @var Level $level */
            $level = $clipboard->getLevel();
            foreach ($chunks as $hash => $chunk) {
                $level->setChunk($chunk->getX(), $chunk->getZ(), $chunk, false);
            }
        } else throw new \Error("Not a clipboard");
    }
}