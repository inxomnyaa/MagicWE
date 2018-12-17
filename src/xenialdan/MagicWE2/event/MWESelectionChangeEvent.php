<?php

namespace xenialdan\MagicWE2\event;

use pocketmine\Player;
use pocketmine\plugin\Plugin;
use xenialdan\MagicWE2\Selection;
use xenialdan\MagicWE2\Session;

class MWESelectionChangeEvent extends MWEEvent {
	private $oldSelection;
	private $newSelection;
	private $session;

	public function __construct(Plugin $plugin, Selection $oldSelection, Selection $newSelection, ?Session $session) {
		parent::__construct($plugin);
		$this->oldSelection = $oldSelection;
		$this->newSelection = $newSelection;
		$this->session = $session;
	}

	/**
	 * @return Selection
	 */
	public function getOldSelection(): Selection {
		return $this->oldSelection;
	}

	/**
	 * @param Selection $oldSelection
	 */
	public function setOldSelection(Selection $oldSelection): void {
		$this->oldSelection = $oldSelection;
	}

	/**
	 * @return Selection
	 */
	public function getNewSelection(): Selection {
		return $this->newSelection;
	}

	/**
	 * @param Selection $newSelection
	 */
	public function setNewSelection(Selection $newSelection): void {
		$this->newSelection = $newSelection;
	}

	/**
	 * @return null|Session
	 */
	public function getSession(): ?Session {
		return $this->session;
	}

	/**
	 * @return null|Player
	 */
	public function getPlayer(): ?Player {
		return $this->getSession()->getPlayer();
	}

	/**
	 * @param null|Player $player
	 */
	public function setPlayer(?Player $player) {
		$this->getSession()->setPlayer($player);
	}
}