<?php

namespace xenialdan\MagicWE2\helper;

use pocketmine\world\format\Chunk;
use pocketmine\world\SimpleChunkManager;
use pocketmine\world\World;

class AsyncChunkManager extends SimpleChunkManager
{

	public function getBlockArrayAt(int $x, int $y, int $z): array//TODO replace with getFullBlock
	{
		return [$this->getBlockAt($x, $y, $z)->getId(), $this->getBlockAt($x, $y, $z)->getMeta()];
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