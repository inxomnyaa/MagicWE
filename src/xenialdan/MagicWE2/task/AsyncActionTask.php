<?php

namespace xenialdan\MagicWE2\task;

use Exception;
use InvalidArgumentException;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use pocketmine\uuid\UUID;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\World;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\clipboard\RevertClipboard;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
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

	/** @var string */
	private $touchedChunks;
	/** @var string */
	private $selection;
	/** @var string */
	private $blockFilter;
	/** @var string */
	private $newBlocks;
	/** @var TaskAction */
	private $action;

	/**
	 * AsyncActionTask constructor.
	 * @param UUID $sessionUUID
	 * @param Selection $selection
	 * @param TaskAction $action
	 * @param string[] $touchedChunks serialized chunks
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
                $player = $session->getPlayer();
                /** @var Player $player */
                $session->getBossBar()->showTo([$player]);
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
     * @throws Exception
     */
    public function onRun(): void
	{
		$this->publishProgress(new Progress(0, "Preparing {$this->action::getName()}"));

		$touchedChunks = unserialize($this->touchedChunks/*, ['allowed_classes' => false]*/);
		$touchedChunks = array_map(static function ($chunk) {
			return FastChunkSerializer::deserialize($chunk);
		}, $touchedChunks);

		$manager = Shape::getChunkManager($touchedChunks);
		unset($touchedChunks);

		/** @var Selection $selection */
		$selection = unserialize($this->selection/*, ['allowed_classes' => [Selection::class]]*/);

		$oldBlocks = new SingleClipboard($this->action->clipboardVector ?? new Vector3(0, 0, 0));//TODO Test if null V3 is ok //TODO test if the vector works
		$oldBlocks->selection = $selection;//TODO test. Needed to add this so that //paste works after //cut2
		#$oldBlocks = [];
		$messages = [];
		$error = false;
		$newBlocks = API::blockParser($this->newBlocks, $messages, $error);//TODO error handling
		$blockFilter = API::blockParser($this->blockFilter, $messages, $error);//TODO error handling
		/** @var Progress $progress */
		foreach ($this->action->execute($this->sessionUUID, $selection, $manager, $changed, $newBlocks, $blockFilter, $oldBlocks, $messages) as $progress) {
			$this->publishProgress($progress);
		}

		$resultChunks = $manager->getChunks();
		$resultChunks = array_filter($resultChunks, static function (Chunk $chunk) {
			return $chunk->isDirty();
		});
		$this->setResult(compact("resultChunks", "oldBlocks", "changed", "messages"));
	}

	/**
	 * @throws InvalidArgumentException
	 * @throws AssumptionFailedError
	 * @throws Exception
	 * @throws Exception
	 */
	public function onCompletion(): void
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
		$undoChunks = array_map(static function ($chunk) {
			return FastChunkSerializer::deserialize($chunk);
		}, unserialize($this->touchedChunks/*, ['allowed_classes' => false]*/));//TODO test pm4
		/** @var SingleClipboard $oldBlocks *///TODO make sure changed everywhere
		$oldBlocks = $result["oldBlocks"];
		//TODO Test this new behaviour!
		//TODO so here i turn SingleClipboard entries into the same $oldBlocks as before this commit
		$oldBlocksBlocks = [];
		$x = $y = $z = null;
		foreach ($oldBlocks->iterateEntries($x, $y, $z) as $entry) {
			$oldBlocksBlocks[] = API::setComponents($entry->toBlock(), (int)$x, (int)$y, (int)$z);//turn BlockEntry to blocks
		}
		$changed = $result["changed"];
		/** @var Selection $selection */
		$selection = unserialize($this->selection/*, ['allowed_classes' => [Selection::class]]*/);//TODO test pm4
		$totalCount = $selection->getShape()->getTotalCount();
		$world = $selection->getWorld();
		foreach ($resultChunks as $hash => $chunk) {
			World::getXZ($hash, $x, $z);
			$world->setChunk($x, $z, $chunk, false);
		}
		if (!is_null($session)) {
			$session->sendMessage(TF::GREEN . $session->getLanguage()->translateString($this->action->completionString, ["name" => trim($this->action->prefix . " " . $this->action::getName()), "took" => $this->generateTookString(), "changed" => $changed, "total" => $totalCount]));
			foreach ($result["messages"] ?? [] as $message) $session->sendMessage($message);
			if ($this->action->addRevert)
				$session->addRevert(new RevertClipboard($selection->worldId, $undoChunks, self::multipleBlocksToData($oldBlocksBlocks)));
			if ($this->action->addClipboard)
				$session->addClipboard($oldBlocks);
        }
    }
}