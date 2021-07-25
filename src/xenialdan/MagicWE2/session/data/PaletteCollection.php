<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\session\data;

use xenialdan\MagicWE2\helper\BlockPalette;
use xenialdan\MagicWE2\session\UserSession;

final class PaletteCollection
{

	/** @var array<string, BlockPalette> */
	public array $palettes;
	private UserSession $session;

	public function __construct(UserSession $session)
	{
		$this->session = $session;
	}

	/**
	 * @return UserSession
	 */
	public function getSession(): UserSession
	{
		return $this->session;
	}

	/** @return BlockPalette[] */
	public function getAll(): array
	{
		return $this->palettes;
	}
}