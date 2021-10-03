<?php

namespace xenialdan\MagicWE2\helper;

use pocketmine\world\format\Chunk;
use pocketmine\world\SimpleChunkManager;
use pocketmine\world\World;

class AsyncChunkManager extends SimpleChunkManager
{

	public function getBlockFullIdAt(int $x, int $y, int $z): int
	{
		/** @noinspection PhpInternalEntityUsedInspection */
		return $this->getBlockAt($x, $y, $z)->getFullId();
	}

	/**
	 * @return Chunk[]
	 */
	public function getChunks(): array
	{
		return $this->chunks;
	}

	public function getWorldHeight(): int
	{
		return World::Y_MAX;
	}
}