<?php

namespace xenialdan\MagicWE2\selection\shape;

use pocketmine\block\Block;
use pocketmine\level\ChunkManager;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\AsyncChunkManager;

abstract class Shape
{
    /** @var null|Vector3 */
    public $pasteVector = null;

    public function getPasteVector(): ?Vector3
    {
        return $this->pasteVector;
    }

    public function setPasteVector(Vector3 $pasteVector)
    {
        $this->pasteVector = $pasteVector;
    }

    /**
     * Creates a chunk manager used for async editing
     * @param Chunk[] $chunks
     * @return AsyncChunkManager
     */
    public static function getChunkManager(array $chunks): AsyncChunkManager
    {
        $manager = new AsyncChunkManager(0);
        foreach ($chunks as $chunk) {
            $manager->setChunk($chunk->getX(), $chunk->getZ(), $chunk);
        }
        return $manager;
    }

    /**
     * @param ChunkManager $manager
     * @throws \Exception
     */
    public function validateChunkManager(ChunkManager $manager): void
    {
        if (!$manager instanceof Level && !$manager instanceof AsyncChunkManager) throw new \Exception(get_class($manager) . " is not an instance of Level or AsyncChunkManager");
    }

    abstract public function getTotalCount(): int;

    /**
     * Returns the blocks by their actual position
     * @param Level|AsyncChunkManager|ChunkManager $manager The level or AsyncChunkManager
     * @param Block[] $filterblocks If not empty, applying a filter on the block list
     * @param int $flags
     * @return \Generator|Block[]
     * @throws \Exception
     */
    abstract public function getBlocks(ChunkManager $manager, array $filterblocks = [], int $flags = API::FLAG_BASE): \Generator;

    /**
     * Returns a flat layer of all included x z positions in selection
     * @param Level|AsyncChunkManager|ChunkManager $manager The level or AsyncChunkManager
     * @param int $flags
     * @return \Generator|Vector2[]
     * @throws \Exception
     */
    abstract public function getLayer(ChunkManager $manager, int $flags = API::FLAG_BASE): \Generator;

    /**
     * @param ChunkManager $manager
     * @return string[] fastSerialized chunks
     * @throws \Exception
     */
    abstract public function getTouchedChunks(ChunkManager $manager): array;

    abstract public function getAABB(): AxisAlignedBB;

    /**
     * @return Vector3
     */
    public function getMinVec3(): Vector3
    {
        return new Vector3($this->getAABB()->minX, $this->getAABB()->minY, $this->getAABB()->minZ);
    }

    /**
     * @return Vector3
     */
    public function getMaxVec3(): Vector3
    {
        return new Vector3($this->getAABB()->maxX, $this->getAABB()->maxY, $this->getAABB()->maxZ);
    }

    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize()
    {
        return serialize($this->pasteVector);
    }

    /**
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     * @return void
     * @since 5.1.0
     */
    public function unserialize($serialized)
    {
        unserialize($serialized);
    }
}