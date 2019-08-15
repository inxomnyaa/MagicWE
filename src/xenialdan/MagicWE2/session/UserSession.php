<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\session;

use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\apibossbar\BossBar;
use xenialdan\MagicWE2\Loader;

class UserSession extends Session
{
    /** @var Player|null */
    private $player = null;
    /** @var BossBar */
    private $bossBar;
    /** @var bool */
    private $wandEnabled = true;
    /** @var bool */
    private $debugStickEnabled = true;

    public function __construct(Player $player)
    {
        $this->setPlayer($player);
        $this->setUUID($player->getUniqueId());
        $this->bossBar = (new BossBar())->addPlayer($player);
        $this->bossBar->hideFrom([$player]);
        $this->undoHistory = new \Ds\Deque();
        $this->redoHistory = new \Ds\Deque();
    }

    public function __destruct()
    {
        Loader::getInstance()->getLogger()->debug("Destructing session " . $this->getUUID()->__toString() . " for user " . $this->getPlayer()->getName());
        $this->bossBar->removeAllPlayers();
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
     * @return BossBar
     */
    public function getBossBar(): BossBar
    {
        return $this->bossBar;
    }

    public function __toString()
    {
        return __CLASS__ .
            " UUID: " . $this->getUUID()->__toString() .
            " Player: " . $this->getPlayer()->getName() .
            " Wand tool enabled: " . ($this->isWandEnabled() ? "enabled" : "disabled") .
            " Debug tool enabled: " . ($this->isDebugStickEnabled() ? "enabled" : "disabled") .
            " BossBar: " . $this->getBossBar()->entityId .
            " Selections: " . count($this->getSelections()) .
            " Latest: " . $this->getLatestSelectionUUID() .
            " Clipboards: " . count($this->getClipboards()) .
            " Current: " . $this->getCurrentClipboardIndex() .
            " Undos: " . count($this->undoHistory) .
            " Redos: " . count($this->redoHistory);
    }

    public function sendMessage(string $message)
    {
        $this->player->sendMessage(Loader::PREFIX . $message);
    }
}