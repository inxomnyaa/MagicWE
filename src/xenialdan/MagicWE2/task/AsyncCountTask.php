<?php

namespace xenialdan\MagicWE2\task;

use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\level\format\Chunk;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\UUID;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\session\UserSession;

class AsyncCountTask extends MWEAsyncTask
{

    private $start;
    private $chunks;
    private $selection;
    private $flags;
    private $newBlocks;

    /**
     * AsyncFillTask constructor.
     * @param Selection $selection
     * @param UUID $sessionUUID
     * @param Chunk[] $chunks
     * @param Block[] $newBlocks
     * @param int $flags
     * @throws \Exception
     */
    public function __construct(Selection $selection, UUID $sessionUUID, array $chunks, array $newBlocks, int $flags)
    {
        $this->start = microtime(true);
        $this->chunks = serialize($chunks);
        $this->sessionUUID = serialize($sessionUUID);
        $this->selection = serialize($selection);
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
        /** @var Block[] $newBlocks */
        $newBlocks = unserialize($this->newBlocks);
        $totalCount = $selection->getTotalCount();
        $counts = $this->countBlocks($selection, $manager, $newBlocks);
        $this->setResult(compact("counts", "totalCount"));
    }

    /**
     * @param Selection $selection
     * @param AsyncChunkManager $manager
     * @param Block[] $newBlocks
     * @return array
     * @throws \Exception
     */
    private function countBlocks(Selection $selection, AsyncChunkManager $manager, array $newBlocks): array
    {
        $blockCount = $selection->getTotalCount();
        $changed = 0;
        $this->publishProgress([0, "Running, changed $changed blocks out of $blockCount | 0% done"]);
        $lastchunkx = $lastchunkz = null;
        $lastprogress = 0;
        $counts = [];
        /** @var Block $block */
        foreach ($selection->getBlocks($manager, $newBlocks, $this->flags) as $block) {
            if (is_null($lastchunkx) || $block->x >> 4 !== $lastchunkx && $block->z >> 4 !== $lastchunkz) {
                $lastchunkx = $block->x >> 4;
                $lastchunkz = $block->z >> 4;
                if (is_null($manager->getChunk($block->x >> 4, $block->z >> 4))) {
                    #print PHP_EOL . "Not found: " . strval($block->x >> 4) . ":" . strval($block->z >> 4) . PHP_EOL;
                    continue;
                }
            }
            if (!BlockFactory::isInit()) BlockFactory::init();
            $block1 = $manager->getBlockArrayAt($block->x, $block->y, $block->z);
            $tostring = (BlockFactory::get($block1[0], $block1[1]))->getName() . " " . $block1[0] . ":" . $block1[1];
            if (!array_key_exists($tostring, $counts)) $counts[$tostring] = 0;
            $counts[$tostring]++;
            $changed++;
            $progress = floor($changed / $blockCount * 100);
            if ($lastprogress < $progress) {//this prevents spamming packets
                $this->publishProgress([$progress, "Running, counting $changed blocks out of $blockCount | " . $progress . "% done"]);
                $lastprogress = $progress;
            }
        }
        return $counts;
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
        $counts = $result["counts"];
        $totalCount = $result["totalCount"];
        $session->sendMessage(Loader::PREFIX . TF::GREEN . "Async analyzing succeed, took " . date("i:s:", microtime(true) - $this->start) . strval(round(microtime(true) - $this->start, 1, PHP_ROUND_HALF_DOWN)));
        $session->sendMessage(TF::DARK_AQUA . count($counts) . " blocks found in a total of $totalCount blocks");
        uasort($counts, function ($a, $b) {
            if ($a === $b) return 0;
            return ($a > $b) ? -1 : 1;
        });
        foreach ($counts as $block => $count) {
            $session->sendMessage(TF::AQUA . $count . "x | " . round($count / $totalCount * 100) . "% | " . $block);
        }
    }
}