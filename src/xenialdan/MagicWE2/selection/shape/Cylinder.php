<?php

namespace xenialdan\MagicWE2\selection\shape;

use pocketmine\block\Block;
use pocketmine\level\ChunkManager;
use pocketmine\level\Level;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\AsyncChunkManager;

class Cylinder extends Shape
{
    public $height;
    public $diameter;

    /**
     * Cylinder constructor.
     * @param Vector3 $pasteVector
     * @param int $height
     * @param int $diameter
     */
    public function __construct(Vector3 $pasteVector, int $height, int $diameter)
    {
        $this->pasteVector = $pasteVector;
        $this->height = $height;
        $this->diameter = $diameter;
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
        $centerVec2 = new Vector2($this->getPasteVector()->getX(), $this->getPasteVector()->getZ());
        for ($x = intval(floor($centerVec2->x - $this->diameter / 2 - 1)); $x <= floor($centerVec2->x + $this->diameter / 2 + 1); $x++) {
            for ($y = intval(floor($this->getPasteVector()->y)), $ry = 0; $y <= floor($this->getPasteVector()->y + $this->height); $y++, $ry++) {
                for ($z = intval(floor($centerVec2->y - $this->diameter / 2 - 1)); $z <= floor($centerVec2->y + $this->diameter / 2 + 1); $z++) {
                    $vec2 = new Vector2($x, $z);
                    $vec3 = new Vector3($x, $y, $z);
                    if ($vec2->distanceSquared($centerVec2) > (($this->diameter / 2) ** 2) || (API::hasFlag($flags, API::FLAG_HOLLOW_CLOSED) && ($ry !== 0 && $ry !== $this->height - 1) && $vec2->distanceSquared($centerVec2) <= ((($this->diameter / 2) - 1) ** 2)) || ((API::hasFlag($flags, API::FLAG_HOLLOW) && $vec2->distanceSquared($centerVec2) <= ((($this->diameter / 2) - 1) ** 2))))
                        continue;
                    $block = $manager->getBlockAt($vec3->x, $vec3->y, $vec3->z)->setComponents($vec3->x, $vec3->y, $vec3->z);
                    if (API::hasFlag($flags, API::FLAG_KEEP_BLOCKS) && $block->getId() !== Block::AIR) continue;
                    if (API::hasFlag($flags, API::FLAG_KEEP_AIR) && $block->getId() === Block::AIR) continue;

                    if ($block->y >= Level::Y_MAX || $block->y < 0) continue;//TODO fuufufufuuu
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
        $centerVec2 = new Vector2($this->getPasteVector()->getX(), $this->getPasteVector()->getZ());
        for ($x = intval(floor($centerVec2->x - $this->diameter / 2 - 1)); $x <= floor($centerVec2->x + $this->diameter / 2 + 1); $x++) {
            for ($z = intval(floor($centerVec2->y - $this->diameter / 2 - 1)); $z <= floor($centerVec2->y + $this->diameter / 2 + 1); $z++) {
                $vec2 = new Vector2($x, $z);
                if ($vec2->distanceSquared($centerVec2) > (($this->diameter / 2) ** 2) || ((API::hasFlag($flags, API::FLAG_HOLLOW) && $vec2->distanceSquared($centerVec2) <= ((($this->diameter / 2) - 1) ** 2))))
                    continue;
                yield $vec2;
            }
        }
    }

    /**
     * @param ChunkManager $manager
     * @return string[] fastSerialized chunks
     * @throws \Exception
     */
    public function getTouchedChunks(ChunkManager $manager): array
    {//TODO optimize to remove "corner" chunks
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
            floor($this->pasteVector->x - $this->diameter / 2),
            $this->pasteVector->y,
            floor($this->pasteVector->z - $this->diameter / 2),
            -1 + floor($this->pasteVector->x - $this->diameter / 2) + $this->diameter,
            -1 + $this->pasteVector->y + $this->height,
            -1 + floor($this->pasteVector->z - $this->diameter / 2) + $this->diameter
        );
    }

    public function getTotalCount(): int
    {
        return ceil((pi() * ($this->diameter ** 2)) / 4) * $this->height;
    }
}