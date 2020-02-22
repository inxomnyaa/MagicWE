<?php

namespace xenialdan\MagicWE2\task;

use Exception;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\UUID;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\Progress;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\session\UserSession;
use xenialdan\MagicWE2\task\action\ClipboardAction;

class AsyncClipboardActionTask extends MWEAsyncTask
{

    /** @var string */
    private $touchedChunks;
    /** @var string */
    private $selection;
    /** @var string */
    private $blockFilter;
    /** @var string */
    private $newBlocks;
    /** @var ClipboardAction */
    private $action;

    /**
     * AsyncClipboardActionTask constructor.
     * @param UUID $sessionUUID
     * @param Selection $selection
     * @param ClipboardAction $action
     * @param string[] $touchedChunks serialized chunks
     * @param string $newBlocks
     * @param string $blockFilter
     */
    public function __construct(UUID $sessionUUID, Selection $selection, ClipboardAction $action, array $touchedChunks, string $newBlocks = "", string $blockFilter = "")
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
                $session->getBossBar()->setTitle("Running {$action::getName()} clipboard action");//TODO better string
            }
        } catch (SessionException $e) {
            Loader::getInstance()->getLogger()->logException($e);
        }
    }

    /**
     * Actions to execute when run
     *
     * @return void
     * @throws Exception
     */
    public function onRun()
    {
        $this->publishProgress(new Progress(0, "Preparing {$this->action::getName()}"));

        /** @var Selection $selection */
        $selection = unserialize($this->selection);

        $newSingleClipboard = new SingleClipboard($this->action->clipboardVector ?? new Vector3());//TODO Test if null V3 is ok //TODO test if the vector works
        $newSingleClipboard->selection = $selection;//TODO test. Needed to add this so that //paste works after //cut2
        $messages = [];
        /** @var Progress $progress */
        foreach ($this->action->execute($this->sessionUUID, $selection, $changed, $newSingleClipboard, $messages) as $progress) {
            $this->publishProgress($progress);
        }

        $this->setResult(compact("newSingleClipboard", "changed", "messages"));
    }

    /**
     * @param Server $server
     * @throws Exception
     */
    public function onCompletion(Server $server): void
    {
        try {
            $session = SessionHelper::getSessionByUUID(UUID::fromString($this->sessionUUID));
            if ($session instanceof UserSession) $session->getBossBar()->hideFromAll();
        } catch (SessionException $e) {
            Loader::getInstance()->getLogger()->logException($e);
            $session = null;
        }
        $result = $this->getResult();
        /** @var SingleClipboard $newSingleClipboard */
        $newSingleClipboard = $result["newSingleClipboard"];
        $changed = $result["changed"];
        /** @var Selection $selection */
        $selection = unserialize($this->selection);
        $totalCount = $selection->getShape()->getTotalCount();
        if (!is_null($session)) {
            $session->sendMessage(TF::GREEN . $session->getLanguage()->translateString($this->action->completionString, ["name" => trim($this->action->prefix . " " . $this->action::getName()), "took" => $this->generateTookString(), "changed" => $changed, "total" => $totalCount]));
            foreach ($result["messages"] ?? [] as $message) $session->sendMessage($message);
            if ($this->action->addClipboard)
                $session->addClipboard($newSingleClipboard);
        }
    }
}