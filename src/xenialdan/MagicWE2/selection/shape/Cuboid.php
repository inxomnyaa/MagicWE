<?php

namespace xenialdan\MagicWE2\selection\shape;

use Exception;
use Generator;
use pocketmine\block\BlockLegacyIds;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\World;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\helper\BlockPalette;

class Cuboid extends Shape
{
	/** @var int */
	public int $width = 5;
	/** @var int */
	public int $height = 5;
	/** @var int */
	public int $depth = 5;

	/**
	 * Cuboid constructor.
	 * @param Vector3 $pasteVector
	 * @param int $width
	 * @param int $height
	 * @param int $depth
	 */
	public function __construct(Vector3 $pasteVector, int $width, int $height, int $depth)
	{
		$this->pasteVector = $pasteVector;
		$this->width = $width;
		$this->height = $height;
		$this->depth = $depth;
	}

	public static function constructFromPositions(Vector3 $pos1, Vector3 $pos2): self
	{
		$width = (int)abs($pos1->getX() - $pos2->getX()) + 1;
		$height = (int)abs($pos1->getY() - $pos2->getY()) + 1;
		$depth = (int)abs($pos1->getZ() - $pos2->getZ()) + 1;
		return new Cuboid((new Vector3(($pos1->x + $pos2->x) / 2, min($pos1->y, $pos2->y), ($pos1->z + $pos2->z) / 2)), $width, $height, $depth);
	}

	/**
	 * Returns the blocks by their actual position
	 * @param World|AsyncChunkManager $manager The world or AsyncChunkManager
	 * @param BlockPalette $filterblocks If not empty, applying a filter on the block list
	 * @param int $flags
	 * @return Generator
	 * @throws Exception
	 */
	public function getBlocks(AsyncChunkManager|World $manager, BlockPalette $filterblocks, int $flags = API::FLAG_BASE): Generator
	{
		$this->validateChunkManager($manager);
		for ($x = (int)floor($this->getMinVec3()->x); $x <= floor($this->getMaxVec3()->x); $x++) {
			for ($y = (int)floor($this->getMinVec3()->y); $y <= floor($this->getMaxVec3()->y); $y++) {
				for ($z = (int)floor($this->getMinVec3()->z); $z <= floor($this->getMaxVec3()->z); $z++) {
					$block = API::setComponents($manager->getBlockAt($x, $y, $z), $x, $y, $z);
					#var_dump("shape getblocks", $block);
					if (API::hasFlag($flags, API::FLAG_KEEP_BLOCKS) && $block->getId() !== BlockLegacyIds::AIR) continue;
					if (API::hasFlag($flags, API::FLAG_KEEP_AIR) && $block->getId() === BlockLegacyIds::AIR) continue;

					if ($block->getPosition()->y >= World::Y_MAX || $block->getPosition()->y < 0) continue;//TODO check for removal because relative might be at other y
					if (API::hasFlag($flags, API::FLAG_HOLLOW) && ($block->getPosition()->x > $this->getMinVec3()->getX() && $block->getPosition()->x < $this->getMaxVec3()->getX()) && ($block->getPosition()->y > $this->getMinVec3()->getY() && $block->getPosition()->y < $this->getMaxVec3()->getY()) && ($block->getPosition()->z > $this->getMinVec3()->getZ() && $block->getPosition()->z < $this->getMaxVec3()->getZ())) continue;
					if ($filterblocks->empty()) yield $block;
					else {
						foreach ($filterblocks->palette() as $filterblock) {
							if (($block->getId() === $filterblock->getId()) && ((API::hasFlag($flags, API::FLAG_VARIANT) && $block->getIdInfo()->getVariant() === $filterblock->getIdInfo()->getVariant()) || (!API::hasFlag($flags, API::FLAG_VARIANT) && ($block->getMeta() === $filterblock->getMeta() || API::hasFlag($flags, API::FLAG_KEEP_META)))))
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
	 * @return Generator
	 * @throws Exception
	 */
	public function getLayer(AsyncChunkManager|World $manager, int $flags = API::FLAG_BASE): Generator
	{
		$this->validateChunkManager($manager);
		for ($x = (int)floor($this->getMinVec3()->x); $x <= floor($this->getMaxVec3()->x); $x++) {
			for ($z = (int)floor($this->getMinVec3()->z); $z <= floor($this->getMaxVec3()->z); $z++) {
				yield new Vector2($x, $z);
			}
		}
	}

	/**
	 * @param World|AsyncChunkManager $manager
	 * @return string[] fastSerialized chunks
	 * @throws Exception
	 */
	public function getTouchedChunks(AsyncChunkManager|World $manager): array
	{
		$this->validateChunkManager($manager);
		$maxX = ($this->getMaxVec3()->x + 1) >> 4;
		$minX = $this->getMinVec3()->x >> 4;
		$maxZ = ($this->getMaxVec3()->z + 1) >> 4;
		$minZ = $this->getMinVec3()->z >> 4;
		$touchedChunks = [];
		for ($x = $minX; $x <= $maxX; $x++) {
			for ($z = $minZ; $z <= $maxZ; $z++) {
				$chunk = $manager->getChunk($x, $z);
				if ($chunk === null) {
					continue;
				}
				$touchedChunks[World::chunkHash($x, $z)] = FastChunkSerializer::serialize($chunk);
			}
		}
		return $touchedChunks;
	}

	public function getAABB(): AxisAlignedBB
	{
		return new AxisAlignedBB(
			ceil($this->pasteVector->x - $this->width / 2),
			$this->pasteVector->y,
			ceil($this->pasteVector->z - $this->depth / 2),
			-1 + ceil($this->pasteVector->x - $this->width / 2) + $this->width,
			-1 + $this->pasteVector->y + $this->height,
			-1 + ceil($this->pasteVector->z - $this->depth / 2) + $this->depth
		);
	}

	/**
	 * @return int
	 */
	public function getTotalCount(): int
	{
		return $this->width * $this->height * $this->depth;
	}

	public static function getName(): string
	{
		return "Cuboid";
	}
}