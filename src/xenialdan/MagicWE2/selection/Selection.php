<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\selection;

use pocketmine\block\Block;
use pocketmine\level\ChunkManager;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\UUID;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\Loader;

/**
 * Class Selection
 * @package xenialdan\MagicWE2
 */
class Selection implements \Serializable
{
    /** @var int */
    public $levelid;
    /** @var Vector3 */
    public $pos1;
    /** @var Vector3 */
    public $pos2;
    /** @var UUID */
    public $uuid;
    /** @var AxisAlignedBB */
    public $aabb;

    /**
     * Selection constructor.
     * @param Level $level
     * @param ?int $minX
     * @param ?int $minY
     * @param ?int $minZ
     * @param ?int $maxX
     * @param ?int $maxY
     * @param ?int $maxZ
     */
    public function __construct(Level $level, $minX = null, $minY = null, $minZ = null, $maxX = null, $maxY = null, $maxZ = null)
    {
        $this->setLevel($level);
        if (isset($minX) && isset($minY) && isset($minZ)) {
            $this->pos1 = new Vector3($minX, $minY, $minZ);
        }
        if (isset($maxX) && isset($maxY) && isset($maxZ)) {
            $this->pos2 = new Vector3($maxX, $maxY, $maxZ);
        }
        $this->setUUID(UUID::fromRandom());
    }

    /**
     * @return AxisAlignedBB
     */
    public function getAxisAlignedBB(): AxisAlignedBB
    {
        if ($this->aabb === null) {
            $this->recalculateAABB();
        }
        return $this->aabb;
    }

    public function recalculateAABB()
    {
        if ($this->isValid())
            $this->aabb = new AxisAlignedBB(
                min($this->pos1->x, $this->pos2->x),
                min($this->pos1->y, $this->pos2->y),
                min($this->pos1->z, $this->pos2->z),
                max($this->pos1->x, $this->pos2->x),
                max($this->pos1->y, $this->pos2->y),
                max($this->pos1->z, $this->pos2->z)
            );
    }

    /**
     * @return Level
     * @throws \Exception
     */
    public function getLevel()
    {
        if (is_null($this->levelid)) {
            throw new \Exception("Level is not set!");
        }
        $level = Server::getInstance()->getLevel($this->levelid);
        if (is_null($level)) {
            throw new \Exception("Level is not found!");
        }
        return $level;
    }

