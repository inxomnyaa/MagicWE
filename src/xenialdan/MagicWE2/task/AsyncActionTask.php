<?php

namespace xenialdan\MagicWE2\task;

use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\UUID;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\clipboard\RevertClipboard;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\Progress;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
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
     * @param string $newBlocks
     * @param string $blockFilter
     */
    public function __construct(UUID $sessionUUID, Selection $selection, TaskAction $action, array $touchedChunks, string $newBlocks = "", string $blockFilter = "")
    {
        $this->start = microtime(true);
        $this->sessionUUID = $sessionUUID->toString();
        $this->selection = serialize($selection);
        $this->action = $action;
        $this->touchedChunks = serialize($touchedChunks);
        $this->newBlocks = $newBlocks;
        $this->blockFilter = $blockFilter;

        try {
            $session = SessionHelper::getSessionByUUID($sessionUUID);
            if ($session instanceof UserSession) {
                $session->getBossBar()->showTo([$session->getPlayer()]);
                $session->getBossBar()->setTitle("Running {$action::getName()} action");//TODO better string
            }
        } catch (SessionException $e) {
            Loader::getInstance()->getLogger()->logException($e);
        }
    }

    /**
     * Actions to execute when run
     *
     * @return void
     * @throws \Exception
     */
    public function onRun()
    {
        $this->publishProgress(new Progress(0, "Preparing"));

        $touchedChunks = unserialize($this->touchedChunks);
        $touchedChunks = array_map(function ($chunk) {
            return Chunk::fastDeserialize($chunk);
        }, $touchedChunks);

        $manager = Shape::getChunkManager($touchedChunks);
        unset($touchedChunks);

        /** @var Selection $selection */
        $selection = unserialize($this->selection);

        $oldBlocks = [];
        $messages = [];
        $error = false;
        $newBlocks = API::blockParser($this->newBlocks, $messages, $error);//TODO error handling
        $blockFilter = API::blockParser($this->blockFilter, $messages, $error);//TODO error handling
        /** @var Progress $progress */
        foreach ($this->action->execute($this->sessionUUID, $selection, $manager, $changed, $newBlocks, $blockFilter, $oldBlocks, $messages) as $progress) {
            $this->publishProgress($progress);
        }

        $resultChunks = $manager->getChunks();
        $resultChunks = array_filter($resultChunks, function (Chunk $chunk) {
            return $chunk->hasChanged();
        });
        $this->setResult(compact("resultChunks", "oldBlocks", "changed", "messages"));
    }

    /**
     * @param Server $server
     * @throws \Exception
     */
    public function onCompletion(Server $server)
    {
        try {
            $session = SessionHelper::getSessionByUUID(UUID::fromString($this->sessionUUID));
            if ($session instanceof UserSession) $session->getBossBar()->hideFromAll();
        } catch (SessionException $e) {
            Loader::getInstance()->getLogger()->logException($e);
            $session = null;
        }
        $result = $this->getResult();
        /** @var Chunk[] $resultChunks */
        $resultChunks = $result["resultChunks"];
        $undoChunks = array_map(function ($chunk) {
            return Chunk::fastDeserialize($chunk);
        }, unserialize($this->touchedChunks));
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
        if (!is_null($session)) {
            $session->sendMessage(TF::GREEN . $session->getLanguage()->translateString($this->action->completionString, ["name" => trim($this->action->prefix . " " . $this->action::getName()), "took" => $this->generateTookString(), "changed" => $changed, "total" => $totalCount]));
            foreach ($result["messages"] ?? [] as $message) $session->sendMessage($message);
            if ($this->action->addRevert)
                $session->addRevert(new RevertClipboard($selection->levelid, $undoChunks, $oldBlocks));
        }
    }
}