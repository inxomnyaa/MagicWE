<?php

namespace xenialdan\MagicWE2\selection\shape;

use pocketmine\block\Block;
use pocketmine\level\ChunkManager;
use pocketmine\level\Level;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector2;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\AsyncChunkManager;

class Cube extends Shape
{
    public $width;

    public function __construct(int $width)
    {
        $this->width = $width;
    }

    /**
     * Returns the blocks by their actual position
     * @param Level|AsyncChunkManager|ChunkManager $manager The level or AsyncChunkManager
     * @param Block[] $filterblocks If not empty, applying a filter on the block list
     * @param int $flags
     * @return \Generator|Block[]
     * @throws \Exception
     */
    public function getBlocks(ChunkManager $manager, array $filterblocks = [], int $flags = API::FLAG_BASE): \Generator
    {
        $this->validateChunkManager($manager);
        for ($x = intval(floor($this->getMinVec3()->x)), $rx = 0; $x <= floor($this->getMaxVec3()->x); $x++, $rx++) {
            for ($y = intval(floor($this->getMinVec3()->y)), $ry = 0; $y <= floor($this->getMaxVec3()->y); $y++, $ry++) {
                for ($z = intval(floor($this->getMinVec3()->z)), $rz = 0; $z <= floor($this->getMaxVec3()->z); $z++, $rz++) {
                    $block = $manager->getBlockAt($x, $y, $z)->setComponents($x, $y, $z);
                    if (API::hasFlag($flags, API::FLAG_KEEP_BLOCKS) && $block->getId() !== Block::AIR) continue;
                    if (API::hasFlag($flags, API::FLAG_KEEP_AIR) && $block->getId() === Block::AIR) continue;

                    if ($block->y >= Level::Y_MAX || $block->y < 0) continue;//TODO check for removal because relative might be at other y
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
     * @return \Generator|Vector2[]
     * @throws \Exception
     */
    public function getLayer(ChunkManager $manager, int $flags = API::FLAG_BASE): \Generator
    {
        $this->validateChunkManager($manager);
        for ($x = intval(floor($this->getMinVec3()->x)); $x <= floor($this->getMaxVec3()->x); $x++) {
            for ($z = intval(floor($this->getMinVec3()->z)); $z <= floor($this->getMaxVec3()->z); $z++) {
                yield new Vector2($x, $z);
            }
        }
    }

    /**
     * @param ChunkManager $manager
     * @return string[] fastSerialized chunks
     * @throws \Exception
     */
    public function getTouchedChunks(ChunkManager $manager): array
    {
        $this->validateChunkManager($manager);
        $maxX = $this->getMaxVec3()->x >> 4;
        $minX = $this->getMinVec3()->x >> 4;
        $maxZ = $this->getMaxVec3()->z >> 4;
        $minZ = $this->getMinVec3()->z >> 4;
        $touchedChunks = [];
        for ($x = $minX; $x <= $maxX; $x++) {
            for ($z = $minZ; $z <= $maxZ; $z++) {
                $chunk = $manager->getChunk($x, $z);
                if ($chunk === null) {
                    continue;
                }
                print "Touched Chunk at: $x:$z" . PHP_EOL;
                $touchedChunks[Level::chunkHash($x, $z)] = $chunk->fastSerialize();
            }
        }
        print "Touched chunks count: " . count($touchedChunks) . PHP_EOL;
        return $touchedChunks;
    }

    public function getAABB(): AxisAlignedBB
    {
        return new AxisAlignedBB(
            $this->pasteVector->x - floor($this->width / 2),
            $this->pasteVector->y,
            $this->pasteVector->z - floor($this->width / 2),
            $this->pasteVector->x + ceil($this->width / 2),
            $this->pasteVector->y + $this->width,
            $this->pasteVector->z + ceil($this->width / 2)
        );
    }

    public function getTotalCount(): int
    {
        return $this->width ** 3;
    }
}