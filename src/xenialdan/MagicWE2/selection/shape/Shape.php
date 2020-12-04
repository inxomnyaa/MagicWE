<?php

namespace xenialdan\MagicWE2\selection\shape;

use Exception;
use Generator;
use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use Serializable;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\AsyncChunkManager;

abstract class Shape implements Serializable
{
    /** @var null|Vector3 */
    public $pasteVector;

    public function getPasteVector(): ?Vector3
    {
        return $this->pasteVector;
    }

    public function setPasteVector(Vector3 $pasteVector): void
    {
        $this->pasteVector = $pasteVector->asVector3();
    }

    /**
     * Creates a chunk manager used for async editing
     * @param Chunk[] $chunks
     * @return AsyncChunkManager
     */
    public static function getChunkManager(array $chunks): AsyncChunkManager
    {
        $manager = new AsyncChunkManager();
        foreach ($chunks as $chunk) {
            $manager->setChunk($chunk->getX(), $chunk->getZ(), $chunk);
        }
        return $manager;
    }

    /**
     * @param mixed $manager
     * @throws InvalidArgumentException
     */
    public function validateChunkManager($manager): void
    {
        if (!$manager instanceof World && !$manager instanceof AsyncChunkManager) {
            throw new InvalidArgumentException(get_class($manager) . " is not an instance of World or AsyncChunkManager");
        }
    }

    abstract public function getTotalCount(): int;

    /**
     * Returns the blocks by their actual position
     * @param World|AsyncChunkManager $manager The world or AsyncChunkManager
     * @param Block[] $filterblocks If not empty, applying a filter on the block list
     * @param int $flags
     * @return Generator|Block[]
     * @throws Exception
     */
    abstract public function getBlocks($manager, array $filterblocks = [], int $flags = API::FLAG_BASE): Generator;

    /**
     * Returns a flat layer of all included x z positions in selection
     * @param World|AsyncChunkManager $manager The world or AsyncChunkManager
     * @param int $flags
     * @return Generator|Vector2[]
     * @throws Exception
     */
    abstract public function getLayer($manager, int $flags = API::FLAG_BASE): Generator;

    /**
     * @param ChunkManager $manager
     * @return string[] fastSerialized chunks
     * @throws Exception
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

    abstract public static function getName(): string;

    public function getShapeProperties(): array
    {
        return array_diff(get_object_vars($this), get_class_vars(__CLASS__));
    }

    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize()
    {
        return serialize((array)$this);
    }

    /**
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     * @return void
     * @since 5.1.0
     * @noinspection PhpMissingParamTypeInspection
     */
    public function unserialize($serialized)
    {
        $unserialize = unserialize($serialized/*, ['allowed_classes' => [__CLASS__]]*/);//TODO test pm4
        array_walk($unserialize, function ($value, $key) {
            $this->$key = $value;
        });
    }
}
