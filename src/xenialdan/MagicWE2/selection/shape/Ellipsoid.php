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

class Ellipsoid extends Shape
{
	/** @var int */
	public int $width = 5;
	/** @var int */
	public int $height = 5;
	/** @var int */
	public int $depth = 5;

	/**
	 * Pyramid constructor.
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
		$centerVec2 = new Vector2($this->getPasteVector()->getX(), $this->getPasteVector()->getZ());
		$this->pasteVector = $this->getPasteVector()->add(0, -0.5, 0);

		$xrad = $this->width / 2;
		$yrad = $this->height / 2;
		$zrad = $this->depth / 2;
		$xradSquared = $xrad ** 2;
		$yradSquared = $yrad ** 2;
		$zradSquared = $zrad ** 2;
		$targetX = $this->pasteVector->getX();
		$targetY = $this->pasteVector->getY();
		$targetZ = $this->pasteVector->getZ();

		for ($x = (int)floor($centerVec2->x - $this->width / 2 /*- 1*/); $x <= floor($centerVec2->x + $this->width / 2 /*+ 1*/); $x++) {
			$xSquared = ($targetX - $x) ** 2;
			for ($y = (int)floor($this->getPasteVector()->y) + 1, $ry = 0; $y <= floor($this->getPasteVector()->y + $this->height); $y++, $ry++) {
				$ySquared = ($targetY - $y + $yrad) ** 2;
				for ($z = (int)floor($centerVec2->y - $this->depth / 2 /*- 1*/); $z <= floor($centerVec2->y + $this->depth / 2 /*+ 1*/); $z++) {
					$zSquared = ($targetZ - $z) ** 2;

					$vec3 = new Vector3($x, $y, $z);
					//TODO hollow
					if ($xSquared / $xradSquared + $ySquared / $yradSquared + $zSquared / $zradSquared >= 1) continue;
					$block = API::setComponents($manager->getBlockAt($vec3->getFloorX(), $vec3->getFloorY(), $vec3->getFloorZ()), (int)$vec3->x, (int)$vec3->y, (int)$vec3->z);
					if (API::hasFlag($flags, API::FLAG_KEEP_BLOCKS) && $block->getId() !== BlockLegacyIds::AIR) continue;
					if (API::hasFlag($flags, API::FLAG_KEEP_AIR) && $block->getId() === BlockLegacyIds::AIR) continue;

					if ($block->getPosition()->y >= World::Y_MAX || $block->getPosition()->y < 0) continue;//TODO fuufufufuuu
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
		$centerVec2 = new Vector2($this->getPasteVector()->getX(), $this->getPasteVector()->getZ());

		$xrad = $this->width / 2;
		$zrad = $this->depth / 2;
		$xradSquared = $xrad ** 2;
		$zradSquared = $zrad ** 2;
		$targetX = $this->pasteVector->getX();
		$targetZ = $this->pasteVector->getZ();

		for ($x = (int)floor($centerVec2->x - $this->width / 2 /*- 1*/); $x <= floor($centerVec2->x + $this->width / 2 /*+ 1*/); $x++) {
			$xSquared = ($targetX - $x) ** 2;
			for ($z = (int)floor($centerVec2->y - $this->depth / 2 /*- 1*/); $z <= floor($centerVec2->y + $this->depth / 2 /*+ 1*/); $z++) {
				$zSquared = ($targetZ - $z) ** 2;
				if ($xSquared / $xradSquared + $zSquared / $zradSquared >= 1) continue;
				//TODO hollow
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
	{//TODO optimize to remove "corner" chunks
		$this->validateChunkManager($manager);
		$maxX = ($this->getMaxVec3()->x + 1) >> 4;
		$minX = $this->getMinVec3()->x >> 4;
		$maxZ = ($this->getMaxVec3()->z + 1) >> 4;
		$minZ = $this->getMinVec3()->z >> 4;
		$touchedChunks = [];
		for ($x = $minX - 1; $x <= $maxX + 1; $x++) {
			for ($z = $minZ - 1; $z <= $maxZ + 1; $z++) {
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
			floor($this->pasteVector->x - $this->width / 2),
			$this->pasteVector->y,
			floor($this->pasteVector->z - $this->depth / 2),
			-1 + floor($this->pasteVector->x - $this->width / 2) + $this->width,
			-1 + $this->pasteVector->y + $this->height,
			-1 + floor($this->pasteVector->z - $this->depth / 2) + $this->depth
		);
	}

	public function getTotalCount(): int
	{
		return (int)floor(4 * M_PI * (($this->width / 2) + 1) * (($this->height / 2) + 1) * (($this->depth / 2) + 1) / 3);
	}

	public static function getName(): string
	{
		return "Ellipsoid";
	}
}