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

class Cone extends Shape
{
	/** @var int */
	public int $height = 5;
	/** @var int */
	public int $diameter = 5;
	/** @var bool */
	public bool $flipped = false;

	/**
	 * Cone constructor.
	 * @param Vector3 $pasteVector
	 * @param int $height
	 * @param int $diameter
	 * @param bool $flipped
	 */
	public function __construct(Vector3 $pasteVector, int $height, int $diameter, bool $flipped = false)
	{
		$this->pasteVector = $pasteVector;
		$this->height = $height;
		$this->diameter = $diameter;
		$this->flipped = $flipped;
	}

	public function offset(Vector3 $offset) : Shape{
		$shape = clone $this;
		$shape->setPasteVector($this->pasteVector->addVector($offset));
		return $shape;
	}

	public function rotate(int $rotation) : self{
		return clone $this;
	}

	/**
	 * Returns the blocks by their actual position
	 *
	 * @param AsyncWorld   $manager The world or AsyncChunkManager
	 * @param BlockPalette $filterblocks If not empty, applying a filter on the block list
	 *
	 * @return Block[]|Generator
	 * @phpstan-return Generator<int, Block, void, void>
	 * @noinspection PhpDocSignatureInspection
	 */
	public function getBlocks(AsyncWorld $manager, BlockPalette $filterblocks) : Generator
	{
		##$reducePerLayer = ($this->diameter / $this->height);
		$centerVec2 = new Vector2($this->pasteVector->getX(), $this->pasteVector->getZ());
		for ($x = (int)floor($centerVec2->x - $this->diameter / 2 - 1); $x <= floor($centerVec2->x + $this->diameter / 2 + 1); $x++){
			for($y = (int) floor($this->pasteVector->y), $ry = 0; $y < floor($this->pasteVector->y + $this->height); $y++, $ry++){
				for($z = (int) floor($centerVec2->y - $this->diameter / 2 - 1); $z <= floor($centerVec2->y + $this->diameter / 2 + 1); $z++){
//					$vec2 = new Vector2($x, $z);
					$vec3 = new Vector3($x, $y, $z);
					/*if ($this->flipped)
						$radiusLayer = ($this->diameter - $reducePerLayer * ($this->height - $ry)) / 2;
					else
						$radiusLayer = ($this->diameter - $reducePerLayer * $ry) / 2;*/
//					if ($vec2->distanceSquared($centerVec2) > ($radiusLayer ** 2) || (API::hasFlag($flags, API::FLAG_HOLLOW_CLOSED) && ($ry !== 0 && $ry !== $this->height - 1) && $vec2->distanceSquared($centerVec2) <= ((($this->diameter / 2) - 1) ** 2)) || ((API::hasFlag($flags, API::FLAG_HOLLOW) && $vec2->distanceSquared($centerVec2) <= ((($this->diameter / 2) - 1) ** 2))))
//						continue;
					$block = API::setComponents($manager->getBlockAt($vec3->getFloorX(), $vec3->getFloorY(), $vec3->getFloorZ()), (int) $vec3->x, (int) $vec3->y, (int) $vec3->z);
//					if (API::hasFlag($flags, API::FLAG_KEEP_BLOCKS) && $block->getId() !== BlockLegacyIds::AIR) continue;
//					if (API::hasFlag($flags, API::FLAG_KEEP_AIR) && $block->getId() === BlockLegacyIds::AIR) continue;

					if ($block->getPosition()->y >= World::Y_MAX || $block->getPosition()->y < 0) continue;//TODO fuufufufuuu EDIT: And.. fufufu is what?
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
	public function getLayer(AsyncWorld $manager, int $flags = API::FLAG_BASE): Generator{
		$centerVec2 = new Vector2($this->pasteVector->getX(), $this->pasteVector->getZ());
		for ($x = (int)floor($centerVec2->x - $this->diameter / 2 - 1); $x <= floor($centerVec2->x + $this->diameter / 2 + 1); $x++) {
			for ($z = (int)floor($centerVec2->y - $this->diameter / 2 - 1); $z <= floor($centerVec2->y + $this->diameter / 2 + 1); $z++) {
				$vec2 = new Vector2($x, $z);
				if ($vec2->distanceSquared($centerVec2) > (($this->diameter / 2) ** 2) || ((API::hasFlag($flags, API::FLAG_HOLLOW) && $vec2->distanceSquared($centerVec2) <= ((($this->diameter / 2) - 1) ** 2))))
					continue;
				yield $vec2;
			}
		}
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
		return (int)ceil((M_PI * (($this->diameter / 2) ** 2) * $this->height) / 3);
	}

	public static function getName(): string
	{
		return "Cone";
	}
}