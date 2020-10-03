<?php

namespace xenialdan\MagicWE2\event;

use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\session\Session;
use xenialdan\MagicWE2\session\UserSession;

class MWESelectionChangeEvent extends MWEEvent
{
	/** @var Selection */
	private Selection $oldSelection;
	/** @var Selection */
	private Selection $newSelection;
	/** @var Session|null */
	private ?Session $session;

	public function __construct(Plugin $plugin, Selection $oldSelection, Selection $newSelection, ?Session $session)
	{
		parent::__construct($plugin);
		$this->oldSelection = $oldSelection;
		$this->newSelection = $newSelection;
		$this->session = $session;
	}

	/**
     * @return Selection
     */
    public function getOldSelection(): Selection
    {
        return $this->oldSelection;
    }

    /**
     * @param Selection $oldSelection
     */
    public function setOldSelection(Selection $oldSelection): void
    {
        $this->oldSelection = $oldSelection;
    }

    /**
     * @return Selection
     */
    public function getNewSelection(): Selection
    {
        return $this->newSelection;
    }

    /**
     * @param Selection $newSelection
     */
    public function setNewSelection(Selection $newSelection): void
    {
        $this->newSelection = $newSelection;
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
        if (($session = $this->getSession()) instanceof UserSession)
            /** @var UserSession $session */
            $session->getPlayer();
        return null;
    }

    /**
     * @param null|Player $player
     */
    public function setPlayer(?Player $player): void
    {
        if (($session = $this->getSession()) instanceof UserSession)
            /** @var UserSession $session */
            $session->setPlayer($player);
    }
}