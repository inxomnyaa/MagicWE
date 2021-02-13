<?php

namespace xenialdan\MagicWE2\event;

use pocketmine\player\Player;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\session\Session;
use xenialdan\MagicWE2\session\UserSession;

class MWESelectionChangeEvent extends MWEEvent
{
	public const TYPE_PLUGIN = 0;
	public const TYPE_POS1 = 1;
	public const TYPE_POS2 = 2;
	public const TYPE_WORLD = 3;
	public const TYPE_SHAPE = 4;

	private Selection $selection;
	private ?Session $session;
	private int $type;

	public function __construct(Selection $selection, int $type)
	{
		parent::__construct(Loader::getInstance());
		$this->selection = $selection;
		$this->type = $type;
		try {
			$this->session = SessionHelper::getSessionByUUID($selection->sessionUUID);
		} catch (SessionException $e) {
		}
	}

	/**
	 * @return Selection
	 */
	public function getSelection(): Selection
	{
		return $this->selection;
	}

	/**
	 * @param Selection $selection
	 */
	public function setSelection(Selection $selection): void
	{
		$this->selection = $selection;
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
			return $session->getPlayer();
		return null;
	}

	/**
	 * @return int
	 */
	public function getType(): int
	{
		return $this->type;
	}
}