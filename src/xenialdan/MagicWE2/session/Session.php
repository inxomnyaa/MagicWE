<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\session;

use pocketmine\utils\UUID;
use xenialdan\MagicWE2\clipboard\Clipboard;
use xenialdan\MagicWE2\clipboard\RevertClipboard;
use xenialdan\MagicWE2\selection\Selection;

abstract class Session
{
    const MAX_CLIPBOARDS = 5;
    /** @var UUID */
    private $uuid;
    /** @var Selection[] */
    private $selections = [];
    /** @var UUID|null */
    private $latestselection = null;
    /** @var int */
    private $currentClipboard = -1;
    /** @var Clipboard[] */
    private $clipboards = [];
    /** @var RevertClipboard[] */
    private $undo = [];
    /** @var RevertClipboard[] */
    private $redo = [];
    //todo change to a list of objects with a pointer of the latest action

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
    public function setUUID(UUID $uuid)
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
    public function getSelections()
    {
        return $this->selections;
    }

    /**
     * @param mixed $selections
     */
    public function setSelections($selections)
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
    public function setLatestSelectionUUID(UUID $latestselection)
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
    public function setClipboards(array $clipboards)
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
        $i = array_search($clipboard, $this->clipboards, true);
        if ($i !== false) {
            if ($setAsCurrent) $this->currentClipboard = $i;
        }
        if ($amount > self::MAX_CLIPBOARDS) array_shift($this->clipboards);
        if ($i !== false) {
            if ($setAsCurrent) $this->currentClipboard = $i;
            return $i;
        }
        return -1;
    }

    /**
     * @return RevertClipboard[]
     */
    public function getUndos(): array
    {
        return $this->undo;
    }

    /**
     * @param RevertClipboard[] $undo
     */
    private function setUndos(array $undo)
    {
        $this->undo = $undo;
    }

    /**
     * @param RevertClipboard $revertClipboard
     */
    public function addUndo(RevertClipboard $revertClipboard)
    {
        array_push($this->undo, $revertClipboard);
    }

    /**
     * @return null|RevertClipboard
     */
    public function getLatestUndo(): ?RevertClipboard
    {
        $revertClipboards = $this->getUndos();
        $return = array_pop($revertClipboards);
        $this->setUndos($revertClipboards);
        return $return;
    }

    /**
     * @return RevertClipboard[]
     */
    public function getRedos(): array
    {
        return $this->redo;
    }

    /**
     * @param RevertClipboard[] $redo
     */
    private function setRedos(array $redo)
    {
        $this->redo = $redo;
    }

    /**
     * @param RevertClipboard $revertClipboard
     */
    public function addRedo(RevertClipboard $revertClipboard)
    {
        array_push($this->redo, $revertClipboard);
    }

    /**
     * @return null|RevertClipboard
     */
    public function getLatestRedo(): ?RevertClipboard
    {
        $revertClipboards = $this->getRedos();
        $return = array_pop($revertClipboards);
        $this->setRedos($revertClipboards);
        return $return;
    }

    public function clearHistory()
    {
        $this->setUndos([]);
        $this->setRedos([]);
    }

    public abstract function sendMessage(string $message);

    public function __toString()
    {
        return __CLASS__ .
            " UUID: " . $this->getUUID()->__toString() .
            " Selections: " . count($this->getSelections()) .
            " Latest: " . $this->getLatestSelectionUUID() .
            " Clipboards: " . count($this->getClipboards()) .
            " Current: " . $this->getCurrentClipboardIndex() .
            " Undos: " . count($this->getUndos()) .
            " Redos: " . count($this->getRedos());
    }

    /*
     * TODO list:
     * session storing/recovering from file/cleanup if too old
     * session items
     * session brushes
     * recover session items + commands to get back already created/configured items/tool/brushes
     * proper multi-selection-usage
     * setState/getState on big actions, status bar/boss bar/texts/titles/popups
     * inspect other player's sessions
     * destroy session if owning player lost permission/gets banned
     * optimise destroySession/__destruct of sessions
     * clipboard selection (renaming?)
     *
     * ask users what else they want
     */
}