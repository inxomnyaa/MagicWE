<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2;

use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\UUID;
use xenialdan\apibossbar\BossBar;
use xenialdan\MagicWE2\clipboard\Clipboard;
use xenialdan\MagicWE2\clipboard\RevertClipboard;

class Session
{
    const MAX_CLIPBOARDS = 5;
    /** @var UUID */
    private $uuid;
    /** @var Player|null */
    private $player = null;
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
    /** @var bool */
    private $wandEnabled = true;
    /** @var bool */
    private $debugStickEnabled = true;
    /** @var BossBar */
    private $bossBar;

    public function __construct(Player $player)
    {
        $this->setPlayer($player);
        $this->setUUID($player->getUniqueId());
        $this->bossBar = (new BossBar())->addPlayer($player);
        $this->bossBar->hideFrom([$player]);
    }

    public function __destruct()
    {
        if (!is_null($this->uuid)) Loader::getInstance()->getLogger()->debug("Destructing session " . $this->getUUID()->__toString());
        $this->bossBar->removeAllPlayers();
        foreach ($this as &$value) {
            $value = null;
            unset($value);
        }
    }

    /**
     * @param null|Player $player
     */
    public function setPlayer($player)
    {
        $this->player = $player;
    }

    /**
     * @return null|Player
     */
    public function getPlayer()
    {
        return $this->player;
    }

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
     * @return bool
     */
    public function isWandEnabled(): bool
    {
        return $this->wandEnabled;
    }

    /**
     * @param bool $wandEnabled
     * @return string
     */
    public function setWandEnabled(bool $wandEnabled)
    {
        $this->wandEnabled = $wandEnabled;
        return Loader::PREFIX . "The wand tool is now " . ($wandEnabled ? TF::GREEN . "enabled" : TF::RED . "disabled") . TF::RESET . "!";//TODO #translation
    }

    /**
     * @return bool
     */
    public function isDebugStickEnabled(): bool
    {
        return $this->debugStickEnabled;
    }

    /**
     * @param bool $debugStick
     * @return string
     */
    public function setDebugStickEnabled(bool $debugStick)
    {
        $this->debugStickEnabled = $debugStick;
        return Loader::PREFIX . "The debug stick is now " . ($debugStick ? TF::GREEN . "enabled" : TF::RED . "disabled") . TF::RESET . "!";//TODO #translation
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
     * @param RevertClipboard $RevertClipboard
     */
    public function addUndo(RevertClipboard $RevertClipboard)
    {
        array_push($this->undo, $RevertClipboard);
    }

    /**
     * @return null|RevertClipboard
     */
    public function getLatestUndo(): ?RevertClipboard
    {
        $RevertClipboards = $this->getUndos();
        $return = array_pop($RevertClipboards);
        $this->setUndos($RevertClipboards);
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
     * @param RevertClipboard $RevertClipboard
     */
    public function addRedo(RevertClipboard $RevertClipboard)
    {
        array_push($this->redo, $RevertClipboard);
    }

    /**
     * @return null|RevertClipboard
     */
    public function getLatestRedo(): ?RevertClipboard
    {
        $RevertClipboards = $this->getRedos();
        $return = array_pop($RevertClipboards);
        $this->setRedos($RevertClipboards);
        return $return;
    }

    /**
     * @return BossBar
     */
    public function getBossBar(): BossBar
    {
        return $this->bossBar;
    }

    /*
     * TODO list:
     * session storing/recovering from file/cleanup if too old
     * session items
     * session brushes
     * recover session items + commands to get back already created/configured items/tools/brushes
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