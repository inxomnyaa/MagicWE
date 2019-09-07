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

class Custom extends Shape
{
    /** @var Vector3[] */
    public $positions = [];

    /**
     * Custom constructor.
     * @param Vector3 $pasteVector
     * @param Vector3[] $positions
     */
    public function __construct(Vector3 $pasteVector, array $positions)
    {
        $this->pasteVector = $pasteVector;
        $this->positions = $positions;
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
        foreach ($this->positions as $position) {
            //TODO filterblocks
            yield $manager->getBlockAt($position->x, $position->y, $position->z)->setComponents($position->x, $position->y, $position->z);
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
        /* Mapping: $walked[$hash]=true */
        $walked = [];
        foreach ($this->positions as $position) {
            $hash = Level::chunkHash($position->x, $position->z);
            if (isset($walked[$hash])) continue;
            $walked[$hash] = true;
            yield new Vector2($position->x, $position->z);
        }
    }

    /**
     * @param ChunkManager $manager
     * @return string[] fastSerialized chunks
     * @throws \Exception
     */
    public function getTouchedChunks(ChunkManager $manager): array
    {
        $touchedChunks = [];
        foreach ($this->getLayer($manager) as $vector2) {
            $x = $vector2 >> 4;
            $z = $vector2 >> 4;
            $chunk = $manager->getChunk($x, $z);
            if ($chunk === null) {
                continue;
            }
            print "Touched Chunk at: $x:$z" . PHP_EOL;
            $touchedChunks[Level::chunkHash($x, $z)] = $chunk->fastSerialize();
        }
        print "Touched chunks count: " . count($touchedChunks) . PHP_EOL;
        return $touchedChunks;
    }

    public function getAABB(): AxisAlignedBB
    {
        $minX = $maxX = $minY = $maxY = $minZ = $maxZ = null;
        foreach ($this->positions as $position) {
            if (is_null($minX)) {
                $minX = $maxX = $position->x;
                $minY = $maxY = $position->y;
                $minZ = $maxZ = $position->z;
                continue;
            }
            $minX = min($minX, $position->x);
            $minY = min($minY, $position->y);
            $minZ = min($minZ, $position->z);
            $maxX = max($maxX, $position->x);
            $maxY = max($maxY, $position->y);
            $maxZ = max($maxZ, $position->z);
        }
        return new AxisAlignedBB($minX, $minY, $minZ, $maxX, $maxY, $maxZ);
    }

    public function getTotalCount(): int
    {
        return count($this->positions);
    }

    public static function getName(): string
    {
        return "Custom";
    }
}