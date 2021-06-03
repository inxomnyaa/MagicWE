<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\session\data;

use Ds\Map;
use pocketmine\utils\SingletonTrait;
use xenialdan\MagicWE2\helper\BlockPalette;

final class PaletteCollection
{
	use SingletonTrait;

	/** @var Map<string, BlockPalette> */
	public Map $palettes;

	public function __construct()
	{
		$this->palettes = new Map();
		//$this->initFolders();
	}

	/** @return BlockPalette[] */
	public function getPalettes(): array
	{
		return $this->palettes->values()->toArray();
	}

	private function initFolders(): void
	{
	}
}