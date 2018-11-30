<?php

namespace xenialdan\MagicWE2\event;

use pocketmine\block\Block;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use xenialdan\MagicWE2\Session;

class MWEEditEvent extends MWEEvent
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
        return $this->getSession()->getPlayer();
    }

    /**
     * @param null|Player $player
     */
    public function setPlayer(?Player $player)
    {
        $this->getSession()->setPlayer($player);
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