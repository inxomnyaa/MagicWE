<?php

namespace xenialdan\MagicWE2\selection\shape;

use Generator;
use pocketmine\block\Block;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\AsyncWorld;
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

	public function offset(Vector3 $offset): Shape
	{
		$shape = clone $this;
		$pos = $this->positions;
		$this->positions = [];
		foreach ($pos as $vector3)$this->positions[]=$vector3->addVector($offset);
		$shape->setPasteVector($this->getPasteVector()->addVector($offset));
		return $shape;
	}

	/**
	 * Returns the blocks by their actual position
	 * @param AsyncWorld $manager The world or AsyncChunkManager
	 * @param BlockPalette $filterblocks If not empty, applying a filter on the block list
	 * @return Block[]|Generator
	 * @phpstan-return Generator<int, Block, void, void>
	 * @noinspection PhpDocSignatureInspection
	 */
	public function getBlocks(AsyncWorld $manager, BlockPalette $filterblocks): Generator
	{
		foreach ($this->positions as $position) {
			//TODO filterblocks
			yield API::setComponents($manager->getBlockAt($position->getFloorX(), $position->getFloorY(), $position->getFloorZ()), (int)$position->x, (int)$position->y, (int)$position->z);
		}
	}

	/**
	 * Returns a flat layer of all included x z positions in selection
	 * @param AsyncWorld $manager The world or AsyncChunkManager
	 * @param int $flags
	 * @return Generator
	 */
	public function getLayer(AsyncWorld $manager, int $flags = API::FLAG_BASE): Generator
	{
		/* Mapping: $walked[$hash]=true */
		$walked = [];
		foreach ($this->positions as $position) {
			$hash = World::chunkHash($position->getFloorX(), $position->getFloorZ());
			if (isset($walked[$hash])) continue;
			$walked[$hash] = true;
			yield new Vector2($position->x, $position->z);
		}
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