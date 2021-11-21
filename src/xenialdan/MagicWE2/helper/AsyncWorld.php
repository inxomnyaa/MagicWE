<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use pocketmine\world\format\Chunk;
use pocketmine\world\SimpleChunkManager;
use pocketmine\world\World;
use xenialdan\MagicWE2\selection\Selection;

class AsyncWorld extends SimpleChunkManager
{
//	/** @var CompoundTag[] *///TODO maybe CacheableNbt
//	protected array $tiles = [];

	public function __construct(Selection $selection)
	{
		parent::__construct(World::Y_MIN, World::Y_MAX);
		$this->copyChunks($selection);
	}

	/**
	 * @return Chunk[]
	 */
	public function getChunks(): array
	{
		return $this->chunks;
	}

	public function copyChunks(Selection $selection): void
	{
		if (!$selection->isValid()) return;
		$this->cleanChunks();

		$shape = $selection->getShape();
		$aabb = $shape->getAABB();
		$world = $selection->getWorld();
		$maxX = $aabb->maxX >> 4;
		$minX = $aabb->minX >> 4;
		$maxZ = $aabb->maxZ >> 4;
		$minZ = $aabb->minZ >> 4;
		for ($x = $minX; $x <= $maxX; $x++) {
			for ($z = $minZ; $z <= $maxZ; $z++) {
				$chunk = $world->getChunk($x, $z);
				if ($chunk === null) {
					continue;
				}
				$this->setChunk($x, $z, $chunk);
//				print __METHOD__ . " Touched Chunk at: $x:$z" . PHP_EOL;
			}
		}
	}

	public function getBlockFullIdAt(int $x, int $y, int $z): int
	{
		if ($this->isInWorld($x, $y, $z) && ($chunk = $this->getChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE)) !== null) {
			return $chunk->getFullBlock($x & Chunk::COORD_MASK, $y, $z & Chunk::COORD_MASK);
		}
		return 0;//TODO idk
	}
}