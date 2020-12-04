<?php

namespace xenialdan\MagicWE2\event;

use pocketmine\player\Player;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\session\Session;
use xenialdan\MagicWE2\session\UserSession;

class MWESessionSettingChangeEvent extends MWEEvent
{
	public const TYPE_PLUGIN = 0;

	private ?Session $session;
	private int $type;

	public function __construct(?Session $session, int $type = self::TYPE_PLUGIN)
	{
		parent::__construct(Loader::getInstance());
		$this->session = $session;
		$this->type = $type;//TODO use
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