<?php

namespace xenialdan\MagicWE2\task;

use Exception;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use pocketmine\uuid\UUID;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\BlockStatesParser;
use xenialdan\MagicWE2\helper\Progress;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\session\UserSession;
use xenialdan\MagicWE2\task\action\ClipboardAction;

class AsyncClipboardActionTask extends MWEAsyncTask
{

    /** @var string */
    private $selection;
    /** @var ClipboardAction */
    private $action;
    /** @var string */
    private $clipboard;
    /** @var string */
    private $rotFlipMapPath;
    /** @var string */
    private $doorRotFlipMapPath;

    /**
     * AsyncClipboardActionTask constructor.
     * @param UUID $sessionUUID
     * @param Selection $selection
     * @param ClipboardAction $action
     * @param SingleClipboard $clipboard
     */
    public function __construct(UUID $sessionUUID, Selection $selection, ClipboardAction $action, SingleClipboard $clipboard)
    {
        $this->start = microtime(true);
        $this->sessionUUID = $sessionUUID->toString();
        $this->selection = serialize($selection);//TODO check if needed, $clipboard already holds the selection
        $this->clipboard = serialize($clipboard);//TODO check if this even needs to be serialized
        $this->action = $action;
        $this->rotFlipMapPath = Loader::getRotFlipPath();
        $this->doorRotFlipMapPath = Loader::getDoorRotFlipPath();

        try {
            $session = SessionHelper::getSessionByUUID($sessionUUID);
            if ($session instanceof UserSession) {
                $player = $session->getPlayer();
                /** @var Player $player */
                $session->getBossBar()->showTo([$player]);
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
    public function onRun(): void
	{
		$this->publishProgress(new Progress(0, "Preparing {$this->action::getName()}"));

		if (!BlockStatesParser::isInit()) BlockStatesParser::init($this->rotFlipMapPath, $this->doorRotFlipMapPath);
		/** @var Selection $selection */
		$selection = unserialize($this->selection, ['allowed_classes' => [Selection::class]]);//TODO test pm4
		/** @var SingleClipboard $clipboard */
		$clipboard = unserialize($this->clipboard, ['allowed_classes' => [SingleClipboard::class]]);//TODO test pm4
		$clipboard->selection = $selection;//TODO test. Needed to add this so that //paste works after //cut2
		$messages = [];
		/** @var Progress $progress */
		foreach ($this->action->execute($this->sessionUUID, $selection, $changed, $clipboard, $messages) as $progress) {
			$this->publishProgress($progress);
		}
		//TODO $clipboard->selection shape might change when using rotate. Fix this, so //paste chunks are correct

		$this->setResult(compact("clipboard", "changed", "messages"));
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
		/** @var SingleClipboard $clipboard */
		$clipboard = $result["clipboard"];
		$changed = $result["changed"];
		/** @var Selection $selection */
		$selection = unserialize($this->selection, ['allowed_classes' => [Selection::class]]);//TODO test pm4
		$totalCount = $selection->getShape()->getTotalCount();
		if (!is_null($session)) {
			$session->sendMessage(TF::GREEN . $session->getLanguage()->translateString($this->action->completionString, ["name" => trim($this->action->prefix . " " . $this->action::getName()), "took" => $this->generateTookString(), "changed" => $changed, "total" => $totalCount]));
			foreach ($result["messages"] ?? [] as $message) $session->sendMessage($message);
			if ($this->action->addClipboard)
				$session->addClipboard($clipboard);
		}
	}
}