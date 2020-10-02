<?php

namespace xenialdan\MagicWE2\selection\shape;

use Exception;
use Generator;
use pocketmine\block\Block;
use pocketmine\block\BlockLegacyIds;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\AsyncChunkManager;

class Cone extends Shape
{
    /** @var int */
    public $height = 5;
    /** @var int */
    public $diameter = 5;
    /** @var bool */
    public $flipped = false;

    /**
     * Cone constructor.
     * @param Vector3 $pasteVector
     * @param int $height
     * @param int $diameter
     * @param bool $flipped
     */
    public function __construct(Vector3 $pasteVector, int $height, int $diameter, bool $flipped = false)
    {
        $this->pasteVector = $pasteVector;
        $this->height = $height;
        $this->diameter = $diameter;
        $this->flipped = $flipped;
    }

    /**
     * Returns the blocks by their actual position
     * @param World|AsyncChunkManager $manager The level or AsyncChunkManager
     * @param Block[] $filterblocks If not empty, applying a filter on the block list
     * @param int $flags
     * @return Generator|Block[]
     * @throws Exception
     */
    public function getBlocks($manager, array $filterblocks = [], int $flags = API::FLAG_BASE): Generator
    {
        $this->validateChunkManager($manager);
        $reducePerLayer = ($this->diameter / $this->height);
        $centerVec2 = new Vector2($this->getPasteVector()->getX(), $this->getPasteVector()->getZ());
        for ($x = intval(floor($centerVec2->x - $this->diameter / 2 - 1)); $x <= floor($centerVec2->x + $this->diameter / 2 + 1); $x++) {
            for ($y = intval(floor($this->getPasteVector()->y)), $ry = 0; $y < floor($this->getPasteVector()->y + $this->height); $y++, $ry++) {
                for ($z = intval(floor($centerVec2->y - $this->diameter / 2 - 1)); $z <= floor($centerVec2->y + $this->diameter / 2 + 1); $z++) {
                    $vec2 = new Vector2($x, $z);
                    $vec3 = new Vector3($x, $y, $z);
                    if ($this->flipped)
                        $radiusLayer = ($this->diameter - $reducePerLayer * ($this->height - $ry)) / 2;
                    else
                        $radiusLayer = ($this->diameter - $reducePerLayer * $ry) / 2;
                    if ($vec2->distanceSquared($centerVec2) > ($radiusLayer ** 2) || (API::hasFlag($flags, API::FLAG_HOLLOW_CLOSED) && ($ry !== 0 && $ry !== $this->height - 1) && $vec2->distanceSquared($centerVec2) <= ((($this->diameter / 2) - 1) ** 2)) || ((API::hasFlag($flags, API::FLAG_HOLLOW) && $vec2->distanceSquared($centerVec2) <= ((($this->diameter / 2) - 1) ** 2))))
                        continue;
                    $block = $manager->getBlockAt($vec3->getFloorX(), $vec3->getFloorY(), $vec3->getFloorZ())->setComponents($vec3->x, $vec3->y, $vec3->z);
                    if (API::hasFlag($flags, API::FLAG_KEEP_BLOCKS) && $block->getId() !== BlockLegacyIds::AIR) continue;
                    if (API::hasFlag($flags, API::FLAG_KEEP_AIR) && $block->getId() === BlockLegacyIds::AIR) continue;

                    if ($block->y >= World::Y_MAX || $block->y < 0) continue;//TODO fuufufufuuu
                    if (empty($filterblocks)) yield $block;
                    else {
                        foreach ($filterblocks as $filterblock) {
                            if (($block->getId() === $filterblock->getId()) && ((API::hasFlag($flags, API::FLAG_VARIANT) && $block->getVariant() === $filterblock->getVariant()) || (!API::hasFlag($flags, API::FLAG_VARIANT) && ($block->getMeta() === $filterblock->getMeta() || API::hasFlag($flags, API::FLAG_KEEP_META)))))
                                yield $block;
                        }
                    }
                }
            }
        }
    }

    /**
     * Returns a flat layer of all included x z positions in selection
     * @param World|AsyncChunkManager $manager The level or AsyncChunkManager
     * @param int $flags
     * @return Generator|Vector2[]
     * @throws Exception
     */
    public function getLayer($manager, int $flags = API::FLAG_BASE): Generator
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
     * @param World|AsyncChunkManager $manager
     * @return string[] fastSerialized chunks
     * @throws Exception
     */
    public function getTouchedChunks($manager): array
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
                $touchedChunks[World::chunkHash($x, $z)] = $chunk->fastSerialize();
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
        return (int)ceil((pi() * (($this->diameter / 2) ** 2) * $this->height) / 3);
    }

    public static function getName(): string
    {
        return "Cone";
    }
}