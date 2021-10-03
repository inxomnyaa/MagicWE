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

class Cylinder extends Shape
{
	/** @var int */
	public int $height = 1;
	/** @var int */
	public int $diameter = 5;

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
		for ($x = (int)floor($centerVec2->x - $this->diameter / 2 - 1); $x <= floor($centerVec2->x + $this->diameter / 2 + 1); $x++) {
			for ($y = (int)floor($this->getPasteVector()->y), $ry = 0; $y < floor($this->getPasteVector()->y + $this->height); $y++, $ry++) {
				for ($z = (int)floor($centerVec2->y - $this->diameter / 2 - 1); $z <= floor($centerVec2->y + $this->diameter / 2 + 1); $z++) {
					$vec2 = new Vector2($x, $z);
					$vec3 = new Vector3($x, $y, $z);
					if ($vec2->distanceSquared($centerVec2) > (($this->diameter / 2) ** 2) || (API::hasFlag($flags, API::FLAG_HOLLOW_CLOSED) && ($ry !== 0 && $ry !== $this->height - 1) && $vec2->distanceSquared($centerVec2) <= ((($this->diameter / 2) - 1) ** 2)) || ((API::hasFlag($flags, API::FLAG_HOLLOW) && $vec2->distanceSquared($centerVec2) <= ((($this->diameter / 2) - 1) ** 2))))
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
		for ($x = (int)floor($centerVec2->x - $this->diameter / 2 - 1); $x <= floor($centerVec2->x + $this->diameter / 2 + 1); $x++) {
			for ($z = (int)floor($centerVec2->y - $this->diameter / 2 - 1); $z <= floor($centerVec2->y + $this->diameter / 2 + 1); $z++) {
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
		return (int)ceil(M_PI * (($this->diameter / 2) ** 2) * $this->height);
	}

	public static function getName(): string
	{
		return "Cylinder";
	}
}