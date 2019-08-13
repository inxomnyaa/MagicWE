<?php

namespace xenialdan\MagicWE2\event;

use pocketmine\block\Block;
use pocketmine\event\Cancellable;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use xenialdan\MagicWE2\session\Session;
use xenialdan\MagicWE2\session\UserSession;

class MWEEditEvent extends MWEEvent implements Cancellable
{

    /** @var Block[] */
    private $oldBlocks = [];
    /** @var Block[] */
    private $newBlocks = [];
    /** @var null|Session */
    private $session;

    public function __construct(Plugin $plugin, $oldBlocks, $newBlocks, ?Session $session)
    {
        parent::__construct($plugin);
        $this->oldBlocks = $oldBlocks;
        $this->newBlocks = $newBlocks;
        $this->session = $session;
    }

    /**
     * @return null|Session
     */
    public function getSession(): ?Session
    {
        return $this->session;
    }

    /**
     * @return null|Player
     */
    public function getPlayer(): ?Player
    {
        /** @var UserSession $session */
        if (($session = $this->getSession()) instanceof UserSession) $session->getPlayer();
        return null;
    }

    /**
     * @param null|Player $player
     */
    public function setPlayer(?Player $player)
    {
        /** @var UserSession $session */
        if (($session = $this->getSession()) instanceof UserSession) $session->setPlayer($player);
    }

    /**
     * @return Block[]
     */
    public function getOldBlocks(): array
    {
        return $this->oldBlocks;
    }

    /**
     * @return Block[]
     */
    public function getNewBlocks(): array
    {
        return $this->newBlocks;
    }

    /**
     * @param Block[] $newBlocks
     */
    public function setNewBlocks(array $newBlocks): void
    {
        $this->newBlocks = $newBlocks;
    }
}