<?php

namespace xenialdan\MagicWE2\selection\shape;

use Exception;
use Generator;
use pocketmine\block\Block;
use pocketmine\block\BlockLegacyIds;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\World;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\AsyncChunkManager;

class Cube extends Shape
{
	/** @var int */
	public int $width = 5;

	public function __construct(Vector3 $pasteVector, int $width)
	{
		$this->pasteVector = $pasteVector;
		$this->width = $width;
	}

	/**
	 * Returns the blocks by their actual position
	 * @param World|AsyncChunkManager $manager The world or AsyncChunkManager
	 * @param Block[] $filterblocks If not empty, applying a filter on the block list
	 * @param int $flags
	 * @return Generator|Block[]
	 * @throws Exception
	 */
    public function getBlocks($manager, array $filterblocks = [], int $flags = API::FLAG_BASE): Generator
    {
        $this->validateChunkManager($manager);
        for ($x = intval(floor($this->getMinVec3()->x)), $rx = 0; $x <= floor($this->getMaxVec3()->x); $x++, $rx++) {
            for ($y = intval(floor($this->getMinVec3()->y)), $ry = 0; $y <= floor($this->getMaxVec3()->y); $y++, $ry++) {
                for ($z = intval(floor($this->getMinVec3()->z)), $rz = 0; $z <= floor($this->getMaxVec3()->z); $z++, $rz++) {
					$block = $manager->getBlockAt($x, $y, $z)/*->setComponents($x, $y, $z)*/
					;
                    if (API::hasFlag($flags, API::FLAG_KEEP_BLOCKS) && $block->getId() !== BlockLegacyIds::AIR) continue;
                    if (API::hasFlag($flags, API::FLAG_KEEP_AIR) && $block->getId() === BlockLegacyIds::AIR) continue;

                    if ($block->y >= World::Y_MAX || $block->y < 0) continue;//TODO check for removal because relative might be at other y
                    if (API::hasFlag($flags, API::FLAG_HOLLOW) && ($block->x > $this->getMinVec3()->getX() && $block->x < $this->getMaxVec3()->getX()) && ($block->y > $this->getMinVec3()->getY() && $block->y < $this->getMaxVec3()->getY()) && ($block->z > $this->getMinVec3()->getZ() && $block->z < $this->getMaxVec3()->getZ())) continue;
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
	 * @param World|AsyncChunkManager $manager The world or AsyncChunkManager
	 * @param int $flags
	 * @return Generator|Vector2[]
	 * @throws Exception
	 */
    public function getLayer($manager, int $flags = API::FLAG_BASE): Generator
    {
        $this->validateChunkManager($manager);
        for ($x = intval(floor($this->getMinVec3()->x)); $x <= floor($this->getMaxVec3()->x); $x++) {
            for ($z = intval(floor($this->getMinVec3()->z)); $z <= floor($this->getMaxVec3()->z); $z++) {
                yield new Vector2($x, $z);
            }
        }
    }

    /**
     * @param World|AsyncChunkManager $manager
     * @return string[] fastSerialized chunks
     * @throws Exception
     */
    public function getTouchedChunks($manager): array
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
				$touchedChunks[World::chunkHash($x, $z)] = FastChunkSerializer::serialize($chunk);
            }
        }
        print "Touched chunks count: " . count($touchedChunks) . PHP_EOL;
        return $touchedChunks;
    }

    public function getAABB(): AxisAlignedBB
    {
        return new AxisAlignedBB(
            ceil($this->pasteVector->x - $this->width / 2),
            $this->pasteVector->y,
            ceil($this->pasteVector->z - $this->width / 2),
            -1 + ceil($this->pasteVector->x - $this->width / 2) + $this->width,
            -1 + $this->pasteVector->y + $this->width,
            -1 + ceil($this->pasteVector->z - $this->width / 2) + $this->width
        );
    }

    public function getTotalCount(): int
    {
        return $this->width ** 3;
    }

    public static function getName(): string
    {
        return "Cube";
    }
}