    /**
     * @param Level $level
     */
    public function setLevel(Level $level)
    {
        $this->levelid = $level->getId();
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
     * @return Position
     * @throws \Exception
     */
    public function getPos1()
    {
        if (is_null($this->pos1)) {
            throw new \Exception("Position 1 is not set!");
        }
        return Position::fromObject($this->pos1, $this->getLevel());
    }

    /**
     * @param Position $position
     * @return string
     */
    public function setPos1(Position $position)
    {
        $this->pos1 = $position->asVector3()->floor();
        if ($this->pos1->y >= Level::Y_MAX) $this->pos1->y = Level::Y_MAX;
        if ($this->pos1->y < 0) $this->pos1->y = 0;
        if ($this->levelid !== $position->getLevel()->getId()) {//reset other position if in different level
            $this->pos2 = null;
        }
        $this->setLevel($position->getLevel());
        return Loader::PREFIX . TF::GREEN . "Position 1 set to X: " . $this->pos1->getX() . " Y: " . $this->pos1->getY() . " Z: " . $this->pos1->getZ();
    }

    /**
     * @return Position
     * @throws \Exception
     */
    public function getPos2()
    {
        if (is_null($this->pos2)) {
            throw new \Exception("Position 2 is not set!");
        }
        return Position::fromObject($this->pos2, $this->getLevel());
    }

    /**
     * @param Position $position
     * @return string
     */
    public function setPos2(Position $position)
    {
        $this->pos2 = $position->asVector3()->floor();
        if ($this->pos2->y >= Level::Y_MAX) $this->pos2->y = Level::Y_MAX;
        if ($this->pos2->y < 0) $this->pos2->y = 0;
        if ($this->levelid !== $position->getLevel()->getId()) {
            $this->pos1 = null;
        }
        $this->setLevel($position->getLevel());
        return Loader::PREFIX . TF::GREEN . "Position 2 set to X: " . $this->pos2->getX() . " Y: " . $this->pos2->getY() . " Z: " . $this->pos2->getZ();
    }

    /**
     * Checks if a Selection is valid. It is not valid if:
     * - The level is not set
     * - Any of the positions are not set
     * @return bool
     */
    public function isValid(): bool
    {
        try {
            $this->getLevel();
            $this->getPos1();
            $this->getPos2();
        } catch (\Exception $e) {
            return false;
        } finally {
            return true;
        }
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
        return abs($this->pos1->x - $this->pos2->x) + 1;
    }

    /**
     * @return int
     */
    public function getSizeY()
    {
        return abs($this->pos1->y - $this->pos2->y) + 1;
    }

    /**
     * @return int
     */
    public function getSizeZ()
    {
        return abs($this->pos1->z - $this->pos2->z) + 1;
    }

    /**
     * @return int
     */
    public function getTotalCount()
    {
        return $this->getSizeX() * $this->getSizeY() * $this->getSizeZ();//TODO correct number on custom selection shapes
    }

    /**
     * Returns the blocks by their actual position
     * @param Level|AsyncChunkManager|ChunkManager $manager The level or AsyncChunkManager
     * @param Block[] $filterblocks If not empty, applying a filter on the block list
     * @param int $flags
     * @return \Generator|Block
     * @throws \Exception
     */
    public function getBlocks(ChunkManager $manager, array $filterblocks = [], int $flags = API::FLAG_BASE): \Generator
    {
        $this->validateChunkManager($manager);
        for ($x = intval(floor($this->getMinVec3()->x)), $rx = 0; $x <= floor($this->getMaxVec3()->x); $x++, $rx++) {
            for ($y = intval(floor($this->getMinVec3()->y)), $ry = 0; $y <= floor($this->getMaxVec3()->y); $y++, $ry++) {
                for ($z = intval(floor($this->getMinVec3()->z)), $rz = 0; $z <= floor($this->getMaxVec3()->z); $z++, $rz++) {
                    if (API::hasFlag($flags, API::FLAG_POSITION_RELATIVE))//TODO check if correct
                        $vec3 = new Vector3($rx, $ry, $rz);
                    else
                        $vec3 = new Vector3($x, $y, $z);
                    $block = $manager->getBlockAt($vec3->x, $vec3->y, $vec3->z);
                    if (API::hasFlag($flags, API::FLAG_KEEP_BLOCKS) && $block->getId() !== Block::AIR) continue;
                    if (API::hasFlag($flags, API::FLAG_KEEP_AIR) && $block->getId() === Block::AIR) continue;

                    /*$block = */
                    $block->setComponents($vec3->x, $vec3->y, $vec3->z);

                    if ($block->y >= Level::Y_MAX || $block->y < 0) continue;
                    if (API::hasFlag($flags, API::FLAG_HOLLOW) && ($block->x > $this->getMinVec3()->getX() && $block->x < $this->getMaxVec3()->getX()) && ($block->y > $this->getMinVec3()->getY() && $block->y < $this->getMaxVec3()->getY()) && ($block->z > $this->getMinVec3()->getZ() && $block->z < $this->getMaxVec3()->getZ())) continue;
                    if (empty($filterblocks)) yield $block;
                    else {
                        foreach ($filterblocks as $filterblock) {
                            if (($block->getId() === $filterblock->getId()) && ((API::hasFlag($flags, API::FLAG_VARIANT) && $block->getVariant() === $filterblock->getVariant()) || (!API::hasFlag($flags, API::FLAG_VARIANT) && ($block->getDamage() === $filterblock->getDamage() || API::hasFlag($flags, API::FLAG_KEEP_META)))))
                                yield $block;
                        }
                    }
                }
            }
        }
    }

    /**
     * Returns a flat layer of all included x z positions in selection
     * @param Level|AsyncChunkManager|ChunkManager $manager The level or AsyncChunkManager
     * @param int $flags
     * @return \Generator|Vector2
     * @throws \Exception
     */
    public function getLayer(ChunkManager $manager, int $flags = API::FLAG_BASE): \Generator
    {
        $this->validateChunkManager($manager);
        for ($x = intval(floor($this->getMinVec3()->x)), $rx = 0; $x <= floor($this->getMaxVec3()->x); $x++, $rx++) {
            for ($z = intval(floor($this->getMinVec3()->z)), $rz = 0; $z <= floor($this->getMaxVec3()->z); $z++, $rz++) {
                if (API::hasFlag($flags, API::FLAG_POSITION_RELATIVE))//TODO check if correct
                    $vec2 = new Vector2($rx, $rz);
                else
                    $vec2 = new Vector2($x, $z);
                yield $vec2;
            }
        }
    }

    /**
     * Returns a flat layer of all included x z positions in selection
     * @param Level|AsyncChunkManager|ChunkManager $manager The level or AsyncChunkManager
     * @param Block[] $filterblocks If not empty, applying a filter on the block list
     * @param int $flags
     * @return \Generator|Block
     * @throws \Exception
     */
    public function getHighestBlockLayer(ChunkManager $manager, array $filterblocks = [], int $flags = API::FLAG_BASE): \Generator
    {
        //TODO rewrite function, right now this is total crap and inefficient, better iterate from top down
        $this->validateChunkManager($manager);
        /** @var Vector2 $vector2 */
        foreach ($this->getLayer($manager, $flags) as $vector2) {
            $highestBlock = $this->getHighestBlock($vector2, $manager, $filterblocks, $flags);
            $highestBlock = max(floor($this->getMaxVec3()->y), $highestBlock);
            yield $manager->getBlock(new Vector3($vector2->x, $highestBlock, $vector2->y));
        }
    }

    public function getHighestBlock(Vector2 $vector2, ChunkManager $manager, array $filterblocks = [], int $flags = API::FLAG_BASE)
    {
        $chunk = $manager->getChunk($vector2->x >> 4, $vector2->y >> 4);
        if (is_null($chunk)) return -1;
        return $chunk->getHighestBlockAt($vector2->x & 0x0f, $vector2->y & 0x0f) + 1;
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
     * @return array fastSerialized chunks
     * @throws \Exception
     */
    public function getTouchedChunks(): array
    {
        $this->recalculateAABB();
        $maxX = $this->getMaxVec3()->x >> 4;
        $minX = $this->getMinVec3()->x >> 4;
        $maxZ = $this->getMaxVec3()->z >> 4;
        $minZ = $this->getMinVec3()->z >> 4;
        #print "from $minX:$minZ to $maxX:$maxZ" . PHP_EOL;
        $touchedChunks = [];
        for ($x = $minX; $x <= $maxX; $x++) {
            for ($z = $minZ; $z <= $maxZ; $z++) {
                $chunk = $this->getLevel()->getChunk($x, $z, true);
                if ($chunk === null) {
                    continue;
                }
                #print "Touched Chunk at: $x:$z" . PHP_EOL;
                $touchedChunks[Level::chunkHash($x, $z)] = $chunk->fastSerialize();
            }
        }
        #print "Touched chunks count: " . count($touchedChunks) . PHP_EOL;;
        return $touchedChunks;
    }

    /**
     * @param UUID $uuid
     */
    public function setUUID(UUID $uuid)
    {
        $this->uuid = $uuid;
    }

    /**
     * @return UUID
     */
    public function getUUID()
    {
        return $this->uuid;
    }

    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize()
    {
        return serialize([
            $this->levelid,
            $this->pos1,
            $this->pos2,
            $this->uuid
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
        /** @var Vector3 $pos1 , $pos2 */
        [
            $this->levelid,
            $this->pos1,
            $this->pos2,
            $this->uuid
        ] = unserialize($serialized);
    }
}