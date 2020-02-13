<?php

namespace xenialdan\MagicWE2\tool;

use Exception;
use Generator;
use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\AsyncChunkManager;

class Flood extends WETool
{
    /** @var int */
    private $limit = 10000;
    /** @var Block[] */
    private $walked = [];
    /** @var Block[] */
    private $nextToCheck = [];
    /** @var int */
    private $y;

    /**
     * Square constructor.
     * @param int $limit
     */
    public function __construct(int $limit)
    {
        $this->limit = $limit;
    }

    /**
     * Returns the blocks by their actual position
     * @param Level|AsyncChunkManager $manager The level or AsyncChunkManager
     * @param Block[] $filterblocks If not empty, applying a filter on the block list
     * @param int $flags
     * @return Generator|Block[]
     * @throws Exception
     */
    public function getBlocks($manager, array $filterblocks = [], int $flags = API::FLAG_BASE): Generator
    {
        $this->validateChunkManager($manager);
        $this->y = $this->getCenter()->getFloorY();
        $block = $manager->getBlockAt($this->getCenter()->getFloorX(), $this->getCenter()->getFloorY(), $this->getCenter()->getFloorZ());
        $block->setComponents($this->getCenter()->getFloorX(), $this->getCenter()->getFloorY(), $this->getCenter()->getFloorZ());
        $this->walked[] = $block;
        $this->nextToCheck = $this->walked;
        foreach ($this->walk($manager) as $block) {
            yield $block;
        }
    }

    /**
     * Returns a flat layer of all included x z positions in selection
     * @param Level|AsyncChunkManager $manager The level or AsyncChunkManager
     * @param int $flags
     * @return Generator|Vector2[]
     * @throws Exception
     */
    public function getLayer($manager, int $flags = API::FLAG_BASE): Generator
    {
        $this->validateChunkManager($manager);
        foreach ($this->getBlocks($manager, []) as $block) {
            yield new Vector2($block->x, $block->z);
        }
    }

    /**
     * @param Level|AsyncChunkManager $manager
     * @return Block[]
     * @throws InvalidArgumentException
     */
    private function walk($manager): array
    {
        $this->validateChunkManager($manager);
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
     * @param Level|AsyncChunkManager $manager
     * @param Vector3 $vector3
     * @return Generator|Block[]
     * @throws InvalidArgumentException
     */
    private function getHorizontalSides($manager, Vector3 $vector3): Generator
    {
        $this->validateChunkManager($manager);
        foreach ([Vector3::SIDE_NORTH, Vector3::SIDE_SOUTH, Vector3::SIDE_WEST, Vector3::SIDE_EAST] as $vSide) {
            $side = $vector3->getSide($vSide);
            if ($manager->getChunk($side->x >> 4, $side->z >> 4) === null) continue;
            $block = $manager->getBlockAt($side->getFloorX(), $side->getFloorY(), $side->getFloorZ());
            $block->setComponents($side->x, $side->y, $side->z);
            yield $block;
        }
    }

    public function getTotalCount(): int
    {
        return $this->limit;
    }

    /**
     * @param Level|AsyncChunkManager $chunkManager
     * @return array
     * @throws InvalidArgumentException
     */
    public function getTouchedChunks($chunkManager): array
    {
        $this->validateChunkManager($chunkManager);
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
                $chunk = $chunkManager->getChunk($x, $z);
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

    public function getName(): string
    {
        return "Flood Fill";
    }

    /**
     * @param mixed $manager
     * @throws InvalidArgumentException
     */
    public function validateChunkManager($manager): void
    {
        if (!$manager instanceof Level && !$manager instanceof AsyncChunkManager) throw new InvalidArgumentException(get_class($manager) . " is not an instance of Level or AsyncChunkManager");
    }

    private function getCenter(): Vector3
    {
        //UGLY HACK TO IGNORE ERRORS FOR NOW
        return new Vector3();
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
}