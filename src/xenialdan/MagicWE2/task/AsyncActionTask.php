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
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\selection\shape\Shape;
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

    private $touchedChunks;
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
     * @param Chunk[] $touchedChunks
     * @param Block[] $newBlocks
     * @param Block[] $blockFilter
     */
    public function __construct(UUID $sessionUUID, Selection $selection, TaskAction $action, array $touchedChunks, array $newBlocks = [], array $blockFilter = [])
    {
        $this->start = microtime(true);
        $this->sessionUUID = $sessionUUID->toString();
        $this->selection = serialize($selection);
        $this->action = $action;
        $this->touchedChunks = serialize($touchedChunks);
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

        $touchedChunks = unserialize($this->touchedChunks);
        array_walk($touchedChunks, function ($chunk) {
            return Chunk::fastDeserialize($chunk);
        });

        $manager = Shape::getChunkManager($touchedChunks);
        unset($touchedChunks);

        /** @var Selection $selection */
        $selection = unserialize($this->selection);

        $oldBlocks = iterator_to_array($this->action->execute($this->sessionUUID, $selection, $manager, $changed, unserialize($this->newBlocks), unserialize($this->blockFilter)));

        var_dump($oldBlocks);

        $resultChunks = $manager->getChunks();
        $resultChunks = array_filter($resultChunks, function (Chunk $chunk) {
            return $chunk->hasChanged();
        });
        $this->setResult(compact("resultChunks", "oldBlocks", "changed"));
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
        $session->sendMessage(TF::GREEN . $this->action->getName() . " succeed, took " . $this->generateTookString() . ", $changed blocks out of $totalCount changed.");
        if ($this->action->addRevert)
            $session->addRevert(new RevertClipboard($selection->levelid, $undoChunks, $oldBlocks));
    }
}