<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\session\data;

use pocketmine\utils\SingletonTrait;
use xenialdan\MagicWE2\helper\BlockPalette;

final class PaletteCollection
{
	use SingletonTrait;

	/** @var array<string, BlockPalette> */
	public array $palettes;

	public function __construct()
	{
		//$this->initFolders();
	}

	/** @return BlockPalette[] */
	public function getPalettes(): array
	{
		return $this->palettes;
	}

	private function initFolders(): void
	{
	}
}