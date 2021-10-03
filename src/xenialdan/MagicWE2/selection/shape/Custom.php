<?php

namespace xenialdan\MagicWE2\selection\shape;

use Exception;
use Generator;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\World;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\helper\BlockPalette;

class Custom extends Shape
{
	/** @var Vector3[] */
	public array $positions = [];

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
	 * @param World|AsyncChunkManager $manager The world or AsyncChunkManager
	 * @param BlockPalette $filterblocks If not empty, applying a filter on the block list
	 * @param int $flags
	 * @return Generator
	 * @throws Exception
	 */
	public function getBlocks(AsyncChunkManager|World $manager, BlockPalette $filterblocks, int $flags = API::FLAG_BASE): Generator
	{
		$this->validateChunkManager($manager);
		foreach ($this->positions as $position) {
			//TODO filterblocks
			yield API::setComponents($manager->getBlockAt($position->getFloorX(), $position->getFloorY(), $position->getFloorZ()), (int)$position->x, (int)$position->y, (int)$position->z);
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
		/* Mapping: $walked[$hash]=true */
		$walked = [];
		foreach ($this->positions as $position) {
			$hash = World::chunkHash($position->getFloorX(), $position->getFloorZ());
			if (isset($walked[$hash])) continue;
			$walked[$hash] = true;
			yield new Vector2($position->x, $position->z);
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
		$touchedChunks = [];
		foreach ($this->getLayer($manager) as $vector2) {
			$x = $vector2->getFloorX() >> 4;
			$z = $vector2->getFloorY() >> 4;
			if (isset($touchedChunks[World::chunkHash($x, $z)]) || ($chunk = $manager->getChunk($x, $z)) === null) {
				continue;
			}
			print "Touched Chunk at: $x:$z" . PHP_EOL;
			$touchedChunks[World::chunkHash($x, $z)] = FastChunkSerializer::serialize($chunk);
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