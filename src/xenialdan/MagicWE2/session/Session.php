<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\session;

use Exception;
use InvalidArgumentException;
use pocketmine\lang\Language;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\World;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use SplDoublyLinkedList;
use xenialdan\MagicWE2\clipboard\Clipboard;
use xenialdan\MagicWE2\clipboard\RevertClipboard;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\task\AsyncRevertTask;

abstract class Session
{
	public const MAX_CLIPBOARDS = 5;
	public const MAX_HISTORY = 32;
	/** @var UuidInterface */
	private UuidInterface $uuid;
	//todo change to a list of objects with a pointer of the latest action
	/** @var Selection[] */
	private array $selections = [];
	/** @var UuidInterface|null */
	private ?UuidInterface $latestselection = null;
	//todo change to a list of objects with a pointer of the latest action
	/** @var Clipboard[] */
	private array $clipboards = [];
	/** @var int */
	private int $currentClipboard = -1;
	/** @var SplDoublyLinkedList<RevertClipboard> */
	public SplDoublyLinkedList $undoHistory;
	/** @var SplDoublyLinkedList<RevertClipboard> */
	public SplDoublyLinkedList $redoHistory;

	/**
	 * @return UuidInterface
	 */
	public function getUUID(): UuidInterface
	{
		return $this->uuid;
	}

	/**
	 * @param UuidInterface $uuid
	 */
	public function setUUID(UuidInterface $uuid): void
	{
		$this->uuid = $uuid;
	}

	/**
	 * @param Selection $selection
	 * @return null|Selection
	 */
	public function &addSelection(Selection $selection): ?Selection
	{
		$this->selections[$selection->getUUID()->toString()] = $selection;
		$this->setLatestSelectionUUID($selection->getUUID());
		return $this->getLatestSelection();
	}

	/**
	 * @param UuidInterface $uuid
	 * @return null|Selection
	 */
	public function &getSelectionByUUID(UuidInterface $uuid): ?Selection
	{
		$selection = $this->selections[$uuid->toString()] ?? null;
		return $selection;
	}

	/**
	 * @param string $uuid
	 * @return null|Selection
	 */
	public function &getSelectionByString(string $uuid): ?Selection
	{
		$selection = $this->selections[$uuid] ?? null;
		return $selection;
	}

	/**
	 * @return null|Selection
	 */
	public function &getLatestSelection(): ?Selection
	{
		$latestSelectionUUID = $this->getLatestSelectionUUID();
		if (is_null($latestSelectionUUID)) {
			$selection = null;
			return $selection;
		}
		$selection = $this->selections[$latestSelectionUUID->toString()] ?? null;
		return $selection;
	}

	/**
	 * @return Selection[]
	 */
	public function getSelections(): array
	{
		return $this->selections;
	}

	/**
	 * @param mixed $selections
	 */
	public function setSelections(mixed $selections): void
	{
		$this->selections = $selections;
	}

	/**
	 * @return UuidInterface|null
	 */
	public function getLatestSelectionUUID(): ?UuidInterface
	{
		return $this->latestselection;
	}

	/**
	 * @param UuidInterface $latestselection
	 */
	public function setLatestSelectionUUID(UuidInterface $latestselection): void
	{
		$this->latestselection = $latestselection;
	}

	/**
	 * @return int
	 */
	public function getCurrentClipboardIndex(): int
	{
		return $this->currentClipboard;
	}

	/**
	 * @return null|Clipboard
	 */
	public function getCurrentClipboard(): ?Clipboard
	{
		return $this->clipboards[$this->currentClipboard] ?? null;
	}

	/**
	 * @param string $name
	 * @return null|Clipboard
	 */
	public function getClipboardByName(string $name): ?Clipboard
	{
		foreach ($this->clipboards as $clipboard) {
			if ($clipboard->getCustomName() === $name) return $clipboard;
		}
		return null;
	}

	/**
	 * @param int $id
	 * @return null|Clipboard
	 */
	public function getClipboardById(int $id): ?Clipboard
	{
		return $this->clipboards[$id] ?? null;
	}

	/**
	 * TODO
	 * @return Clipboard[]
	 */
	public function getClipboards(): array
	{
		return $this->clipboards;
	}

