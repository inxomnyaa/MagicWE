<?php

namespace xenialdan\MagicWE2\task;

use pocketmine\block\Block;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\UUID;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\session\UserSession;

class AsyncFillTask extends MWEAsyncTask
{

    private $start;
    private $chunks;
    private $selection;
    private $flags;
    private $newBlocks;

    /**
     * AsyncFillTask constructor.
     * @param UUID $sessionUUID
     * @param Selection $selection
     * @param Chunk[] $chunks
     * @param Block[] $newBlocks
     * @param int $flags
     * @throws \Exception
     */
    public function __construct(UUID $sessionUUID, Selection $selection, array $chunks, array $newBlocks, int $flags)
    {
        $this->start = microtime(true);
        $this->chunks = serialize($chunks);
        $this->sessionUUID = $sessionUUID->toString();
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
        $oldBlocks = iterator_to_array($this->editBlocks($selection, $manager, $newBlocks));
        $chunks = $manager->getChunks();
        $chunks = array_filter($chunks, function (Chunk $chunk) {
            return $chunk->hasChanged();
        });
        $this->setResult(compact("chunks", "oldBlocks", "totalCount"));
    }

    /**
     * @param Selection $selection
     * @param AsyncChunkManager $manager
     * @param Block[] $newBlocks
     * @return \Generator|Block[]
     * @throws \Exception
     */
    private function editBlocks(Selection $selection, AsyncChunkManager $manager, array $newBlocks): \Generator
    {
        $blockCount = $selection->getTotalCount();
        $i = 0;
        $changed = 0;
        $this->publishProgress([0, "Running, changed $changed blocks out of $blockCount | 0% done"]);
        $lastchunkx = $lastchunkz = null;
        $lastprogress = 0;
        /** @var Block $block */
        foreach ($selection->getBlocks($manager, [], $this->flags) as $block) {
            if (is_null($lastchunkx) || $block->x >> 4 !== $lastchunkx && $block->z >> 4 !== $lastchunkz) {
                $lastchunkx = $block->x >> 4;
                $lastchunkz = $block->z >> 4;
                if (is_null($manager->getChunk($block->x >> 4, $block->z >> 4))) {
                    #print PHP_EOL . "Not found: " . strval($block->x >> 4) . ":" . strval($block->z >> 4) . PHP_EOL;
                    continue;
                }
            }
            /** @var Block $new */
            if (count($newBlocks) === 1)
                $new = clone $newBlocks[0];
            else
                $new = clone $newBlocks[array_rand($newBlocks, 1)];
            if ($new->getId() === $block->getId() && $new->getDamage() === $block->getDamage()) continue;//skip same blocks
            yield $manager->getBlockAt($block->x, $block->y, $block->z)->setComponents($block->x, $block->y, $block->z);
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
    }

    /**
     * @param Server $server
     * @throws \Exception
     */
    public function onCompletion(Server $server)
    {
        $result = $this->getResult();
        /** @var Chunk[] $chunks */
        $chunks = $result["chunks"];
        $undoChunks1 = unserialize($this->chunks);
        /** @var Chunk[] $undoChunks */
        $undoChunks = [];
        foreach ($undoChunks1 as $hash => $data) {
            if (isset($chunks[$hash]) && $chunks[$hash]->hasChanged())
                $undoChunks[$hash] = Chunk::fastDeserialize($data);
        }
        $session = API::getSessions()[$this->sessionUUID];
        if ($session instanceof UserSession) $session->getBossBar()->hideFromAll();
        $oldBlocks = $result["oldBlocks"];
        $changed = count($oldBlocks);
        $totalCount = $result["totalCount"];
        /** @var Selection $selection */
        $selection = unserialize($this->selection);
        /** @var Level $level */
        $level = $selection->getLevel();
        foreach ($chunks as $hash => $chunk) {
            $level->setChunk($chunk->getX(), $chunk->getZ(), $chunk, false);
        }
        $session->sendMessage(TF::GREEN . "Async Fill succeed, took " . date("i:s:", microtime(true) - $this->start) . strval(round(microtime(true) - $this->start, 1, PHP_ROUND_HALF_DOWN)) . ", $changed blocks out of $totalCount changed.");
        $session->addRevert(new RevertClipboard($selection->levelid, $undoChunks, $oldBlocks));
    }
}