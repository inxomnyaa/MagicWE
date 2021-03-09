<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\session\data;

use xenialdan\libstructure\tile\StructureBlockTile;

class Outline
{
	public ?Asset $asset = null;
	public ?StructureBlockTile $tile = null;

	/**
	 * Asset constructor.
	 * @param Asset|null $asset
	 */
	public function __construct(?Asset $asset = null)
	{
		$this->asset = $asset;
	}

	public function __toString(): string
	{
		return 'Outline';
	}

	/**
	 * @return Asset|null
	 */
	public function getAsset(): ?Asset
	{
		return $this->asset;
	}

	/**
	 * @param Asset|null $asset
	 * @return Outline
	 */
	public function setAsset(?Asset $asset): Outline
	{
		$this->asset = $asset;
		return $this;
	}
}