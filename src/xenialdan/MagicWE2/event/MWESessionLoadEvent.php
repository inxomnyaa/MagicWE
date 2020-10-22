<?php

namespace xenialdan\MagicWE2\event;

use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use xenialdan\MagicWE2\session\Session;
use xenialdan\MagicWE2\session\UserSession;

class MWESessionLoadEvent extends MWEEvent
{
	/** @var Session */
	private $session;

	/**
	 * MWESessionLoadEvent constructor.
	 * @param Plugin $plugin
	 * @param Session $session
	 */
	public function __construct(Plugin $plugin, Session $session)
	{
		parent::__construct($plugin);
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
		return $this->session instanceof UserSession ? $this->session->getPlayer() : null;
	}
}