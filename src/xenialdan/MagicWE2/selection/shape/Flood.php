<?php

namespace xenialdan\MagicWE2\selection\shape;

use pocketmine\block\Block;
use pocketmine\level\ChunkManager;
use pocketmine\level\Level;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\AsyncChunkManager;

class Flood extends Shape
{
    private $limit = 10000;
    /** @var Block[] */
    private $walked = [];
    /** @var Block[] */
    private $nextToCheck = [];
    /** @var int */
    private $y;

    /**
     * Square constructor.
     * @param Level $level
     * @param array $options
     */
    public function __construct(Level $level, array $options)
    {
        parent::__construct($level, $options);
        $this->limit = $options["limit"] ?? $this->limit;
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
        $this->y = $this->getCenter()->getY();
        $block = $manager->getBlockAt($this->getCenter()->x, $this->getCenter()->y, $this->getCenter()->z);
        $block->setComponents($this->getCenter()->x, $this->getCenter()->y, $this->getCenter()->z);
        $this->walked[] = $block;
        $this->nextToCheck = $this->walked;
        foreach ($this->walk($manager) as $block) {
            yield $block;
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
        foreach ($this->getBlocks($manager, []) as $block) {
            yield new Vector2($block->x, $block->z);
        }
    }

    /**
     * @param Level|AsyncChunkManager|ChunkManager $manager
     * @return Block[]
     */
    private function walk(ChunkManager $manager): array
    {
        /** @var Block[] $walkTo */
        $walkTo = [];
        foreach ($this->nextToCheck as $next) {
            $sides = iterator_to_array($this->getHorizontalSides($manager, $next));
            $walkTo = array_merge($walkTo, array_filter($sides, function (Block $side) use ($walkTo) {
                return $side->getId() === 0 && !in_array($side, $walkTo) && !in_array($side, $this->walked) && !in_array($side, $this->nextToCheck) && $side->distanceSquared($this->getCenter()) <= ($this->limit / pi());
            }));
        }
        $this->walked = array_merge($this->walked, $walkTo);
        $this->nextToCheck = $walkTo;
        if (!empty($this->nextToCheck)) $this->walk($manager);
        return $this->walked;
    }

    /**
     * @param Level|AsyncChunkManager|ChunkManager $manager
     * @param Vector3 $vector3
     * @return \Generator|Block
     */
    private function getHorizontalSides(ChunkManager $manager, Vector3 $vector3): \Generator
    {
        foreach ([Vector3::SIDE_NORTH, Vector3::SIDE_SOUTH, Vector3::SIDE_WEST, Vector3::SIDE_EAST] as $vSide) {
            $side = $vector3->getSide($vSide);
            if ($manager->getChunk($side->x >> 4, $side->z >> 4) === null) continue;//TODO check if continue or stop walking instead
            $block = $manager->getBlockAt($side->x, $side->y, $side->z);
            $block->setComponents($side->x, $side->y, $side->z);
            yield $block;
        }
    }

    public function getTotalCount()
    {
        return $this->limit;
    }

    public function getTouchedChunks(): array
    {
        $maxRadius = sqrt($this->limit / pi());
        $v2center = new Vector2($this->getCenter()->x, $this->getCenter()->z);
        $cv2center = new Vector2($this->getCenter()->x >> 4, $this->getCenter()->z >> 4);
        $maxX = ($v2center->x + $maxRadius) >> 4;
        $minX = ($v2center->x - $maxRadius) >> 4;
        $maxZ = ($v2center->y + $maxRadius) >> 4;
        $minZ = ($v2center->y - $maxRadius) >> 4;
        $cmaxRadius = $cv2center->distanceSquared($minX - 0.5, $minZ - 0.5);
        #print "from $minX:$minZ to $maxX:$maxZ" . PHP_EOL;
        $touchedChunks = [];
        for ($x = $minX - 1; $x <= $maxX + 1; $x++) {
            for ($z = $minZ - 1; $z <= $maxZ + 1; $z++) {
                if ($cv2center->distanceSquared($x, $z) > $cmaxRadius) continue;
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
}