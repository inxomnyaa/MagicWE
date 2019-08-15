<?php

namespace xenialdan\MagicWE2\task;

use pocketmine\block\Block;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\UUID;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\session\UserSession;
use xenialdan\MagicWE2\task\action\TaskAction;

class AsyncActionTask extends MWEAsyncTask
{
    /*
     * Intention:
     * Shape: get blocks from a shape. Shape can contain options
     * Filterblocks: filter out blocks that are not needed
     * Action: action to run on the remaining blocks, return previous blocks
     * Strings: Begin, completion, bossbar, other stuff can be in the action
    */

    private $start;
    private $chunks;
    private $selection;
    private $blockFilter;
    private $newBlocks;
    /** @var TaskAction */
    private $action;

    /**
     * AsyncFillTask constructor.
     * @param UUID $sessionUUID
     * @param Selection $selection
     * @param TaskAction $action
     * @param Chunk[] $chunks
     * @param Block[] $newBlocks
     * @param Block[] $blockFilter
     */
    public function __construct(UUID $sessionUUID, Selection $selection, TaskAction $action, array $chunks, array $newBlocks = [], array $blockFilter = [])
    {
        $this->start = microtime(true);
        $this->sessionUUID = $sessionUUID->toString();
        $this->selection = serialize($selection);
        $this->action = $action;
        $this->chunks = serialize($chunks);
        $this->newBlocks = serialize($newBlocks);
        $this->blockFilter = serialize($blockFilter);
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
        $manager = Selection::getChunkManager($chunks);
        unset($chunks);

        /** @var Selection $selection */
        $selection = unserialize($this->selection);

        $changed = 0;
        $oldBlocks = iterator_to_array($this->action->execute($this->sessionUUID, $selection, $manager, $changed, unserialize($this->newBlocks), unserialize($this->blockFilter)));

        var_dump($oldBlocks);

        $chunks = $manager->getChunks();
        $chunks = array_filter($chunks, function (Chunk $chunk) {
            return $chunk->hasChanged();
        });
        $this->setResult(compact("chunks", "oldBlocks", "changed"));
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
        $changed = $result["changed"];
        /** @var Selection $selection */
        $selection = unserialize($this->selection);
        $totalCount = $selection->getTotalCount();
        /** @var Level $level */
        $level = $selection->getLevel();
        foreach ($chunks as $hash => $chunk) {
            $level->setChunk($chunk->getX(), $chunk->getZ(), $chunk, false);
        }
        $session->sendMessage(TF::GREEN . $this->action->getName() . " succeed, took " . date("i:s:", microtime(true) - $this->start) . strval(round(microtime(true) - $this->start, 1, PHP_ROUND_HALF_DOWN)) . ", $changed blocks out of $totalCount changed.");
        #$session->addRevert(new RevertClipboard($selection->levelid, $undoChunks, $oldBlocks));
    }
}