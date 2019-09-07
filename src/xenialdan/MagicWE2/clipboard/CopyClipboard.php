<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\clipboard;

use pocketmine\block\Block;
use pocketmine\level\ChunkManager;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\selection\shape\Shape;

class CopyClipboard extends Clipboard
{
    /** @var Vector3 */
    private $center;
    /** @var Chunk[] */
    public $pasteChunks = [];
    /** @var bool If entities were copied */
    public $entities = false;
    /** @var bool If biomes were copied */
    public $biomes = false;
    /** @var Shape */
    public $shape;

    /**
     * CopyClipboard constructor.
     * @param int $levelId
     * @param Chunk[] $chunks
     */
    public function __construct(int $levelId, array $chunks = [])
    {
        $this->levelid = $levelId;
        $this->chunks = $chunks;
    }

    /**
     * @return Vector3
     */
    public function getCenter(): Vector3
    {
        return $this->center;
    }

    /**
     * @param Vector3 $center
     */
    public function setCenter(Vector3 $center): void
    {
        if ($center instanceof Position && $center->getLevel() !== null) {
            $this->levelid = $center->getLevel()->getId();
        }
        $this->center = $center;
    }

    /**
     * @return Shape
     * @throws \Exception
     */
    public function getShape(): Shape
    {
        if (!$this->shape instanceof Shape) throw new \Exception("Shape is not valid");
        return $this->shape;
    }

    /**
     * @param Shape $shape
     */
    public function setShape(Shape $shape): void
    {
        $this->shape = $shape;
    }

    /**
     * @return AxisAlignedBB
     * @throws \Exception
     * @deprecated
     */
    public function getAxisAlignedBB(): AxisAlignedBB
    {
        return $this->getShape()->getAABB();
    }

    /**
     * @param Vector3 $center
     * @return array of fastSerialized chunks
     * @throws \Exception
     */
    public function getTouchedChunks(Vector3 $center): array
    {
        $shape = $this->getShape();
        $shape->setPasteVector($center);
        return $shape->getTouchedChunks($this->getLevel());
    }

    /**
     * Returns the blocks by their actual position
     * @param Level|AsyncChunkManager|ChunkManager $manager The level or AsyncChunkManager
     * @param int $flags
     * @return \Generator|Block
     * @throws \Exception
     * @deprecated
     */
    public function getBlocks(ChunkManager $manager, int $flags = API::FLAG_BASE): \Generator
    {
        $this->validateChunkManager($manager);
        yield $this->getShape()->getBlocks($manager);
    }

    /**
     * @param ChunkManager $manager
     * @throws \Exception
     */
    public function validateChunkManager(ChunkManager $manager): void
    {
        if (!$manager instanceof Level && !$manager instanceof AsyncChunkManager) throw new \Exception(get_class($manager) . " is not an instance of Level or AsyncChunkManager");
    }

    /**
     * Approximated count of blocks
     * @return int
     * @throws \Exception
     * @deprecated
     */
    public function getTotalCount()
    {
        return $this->getShape()->getTotalCount();
    }

    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize()
    {
        $chunks = [];
        foreach ($this->chunks as $hash => $chunk)
            $chunks[$hash] = $chunk->fastSerialize();
        return serialize([
            $this->levelid,
            $this->center->asVector3(),
            $chunks,
            $this->pasteChunks
        ]);
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
        #var_dump("Called " . __METHOD__);
        [
            $this->levelid,
            $this->center,
            $chunks,
            $this->pasteChunks
        ] = unserialize($serialized);
        foreach ($chunks as $hash => $chunk)//TODO save serialized chunks instead?
            $this->chunks[$hash] = Chunk::fastDeserialize($chunk);
        #print "Touched chunks unserialize count: " . count($this->chunks) . PHP_EOL;
    }

    public function __toString()
    {
        return __CLASS__ . " Chunk count: " . count($this->chunks);
    }
}