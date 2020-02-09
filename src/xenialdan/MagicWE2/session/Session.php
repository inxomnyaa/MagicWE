<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\session;

use Ds\Deque;
use Exception;
use InvalidArgumentException;
use pocketmine\lang\BaseLang;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\UUID;
use RuntimeException;
use xenialdan\MagicWE2\clipboard\Clipboard;
use xenialdan\MagicWE2\clipboard\RevertClipboard;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\task\AsyncRevertTask;

abstract class Session
{
    const MAX_CLIPBOARDS = 5;
    const MAX_HISTORY = 32;
    /** @var UUID */
    private $uuid;
    //todo change to a list of objects with a pointer of the latest action
    /** @var Selection[] */
    private $selections = [];
    /** @var UUID|null */
    private $latestselection = null;
    //todo change to a list of objects with a pointer of the latest action
    /** @var Clipboard[] */
    private $clipboards = [];
    /** @var int */
    private $currentClipboard = -1;
    /** @var Deque */
    public $undoHistory;
    /** @var Deque */
    public $redoHistory;

    /**
     * @return UUID
     */
    public function getUUID(): UUID
    {
        return $this->uuid;
    }

    /**
     * @param UUID $uuid
     */
    public function setUUID(UUID $uuid): void
    {
        $this->uuid = $uuid;
    }

    /**
     * @param Selection $selection
     * @return null|Selection
     */
    public function &addSelection(Selection $selection)
    {
        $this->selections[$selection->getUUID()->toString()] = $selection;
        $this->setLatestSelectionUUID($selection->getUUID());
        $selection = $this->getLatestSelection();
        return $selection;
    }

    /**
     * @param UUID $uuid
     * @return null|Selection
     */
    public function &getSelectionByUUID(UUID $uuid)
    {
        $selection = $this->selections[$uuid->toString()] ?? null;
        return $selection;
    }

    /**
     * @param string $uuid
     * @return null|Selection
     */
    public function &getSelectionByString(string $uuid)
    {
        $selection = $this->selections[$uuid] ?? null;
        return $selection;
    }

    /**
     * @return null|Selection
     */
    public function &getLatestSelection()
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
    public function setSelections($selections): void
    {
        $this->selections = $selections;
    }

    /**
     * @return UUID|null
     */
    public function getLatestSelectionUUID(): ?UUID
    {
        return $this->latestselection;
    }

    /**
     * @param UUID $latestselection
     */
    public function setLatestSelectionUUID(UUID $latestselection): void
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
            if ($setAsCurrent) $this->currentClipboard = $i;
            return $i;
        }
        return -1;
    }

    /**
     * @param RevertClipboard $revertClipboard
     */
    public function addRevert(RevertClipboard $revertClipboard): void
    {
        $this->redoHistory->clear();
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
        $level = $revertClipboard->getLevel();
        foreach ($revertClipboard->chunks as $hash => $chunk) {
            $revertClipboard->chunks[$hash] = $level->getChunk($chunk->getX(), $chunk->getZ(), false);
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
        $revertClipboard = $this->redoHistory->pop();
        Server::getInstance()->getAsyncPool()->submitTask(new AsyncRevertTask($this->getUUID(), $revertClipboard, AsyncRevertTask::TYPE_REDO));
        $this->sendMessage(TF::GREEN . $this->getLanguage()->translateString('session.redo.left', [count($this->redoHistory)]));
    }

    public function clearHistory(): void
    {
        $this->undoHistory->clear();
        $this->redoHistory->clear();
    }

    public function clearClipboard(): void
    {
        $this->setClipboards([]);
        $this->currentClipboard = -1;
    }

    /**
     * @return BaseLang
     */
    public function getLanguage(): BaseLang
    {
        return Loader::getInstance()->getLanguage();
    }

    public abstract function sendMessage(string $message): void;

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