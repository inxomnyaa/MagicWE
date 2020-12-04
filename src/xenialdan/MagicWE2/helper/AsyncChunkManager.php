<?php

namespace xenialdan\MagicWE2\helper;

use pocketmine\world\format\Chunk;
use pocketmine\world\SimpleChunkManager;

class AsyncChunkManager extends SimpleChunkManager
{
    public function getBlockArrayAt(int $x, int $y, int $z): array
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
}
