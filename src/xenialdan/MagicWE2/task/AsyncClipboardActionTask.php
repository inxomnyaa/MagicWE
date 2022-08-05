<?php

namespace xenialdan\MagicWE2\task;

use Exception;
use pocketmine\player\Player;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\Progress;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\session\UserSession;
use xenialdan\MagicWE2\task\action\ClipboardAction;
use function count;
use function igbinary_serialize;
use function igbinary_unserialize;
use function var_dump;

class AsyncClipboardActionTask extends MWEAsyncTask
{

	private string $selection;
	private ClipboardAction $action;
	private string $clipboard;
	private array $rotationData;

	/**
	 * AsyncClipboardActionTask constructor.
	 * @param UuidInterface $sessionUUID
	 * @param Selection $selection
	 * @param ClipboardAction $action
	 * @param SingleClipboard $clipboard
	 */
	public function __construct(UuidInterface $sessionUUID, Selection $selection, ClipboardAction $action, SingleClipboard $clipboard){
		$this->start = microtime(true);
		$this->sessionUUID = $sessionUUID->toString();
		$this->selection = igbinary_serialize($selection);//TODO check if needed, $clipboard already holds the selection
		$this->clipboard = igbinary_serialize($clipboard);//TODO check if this even needs to be serialized
		$this->action = $action;

		$this->rotationData = API::$rotationData;
		var_dump(__CLASS__ . " " . __FUNCTION__ . " " . __LINE__ . " " . __FILE__, count($this->rotationData));

		try{
			$session = SessionHelper::getSessionByUUID($sessionUUID);
			if($session instanceof UserSession){
				$player = $session->getPlayer();
				/** @var Player $player */
				$session->getBossBar()->showTo([$player]);
				$session->getBossBar()->setTitle("Running {$action::getName()} clipboard action");//TODO better string
			}
		}catch(SessionException $e){
			Loader::getInstance()->getLogger()->logException($e);
		}
	}

	/**
	 * Actions to execute when run
	 *
	 * @return void
	 * @throws Exception
	 */
	public function onRun(): void{
		$this->publishProgress(new Progress(0, "Preparing {$this->action::getName()}"));

		var_dump(__CLASS__ . " " . __FUNCTION__ . " " . __LINE__ . " " . __FILE__, count($this->rotationData));

		/** @var Selection $selection */
		$selection = igbinary_unserialize($this->selection/*, ['allowed_classes' => [Selection::class]]*/);//TODO test pm4
		/** @var SingleClipboard $clipboard */
		$clipboard = igbinary_unserialize($this->clipboard/*, ['allowed_classes' => [SingleClipboard::class]]*/);//TODO test pm4
		$clipboard->selection = $selection;//TODO test. Needed to add this so that //paste works after //cut2
		$messages = [];
		/** @var Progress $progress */
		foreach($this->action->execute($this->sessionUUID, $selection, $changed, $clipboard, $messages) as $progress){
			$this->publishProgress($progress);
		}
		//TODO $clipboard->selection shape might change when using rotate. Fix this, so //paste chunks are correct

		$this->setResult(compact("clipboard", "changed", "messages"));
	}

	/**
	 * @throws AssumptionFailedError
	 */
	public function onCompletion(): void
	{
		try {
			$session = SessionHelper::getSessionByUUID(Uuid::fromString($this->sessionUUID));
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
		$selection = igbinary_unserialize($this->selection/*, ['allowed_classes' => [Selection::class]]*/);//TODO test pm4
		$totalCount = $selection->getShape()->getTotalCount();
		if (!is_null($session)) {
			$session->sendMessage(TF::GREEN . $session->getLanguage()->translateString($this->action->completionString, ["name" => trim($this->action->prefix . " " . $this->action::getName()), "took" => $this->generateTookString(), "changed" => $changed, "total" => $totalCount]));
			foreach ($result["messages"] ?? [] as $message) $session->sendMessage($message);
			if ($this->action->addClipboard)
				$session->addClipboard($clipboard);
		}
	}
}