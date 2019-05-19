<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\clipboard;

use pocketmine\block\Block;
use pocketmine\level\ChunkManager;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\AsyncChunkManager;

class CopyClipboard extends Clipboard
{
    /** @var Vector3 */
    private $center;
    /** @var AxisAlignedBB */
    private $aabb;
    /** @var Chunk[] */
    public $pasteChunks = [];

    /**
     * RevertClipboard constructor.
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
     * @return AxisAlignedBB
     */
    public function getAxisAlignedBB(): AxisAlignedBB
    {
        return $this->aabb;
    }

    /**
     * @param AxisAlignedBB $aabb
     */
    public function setAxisAlignedBB(AxisAlignedBB $aabb): void
    {
        $this->aabb = $aabb;
    }

    /**
     * @param Vector3 $center
     * @return array of fastSerialized chunks
     */
    public function getTouchedChunks(Vector3 $center): array
    {
        $c = $center->subtract($this->center);//should be 0,0,0
        var_dump("Center c ", $c);
        $offset = new Vector2($c->getX() >> 4, $c->getZ() >> 4);
        var_dump("offset ", $c);
        $chunks = [];
        foreach ($this->chunks as $chunk) {
            print "Touched Chunk at: " . $chunk->getX() . ":" . $chunk->getZ() . PHP_EOL;
            $chunk->setX(intval($chunk->getX() + $offset->x));
            $chunk->setZ(intval($chunk->getZ() + $offset->y));
            print "New Touched Chunk at: " . $chunk->getX() . ":" . $chunk->getZ() . PHP_EOL;
            $chunks[Level::chunkHash($chunk->getX(), $chunk->getZ())] = $chunk->fastSerialize();
        }
        print "Touched chunks count: " . count($chunks) . PHP_EOL;
        return $chunks;
    }

    /**
     * @return array of fastSerialized chunks
     */
    public function getTouchedChunksSerialize(): array
    {
        $chunks = [];
        foreach ($this->chunks as $chunk) {
            #if(is_null($chunk)) continue;
            $chunks[Level::chunkHash($chunk->getX(), $chunk->getZ())] = $chunk->fastSerialize();
        }
        print "Touched chunks serialize count: " . count($chunks) . PHP_EOL;
        return $chunks;
    }

    /**
     * Returns the blocks by their actual position
     * @param Level|AsyncChunkManager|ChunkManager $manager The level or AsyncChunkManager
     * @param int $flags
     * @return \Generator|Block
     * @throws \Exception
     */
    public function getBlocks(ChunkManager $manager, int $flags = API::FLAG_BASE): \Generator
    {
        $this->validateChunkManager($manager);
        var_dump($this->aabb);
        $this->recalculateAABB();
        var_dump($this->aabb);
        //todo should RX RY RZ be center coords?
        for ($x = intval(floor($this->getMinVec3()->x)), $rx = 0; $x <= floor($this->getMaxVec3()->x); $x++, $rx++) {
            for ($y = intval(floor($this->getMinVec3()->y)), $ry = 0; $y <= floor($this->getMaxVec3()->y); $y++, $ry++) {
                for ($z = intval(floor($this->getMinVec3()->z)), $rz = 0; $z <= floor($this->getMaxVec3()->z); $z++, $rz++) {
                    if (API::hasFlag($flags, API::FLAG_POSITION_RELATIVE))//TODO check if correct
                        $vec3 = new Vector3($rx, $ry, $rz);//todo should RX RY RZ be center coords?
                    else
                        $vec3 = new Vector3($x, $y, $z);//This is the old position
                    $block = $manager->getBlockAt($vec3->x, $vec3->y, $vec3->z);
                    $vec3 = $vec3->subtract($this->getMinVec3())->add($this->center->floor())->floor();
                    $block->setComponents($vec3->x, $vec3->y, $vec3->z);
                    #var_dump(__METHOD__ . __LINE__.$vec3);
                    if (API::hasFlag($flags, API::FLAG_KEEP_BLOCKS) && $block->getId() !== Block::AIR) continue;
                    if (API::hasFlag($flags, API::FLAG_KEEP_AIR) && $block->getId() === Block::AIR) continue;

                    /*$block = */
                    #$block->setComponents($vec3->x, $vec3->y, $vec3->z);

                    if ($block->y >= Level::Y_MAX || $block->y < 0) continue;
                    if (API::hasFlag($flags, API::FLAG_HOLLOW) && ($block->x > $this->getMinVec3()->getX() && $block->x < $this->getMaxVec3()->getX()) && ($block->y > $this->getMinVec3()->getY() && $block->y < $this->getMaxVec3()->getY()) && ($block->z > $this->getMinVec3()->getZ() && $block->z < $this->getMaxVec3()->getZ())) continue;
                    yield $block;
                }
            }
        }
    }

    /**
     * @param ChunkManager $manager
     * @throws \Exception
     */
    public function validateChunkManager(ChunkManager $manager): void
    {
        if ($manager instanceof Level) {
            $async = false;//TODO cleanup
        } elseif ($manager instanceof AsyncChunkManager) {
            $async = true;//TODO cleanup
        } else
            throw new \Exception(get_class($manager) . " is not an instance of Level or AsyncChunkManager");
    }

    /**
     * @return Vector3
     */
    public function getMinVec3()
    {
        return new Vector3($this->getAxisAlignedBB()->minX, $this->getAxisAlignedBB()->minY, $this->getAxisAlignedBB()->minZ);
    }

    /**
     * @return Vector3
     */
    public function getMaxVec3()
    {
        return new Vector3($this->getAxisAlignedBB()->maxX, $this->getAxisAlignedBB()->maxY, $this->getAxisAlignedBB()->maxZ);
    }

    /**
     * @return int
     */
    public function getSizeX()
    {
        return abs($this->aabb->minX - $this->aabb->maxX) + 1;
    }

    /**
     * @return int
     */
    public function getSizeY()
    {
        return abs($this->aabb->maxY - $this->aabb->maxY) + 1;
    }

    /**
     * @return int
     */
    public function getSizeZ()
    {
        return abs($this->aabb->maxZ - $this->aabb->maxZ) + 1;
    }

    /**
     * Approximated count of blocks
     * @return int
     */
    public function getTotalCount()
    {
        return $this->getSizeX() * $this->getSizeY() * $this->getSizeZ();
    }

    public function recalculateAABB()
    {
        $this->aabb = new AxisAlignedBB(
            min($this->aabb->minX, $this->aabb->maxX),
            min($this->aabb->minY, $this->aabb->maxY),
            min($this->aabb->minZ, $this->aabb->maxZ),
            max($this->aabb->minX, $this->aabb->maxX),
            max($this->aabb->minY, $this->aabb->maxY),
            max($this->aabb->minZ, $this->aabb->maxZ)
        );
    }

    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize()
    {
        var_dump("Called " . __METHOD__);
        return serialize([
            $this->levelid,
            $this->center->asVector3(),
            $this->aabb,
            $this->getTouchedChunksSerialize(),
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
        var_dump("Called " . __METHOD__);
        [
            $this->levelid,
            $this->center,
            $this->aabb,
            $chunks,
            $this->pasteChunks
        ] = unserialize($serialized);
        foreach ($chunks as $hash => $chunk)//TODO save serialized chunks instead?
            $this->chunks[$hash] = Chunk::fastDeserialize($chunk);
        print "Touched chunks unserialize count: " . count($this->chunks) . PHP_EOL;
    }

    public function __toString()
    {
        return __CLASS__ . " AxisAlignedBB: " . $this->aabb . " Chunk count: " . count($this->chunks);
    }
}