	/**
	 * TODO
	 * @param Clipboard[] $clipboards
	 * @return bool
	 */
	public function setClipboards(array $clipboards): bool
	{
		$this->clipboards = $clipboards;
		return true;
	}

	/**
	 * @param Clipboard $clipboard
	 * @param bool $setAsCurrent
	 * @return int The index of the clipboard
	 */
	public function addClipboard(Clipboard $clipboard, bool $setAsCurrent = true): int
	{
		$amount = array_push($this->clipboards, $clipboard);
		if ($amount > self::MAX_CLIPBOARDS) array_shift($this->clipboards);
		$i = array_search($clipboard, $this->clipboards, true);
		if ($i !== false) {
			if ($setAsCurrent) $this->currentClipboard = (int)$i;
			return (int)$i;
		}
		return -1;
	}

	/**
	 * @param RevertClipboard $revertClipboard
	 */
	public function addRevert(RevertClipboard $revertClipboard): void
	{
		$this->redoHistory = new SplDoublyLinkedList();
		$this->undoHistory->push($revertClipboard);
		while ($this->undoHistory->count() > self::MAX_HISTORY) {
			$this->undoHistory->shift();
		}
	}

	/**
	 * @throws Exception
	 */
	public function undo(): void
	{
		if ($this->undoHistory->count() === 0) {
			$this->sendMessage(TF::RED . $this->getLanguage()->translateString('session.undo.none'));
			return;
		}
		/** @var RevertClipboard $revertClipboard */
		$revertClipboard = $this->undoHistory->pop();
		$world = $revertClipboard->getWorld();
		foreach ($revertClipboard->chunks as $hash => $chunk) {
			World::getXZ($hash, $x, $z);
			$revertClipboard->chunks[$hash] = $world->getChunk($x, $z);
		}
		Server::getInstance()->getAsyncPool()->submitTask(new AsyncRevertTask($this->getUUID(), $revertClipboard, AsyncRevertTask::TYPE_UNDO));
		$this->sendMessage(TF::GREEN . $this->getLanguage()->translateString('session.undo.left', [count($this->undoHistory)]));
	}

	/**
	 * @throws InvalidArgumentException
	 * @throws RuntimeException
	 */
	public function redo(): void
	{
		if ($this->redoHistory->count() === 0) {
			$this->sendMessage(TF::RED . $this->getLanguage()->translateString('session.redo.none'));
			return;
		}
		/** @var RevertClipboard $revertClipboard */
		$revertClipboard = $this->redoHistory->pop();
		Server::getInstance()->getAsyncPool()->submitTask(new AsyncRevertTask($this->getUUID(), $revertClipboard, AsyncRevertTask::TYPE_REDO));
		$this->sendMessage(TF::GREEN . $this->getLanguage()->translateString('session.redo.left', [count($this->redoHistory)]));
	}

	public function clearHistory(): void
	{
		$this->undoHistory = new SplDoublyLinkedList();
		$this->redoHistory = new SplDoublyLinkedList();
	}

	public function clearClipboard(): void
	{
		$this->setClipboards([]);
		$this->currentClipboard = -1;
	}

	/**
	 * @return Language
	 */
	public function getLanguage(): Language
	{
		return Loader::getInstance()->getLanguage();
	}

	abstract public function sendMessage(string $message): void;

	public function __toString()
	{
		return __CLASS__ .
			" UUID: " . $this->getUUID()->__toString() .
			" Selections: " . count($this->getSelections()) .
			" Latest: " . $this->getLatestSelectionUUID() .
			" Clipboards: " . count($this->getClipboards()) .
			" Current: " . $this->getCurrentClipboardIndex() .
			" Undos: " . count($this->undoHistory) .
			" Redos: " . count($this->redoHistory);
	}

	/*
	 * TODO list:
	 * session storing/recovering from file/cleanup if too old
	 * session items
	 * recover session items + commands to get back already created/configured items/tool/brushes
	 * proper multi-selection-usage
	 * setState/getState on big actions, status bar/boss bar/texts/titles/popups
	 * inspect other player's sessions
	 * destroy session if owning player lost permission/gets banned
	 * optimise destroySession/__destruct of sessions
	 * clipboard selection (renaming?)
	 */
}