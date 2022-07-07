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
	 * @param AsyncWorld $manager The world or AsyncChunkManager
	 * @param BlockPalette $filterblocks If not empty, applying a filter on the block list
	 * @return Block[]|Generator
	 * @phpstan-return Generator<int, Block, void, void>
	 * @noinspection PhpDocSignatureInspection
	 */
	public function getBlocks(AsyncWorld $manager, BlockPalette $filterblocks): Generator
	{
		for ($x = (int)floor($this->getMinVec3()->x), $rx = 0; $x <= floor($this->getMaxVec3()->x); $x++, $rx++) {
			for ($y = (int)floor($this->getMinVec3()->y), $ry = 0; $y <= floor($this->getMaxVec3()->y); $y++, $ry++) {
				for ($z = (int)floor($this->getMinVec3()->z), $rz = 0; $z <= floor($this->getMaxVec3()->z); $z++, $rz++) {
					$block = API::setComponents($manager->getBlockAt($x, $y, $z), $x, $y, $z);
//					if (API::hasFlag($flags, API::FLAG_KEEP_BLOCKS) && $block->getId() !== BlockLegacyIds::AIR) continue;
//					if (API::hasFlag($flags, API::FLAG_KEEP_AIR) && $block->getId() === BlockLegacyIds::AIR) continue;

					if ($block->getPosition()->y >= World::Y_MAX || $block->getPosition()->y < 0) continue;//TODO check for removal because relative might be at other y
//					if (API::hasFlag($flags, API::FLAG_HOLLOW) && ($block->getPosition()->x > $this->getMinVec3()->getX() && $block->getPosition()->x < $this->getMaxVec3()->getX()) && ($block->getPosition()->y > $this->getMinVec3()->getY() && $block->getPosition()->y < $this->getMaxVec3()->getY()) && ($block->getPosition()->z > $this->getMinVec3()->getZ() && $block->getPosition()->z < $this->getMaxVec3()->getZ())) continue;
					if ($filterblocks->empty()) yield $block;
					else {
						foreach ($filterblocks->palette() as $filterblock) {
//							if (($block->getId() === $filterblock->getId()) && ((API::hasFlag($flags, API::FLAG_VARIANT) && $block->getIdInfo()->getVariant() === $filterblock->getIdInfo()->getVariant()) || (!API::hasFlag($flags, API::FLAG_VARIANT) && ($block->getMeta() === $filterblock->getMeta() || API::hasFlag($flags, API::FLAG_KEEP_META)))))
							if ($block->getFullId() === $filterblock->getFullId())
								yield $block;
						}
					}
				}
			}
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
		for ($x = (int)floor($this->getMinVec3()->x); $x <= floor($this->getMaxVec3()->x); $x++) {
			for ($z = (int)floor($this->getMinVec3()->z); $z <= floor($this->getMaxVec3()->z); $z++) {
				yield new Vector2($x, $z);
			}
		}
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