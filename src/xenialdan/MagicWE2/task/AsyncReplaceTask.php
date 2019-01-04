<?php

namespace xenialdan\MagicWE2\task;

use pocketmine\block\Block;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\utils\UUID;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\AsyncChunkManager;
use xenialdan\MagicWE2\clipboard\RevertClipboard;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\Selection;

class AsyncReplaceTask extends MWEAsyncTask
{

    private $start;
    private $chunks;
    private $selection;
    private $flags;
    private $oldBlocks;
    private $newBlocks;

    /**
     * AsyncReplaceTask constructor.
     * @param Selection $selection
     * @param UUID $playerUUID
     * @param Chunk[] $chunks
     * @param Block[] $oldBlocks
     * @param Block[] $newBlocks
     * @param int $flags
     * @throws \Exception
     */
    public function __construct(Selection $selection, UUID $playerUUID, array $chunks, array $oldBlocks, array $newBlocks, int $flags)
    {
        $this->start = microtime(true);
        $this->chunks = serialize($chunks);
        $this->playerUUID = serialize($playerUUID);
        $this->selection = serialize($selection);
        $this->oldBlocks = serialize($oldBlocks);
        $this->newBlocks = serialize($newBlocks);
        $this->flags = $flags;
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
        /** @var Selection $selection */
        $selection = unserialize($this->selection);
        $manager = Selection::getChunkManager($chunks);
        unset($chunks);
        /** @var Block[] $oldBlocks */
        $oldBlocks = unserialize($this->oldBlocks);
        /** @var Block[] $newBlocks */
        $newBlocks = unserialize($this->newBlocks);
        $totalCount = $selection->getTotalCount();
        $changed = $this->editBlocks($selection, $manager, $oldBlocks, $newBlocks);
        $chunks = $manager->getChunks();
        $chunks = array_filter($chunks, function (Chunk $chunk) {
            return $chunk->hasChanged();
        });
        $this->setResult(compact("chunks", "changed", "totalCount"));
    }

    /**
     * @param Selection $selection
     * @param AsyncChunkManager $manager
     * @param array $oldBlocks
     * @param Block[] $newBlocks
     * @return int
     * @throws \Exception
     */
    private function editBlocks(Selection $selection, AsyncChunkManager $manager, array $oldBlocks, array $newBlocks): int
    {
        $blockCount = $selection->getTotalCount();
        $i = 0;
        $changed = 0;
        $this->publishProgress([0, "Running, changed $changed blocks out of $blockCount | 0% done"]);
        $lastchunkx = $lastchunkz = null;
        $lastprogress = 0;
        /** @var Block $block */
        foreach ($selection->getBlocks($manager, $oldBlocks, $this->flags) as $block) {
            if (is_null($lastchunkx) || $block->x >> 4 !== $lastchunkx && $block->z >> 4 !== $lastchunkz) {
                $lastchunkx = $block->x >> 4;
                $lastchunkz = $block->z >> 4;
                if (is_null(($c = $manager->getChunk($block->x >> 4, $block->z >> 4)))) {
                    print PHP_EOL . "Not found: " . strval($block->x >> 4) . ":" . strval($block->z >> 4) . PHP_EOL;
                    continue;
                }
            }
            /** @var Block $new */
            if (count($newBlocks) === 1)
                $new = clone $newBlocks[0];
            else
                $new = clone $newBlocks[array_rand($newBlocks, 1)];
            if ($new->getId() === $block->getId() && $new->getDamage() === $block->getDamage()) continue;//skip same blocks
            $manager->setBlockAt($block->x, $block->y, $block->z, $new);
            if ($manager->getBlockArrayAt($block->x, $block->y, $block->z) !== [$block->getId(), $block->getDamage()]) {
                $changed++;
            }
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
        /** @var Chunk[] $chunks */
        $chunks = $result["chunks"];
        $undoChunks1 = unserialize($this->chunks);
        /** @var Chunk[] $undoChunks */
        $undoChunks = [];
        foreach ($undoChunks1 as $hash => $data) {
            if (isset($chunks[$hash]) && $chunks[$hash]->hasChanged())
                $undoChunks[$hash] = Chunk::fastDeserialize($data);
        }
        if ($player instanceof Player) {
            $session = API::getSession($player);
            if (is_null($session)) return;
            $bpk = new BossEventPacket();
            $bpk->bossEid = $session->getBossBarId();
            $bpk->eventType = BossEventPacket::TYPE_HIDE;
            $player->dataPacket($bpk);
            $changed = $result["changed"];//todo use extract()
            $totalCount = $result["totalCount"];
            $player->sendMessage(Loader::$prefix . TextFormat::GREEN . "Async Replace succeed, took " . date("i:s:", microtime(true) - $this->start) . strval(round(microtime(true) - $this->start, 1, PHP_ROUND_HALF_DOWN)) . ", $changed blocks out of $totalCount changed.");
            $session->addUndo(new RevertClipboard($player->getLevel()->getId(), $undoChunks));
        }
        /** @var Selection $selection */
        $selection = unserialize($this->selection);
        if ($selection instanceof Selection) {
            /** @var Level $level */
            $level = $selection->getLevel();
            foreach ($chunks as $hash => $chunk) {
                $level->setChunk($chunk->getX(), $chunk->getZ(), $chunk, false);
            }
        } else throw new \Error("Not a selection");
    }
}