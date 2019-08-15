<?php

namespace xenialdan\MagicWE2\helper;

use pocketmine\block\Block;
use pocketmine\level\format\Chunk;
use pocketmine\level\SimpleChunkManager;

class AsyncChunkManager extends SimpleChunkManager
{
    public function getBlockAt(int $x, int $y, int $z): Block
    {
        return Block::get($this->getBlockIdAt($x, $y, $z), $this->getBlockDataAt($x, $y, $z));
    }

    public function getBlockArrayAt(int $x, int $y, int $z): array
    {
        return [$this->getBlockIdAt($x, $y, $z), $this->getBlockDataAt($x, $y, $z)];
    }

    public function setBlockAt(int $x, int $y, int $z, Block $block): void
    {
        $this->setBlockIdAt($x, $y, $z, $block->getId());
        $this->setBlockDataAt($x, $y, $z, $block->getDamage());
    }

    /**
     * @return Chunk[]
     */
    public function getChunks(): array
    {
        return $this->chunks;
    }

    /**
     * @param int $index
     * @return null|Chunk
     */
    public function getChunkFromIndex(int $index): ?Chunk
    {
        return $this->chunks[$index] ?? null;
    }
}