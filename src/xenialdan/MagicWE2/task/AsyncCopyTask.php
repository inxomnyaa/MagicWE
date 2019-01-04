<?php

namespace xenialdan\MagicWE2\task;

use pocketmine\block\Block;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\utils\UUID;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\AsyncChunkManager;
use xenialdan\MagicWE2\clipboard\CopyClipboard;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\Selection;

class AsyncCopyTask extends MWEAsyncTask
{

    private $start;
    private $chunks;
    private $selection;
    private $offset;
    private $flags;

    /**
     * AsyncCopyTask constructor.
     * @param Selection $selection
     * @param Vector3 $offset
     * @param UUID $playerUUID
     * @param Chunk[] $chunks
     * @param int $flags
     * @throws \Exception
     */
    public function __construct(Selection $selection, Vector3 $offset, UUID $playerUUID, array $chunks, int $flags)
    {
        $this->start = microtime(true);
        $this->chunks = serialize($chunks);
        $this->playerUUID = serialize($playerUUID);
        $this->selection = serialize($selection);
        $this->offset = serialize($offset);
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
        $clipboard = new CopyClipboard($selection->levelid);
        $clipboard->setCenter(unserialize($this->offset));
        $clipboard->setAxisAlignedBB($selection->getAxisAlignedBB());
        $totalCount = $selection->getTotalCount();
        $copied = $this->copyBlocks($selection, $manager, $clipboard);
        $this->setResult(compact("clipboard", "copied", "totalCount"));
    }

    /**
     * @param Selection $selection
     * @param AsyncChunkManager $manager
     * @param CopyClipboard $clipboard
     * @return int
     * @throws \Exception
     */
    private function copyBlocks(Selection $selection, AsyncChunkManager $manager, CopyClipboard &$clipboard): int
    {
        $blockCount = $selection->getTotalCount();
        $i = 0;
        $lastprogress = 0;
        $this->publishProgress([0, "Running, copied $i blocks out of $blockCount | 0% done"]);
        /** @var Block $block */
        foreach ($selection->getBlocks($manager, [], $this->flags) as $block) {
            $chunk = $clipboard->chunks[Level::chunkHash($block->x >> 4, $block->z >> 4)] ?? null;
            if ($chunk === null) {
                $chunk = $manager->getChunk($block->x >> 4, $block->z >> 4);
                $clipboard->chunks[Level::chunkHash($block->x >> 4, $block->z >> 4)] = $chunk;
            }
            $manager->setBlockAt($block->x, $block->y, $block->z, $block);
            $i++;
            $progress = floor($i / $blockCount * 100);
            if ($lastprogress < $progress) {//this prevents spamming packets
                $this->publishProgress([$progress, "Running, copied $i blocks out of $blockCount | " . $progress . "% done"]);
                $lastprogress = $progress;
            }
        }
        var_dump(__METHOD__ . " clipboard chunks count", count($clipboard->chunks));
        return $i;
    }

    public function onCompletion(Server $server)
    {
        $result = $this->getResult();
        $player = $server->getPlayerByUUID(unserialize($this->playerUUID));
        if ($player instanceof Player) {
            $session = API::getSession($player);
            if (is_null($session)) return;
            $bpk = new BossEventPacket();
            $bpk->bossEid = $session->getBossBarId();
            $bpk->eventType = BossEventPacket::TYPE_HIDE;
            $player->dataPacket($bpk);
            $copied = $result["copied"];//todo use extract()
            /** @var CopyClipboard $clipboard */
            $clipboard = $result["clipboard"];
            print $clipboard . PHP_EOL;
            $totalCount = $result["totalCount"];
            $player->sendMessage(Loader::$prefix . TextFormat::GREEN . "Async Copy succeed, took " . date("i:s:", microtime(true) - $this->start) . strval(round(microtime(true) - $this->start, 1, PHP_ROUND_HALF_DOWN)) . ", copied $copied blocks out of $totalCount.");
            $session->setClipboards([0 => $clipboard]);
        }
    }
}