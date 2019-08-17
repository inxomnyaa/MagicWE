<?php

namespace xenialdan\MagicWE2\task;

use pocketmine\block\Block;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\UUID;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\clipboard\RevertClipboard;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\selection\shape\Shape;
use xenialdan\MagicWE2\session\UserSession;

class AsyncReplaceTask extends MWEAsyncTask
{

    private $touchedChunks;
    private $selection;
    private $flags;
    private $replaceBlocks;
    private $newBlocks;

    /**
     * AsyncReplaceTask constructor.
     * @param Selection $selection
     * @param UUID $sessionUUID
     * @param Chunk[] $touchedChunks
     * @param Block[] $replaceBlocks
     * @param Block[] $newBlocks
     * @param int $flags
     * @throws \Exception
     */
    public function __construct(UUID $sessionUUID, Selection $selection, array $touchedChunks, array $replaceBlocks, array $newBlocks, int $flags)
    {
        $this->start = microtime(true);
        $this->sessionUUID = $sessionUUID->toString();
        $this->selection = serialize($selection);
        $this->touchedChunks = serialize($touchedChunks);
        $this->replaceBlocks = serialize($replaceBlocks);
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

        $touchedChunks = unserialize($this->touchedChunks);
        array_walk($touchedChunks, function ($chunk) {
            return Chunk::fastDeserialize($chunk);
        });

        $manager = Shape::getChunkManager($touchedChunks);
        unset($touchedChunks);

        /** @var Selection $selection */
        $selection = unserialize($this->selection);

        /** @var Block[] $replaceBlocks */
        $replaceBlocks = unserialize($this->replaceBlocks);
        /** @var Block[] $newBlocks */
        $newBlocks = unserialize($this->newBlocks);

        $oldBlocks = iterator_to_array($this->execute($selection, $manager, $replaceBlocks, $newBlocks, $changed));

        $resultChunks = $manager->getChunks();
        $resultChunks = array_filter($resultChunks, function (Chunk $chunk) {
            return $chunk->hasChanged();
        });
        $this->setResult(compact("resultChunks", "oldBlocks", "changed"));
    }

    /**
     * @param Selection $selection
     * @param AsyncChunkManager $manager
     * @param array $replaceBlocks
     * @param Block[] $newBlocks
     * @param int $changed
     * @return \Generator|Block[]
     * @throws \Exception
     */
    private function execute(Selection $selection, AsyncChunkManager $manager, array $replaceBlocks, array $newBlocks, int &$changed): \Generator
    {
        $blockCount = $selection->getShape()->getTotalCount();
        $lastchunkx = $lastchunkz = null;
        $lastprogress = 0;
        $i = 0;
        $changed = 0;
        $this->publishProgress([0, "Running, changed $changed blocks out of $blockCount | 0% done"]);
        /** @var Block $block */
        foreach ($selection->getShape()->getBlocks($manager, $replaceBlocks, $this->flags) as $block) {
            if (is_null($lastchunkx) || $block->x >> 4 !== $lastchunkx && $block->z >> 4 !== $lastchunkz) {
                $lastchunkx = $block->x >> 4;
                $lastchunkz = $block->z >> 4;
                if (is_null(($c = $manager->getChunk($block->x >> 4, $block->z >> 4)))) {
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
        $session = API::getSessions()[$this->sessionUUID];
        if ($session instanceof UserSession) $session->getBossBar()->hideFromAll();
        $result = $this->getResult();
        /** @var Chunk[] $resultChunks */
        $resultChunks = $result["resultChunks"];
        $undoChunks = unserialize($this->touchedChunks);
        array_walk($undoChunks, function ($chunk) {
            return Chunk::fastDeserialize($chunk);
        });
        $oldBlocks = $result["oldBlocks"];
        $changed = $result["changed"];
        /** @var Selection $selection */
        $selection = unserialize($this->selection);
        $totalCount = $selection->getShape()->getTotalCount();
        /** @var Level $level */
        $level = $selection->getLevel();
        foreach ($resultChunks as $hash => $chunk) {
            $level->setChunk($chunk->getX(), $chunk->getZ(), $chunk, false);
        }
        $session->sendMessage(Loader::PREFIX . TF::GREEN . "Async Replace succeed, took " . $this->generateTookString() . ", $changed blocks out of $totalCount changed.");
        $session->addRevert(new RevertClipboard($selection->levelid, $undoChunks, $oldBlocks));
    }
}