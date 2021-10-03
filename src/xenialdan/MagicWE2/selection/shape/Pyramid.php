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

class Pyramid extends Shape
{
	/** @var int */
	public int $width = 5;
	/** @var int */
	public int $height = 5;
	/** @var int */
	public int $depth = 5;
	/** @var bool */
	public bool $flipped = false;

	/**
	 * Pyramid constructor.
	 * @param Vector3 $pasteVector
	 * @param int $width
	 * @param int $height
	 * @param int $depth
	 * @param bool $flipped
	 */
	public function __construct(Vector3 $pasteVector, int $width, int $height, int $depth, bool $flipped = false)
	{
		$this->pasteVector = $pasteVector;
		$this->width = $width;
		$this->height = $height;
		$this->depth = $depth;
		$this->flipped = $flipped;
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
		$reduceXPerLayer = -($this->width / $this->height);
		$reduceZPerLayer = -($this->depth / $this->height);
		$centerVec2 = new Vector2($this->getPasteVector()->getX(), $this->getPasteVector()->getZ());
		for ($x = (int)floor($centerVec2->x - $this->width / 2 - 1); $x <= floor($centerVec2->x + $this->width / 2 + 1); $x++) {
			for ($y = (int)floor($this->getPasteVector()->y), $ry = 0; $y < floor($this->getPasteVector()->y + $this->height); $y++, $ry++) {
				for ($z = (int)floor($centerVec2->y - $this->depth / 2 - 1); $z <= floor($centerVec2->y + $this->depth / 2 + 1); $z++) {
					$vec2 = new Vector2($x, $z);
					$vec3 = new Vector3($x, $y, $z);
					if ($this->flipped) {
						$radiusLayerX = ($this->width + $reduceXPerLayer * ($this->height - $ry)) / 2;
						$radiusLayerZ = ($this->depth + $reduceZPerLayer * ($this->height - $ry)) / 2;
					} else {
						$radiusLayerX = ($this->width + $reduceXPerLayer * $ry) / 2;
						$radiusLayerZ = ($this->depth + $reduceZPerLayer * $ry) / 2;
					}
					//TODO hollow
					if (floor(abs($centerVec2->x - $vec2->x)) >= $radiusLayerX || floor(abs($centerVec2->y - $vec2->y)) >= $radiusLayerZ)
						continue;
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
		for ($x = (int)floor($centerVec2->x - $this->width / 2 - 1); $x <= floor($centerVec2->x + $this->width / 2 + 1); $x++) {
			for ($z = (int)floor($centerVec2->y - $this->depth / 2 - 1); $z <= floor($centerVec2->y + $this->depth / 2 + 1); $z++) {
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
		return (int)ceil((1 / 3) * ($this->width * $this->depth) * $this->height);
	}

	public static function getName(): string
	{
		return "Pyramid";
	}
}