<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2;

use pocketmine\block\Block;
use pocketmine\block\Slab;
use pocketmine\block\Stair;
use pocketmine\level\ChunkManager;
use pocketmine\level\Level;
use pocketmine\level\Position;

class Clipboard extends Selection {
	const DIRECTION_DEFAULT = 1;

	const FLIP_X = 0x01;
	const FLIP_WEST = 0x01;
	const FLIP_EAST = 0x01;
	const FLIP_Y = 0x02;
	const FLIP_UP = 0x02;
	const FLIP_DOWN = 0x02;
	const FLIP_Z = 0x03;
	const FLIP_NORTH = 0x03;
	const FLIP_SOUTH = 0x03;

	/** @var Block[] */
	private $blocks;

	/**
	 * Clipboard constructor.
	 * @param Level $level
	 * @param Block[] $blocks
	 * @param null $minX
	 * @param null $minY
	 * @param null $minZ
	 * @param null $maxX
	 * @param null $maxY
	 * @param null $maxZ
	 */
	public function __construct(Level $level, array $blocks = [], $minX = null, $minY = null, $minZ = null, $maxX = null, $maxY = null, $maxZ = null) {
		parent::__construct($level, $minX, $minY, $minZ, $maxX, $maxY, $maxZ);
		$this->blocks = $blocks;
	}

	/**
	 * @param ChunkManager $manager
	 * @param array $filterblocks
	 * @param int $flags
	 * @return \Generator|Block
	 * @throws \Exception
	 */
	public function getBlocks(ChunkManager $manager, array $filterblocks = [], int $flags = API::FLAG_BASE): \Generator {
		$this->validateChunkManager($manager);
		foreach ($this->blocks as $block) {
			yield $block;
		}
	}

	/**
	 * Replaces the block array with the given blocks
	 * @param Block[] $blocks
	 */
	public function setBlocks(array $blocks) {
		$this->blocks = $blocks;
	}

	/**
	 * Pushes a block to the end of the block array
	 * Ignores duplicated blocks TODO
	 * @param Block $block
	 */
	public function pushBlock(Block $block) {
		$this->blocks[] = $block;
	}

	/**
	 * Pushes a block array to the end of the block array
	 * Ignores duplicated blocks TODO
	 * @param Block[] $blocks
	 */
	public function pushBlocks(array $blocks) {
		$this->blocks = array_merge($this->blocks, $blocks);
	}

	/**
	 * Clears the block array
	 */
	public function clear() {
		$this->blocks = [];
	}

	public function getTotalCount(): int {
		return count($this->blocks);
	}

	/**
	 * @deprecated
	 * @param int $directions
	 */
	public function flip($directions = self::DIRECTION_DEFAULT) {//TODO _ACTUALLY_ move to API //TODO other directions //TODO make async
		$multiplier = ["x" => 1, "y" => 1, "z" => 1];//TODO maybe vector3
		if (API::hasFlag($directions, self::FLIP_X)) {
			$multiplier["x"] = -1;
		}
		if (API::hasFlag($directions, self::FLIP_Y)) {
			$multiplier["y"] = -1;
		}
		if (API::hasFlag($directions, self::FLIP_Z)) {
			$multiplier["z"] = -1;
		}
		$newdata = [];
		foreach ($this->getData() as $block) {
			$newblock = clone $block;
			$newpos = $newblock->add($this->getOffset())->floor();//TEST IF FLOOR OR CEIL
			$newpos = $newpos->setComponents($newpos->getX() * $multiplier["x"], $newpos->getY() * $multiplier["y"], $newpos->getZ() * $multiplier["z"]);
			$newpos = $newpos->subtract($this->getOffset())->floor();//TEST IF FLOOR OR CEIL
			$newblock->position(new Position($newpos->getX(), $newpos->getY(), $newpos->getZ(), $block->getLevel()));
			switch ($newblock) {
				case $newblock instanceof Slab:
					{
						$meta = $newblock->getDamage();
						if (API::hasFlag($directions, self::FLIP_Y)) {
							$meta |= 0x08;
						}
						$newblock->setDamage($meta);
						break;
					}
				case $newblock instanceof Stair:
					{
						$meta = $newblock->getDamage();
						if (API::hasFlag($directions, self::FLIP_Y)) {
							$meta |= 0x04;
						}
						$newblock->setDamage($meta);
						break;
					}
				//TODO check + flip up, flip down etc
			}
			$newdata[] = $newblock;
		}
		$this->setData($newdata);
	}

	/**
	 * @deprecated
	 * @param int $rotations
	 */
	public function rotate($rotations = 0) {//TODO maybe move to API //TODO make async
		$newdata = [];
		foreach ($this->getData() as $block) {
			$newblock = clone $block;
			$newpos = $newblock->add($this->getOffset())->floor();//TEST IF FLOOR OR CEIL
			switch ($rotations % 4) {
				case 1:
					{
						$newpos = $newpos->setComponents(-$newpos->getZ(), $newpos->getY(), $newpos->getX());
						break;
					}
				case 2:
					{
						$newpos = $newpos->setComponents(-$newpos->getX(), $newpos->getY(), -$newpos->getZ());
						break;
					}
				case 3:
					{
						$newpos = $newpos->setComponents(-$newpos->getZ(), $newpos->getY(), $newpos->getX());
						break;
					}
				default:
					{
						//$newpos === $newpos;
					}
			}
			$newpos = $newpos->subtract($this->getOffset())->floor();//TEST IF FLOOR OR CEIL
			$newblock->position(new Position($newpos->getX(), $newpos->getY(), $newpos->getZ(), $block->getLevel()));
			$newblock->setDamage(API::rotationMetaHelper($newblock, $rotations));
			$newdata[] = $newblock;
		}
		$this->setData($newdata);
	}

	/** TODO list:
	 * Serialize, deserialize to/from file
	 * Fix flip for special blocks
	 */


	/**
	 * String representation of object
	 * @link http://php.net/manual/en/serializable.serialize.php
	 * @return string the string representation of the object or null
	 * @since 5.1.0
	 */
	public function serialize() {
		return serialize([
			$this->levelid,
			$this->pos1,
			$this->pos2,
			$this->uuid,
			$this->blocks
		]);
	}

	/**
	 * Constructs the object
	 * @link http://php.net/manual/en/serializable.unserialize.php
	 * @param string $serialized <p>
	 * The string representation of the object.
	 * </p>
	 * @return void
	 * @since 5.1.0
	 */
	public function unserialize($serialized) {
		/** @var Vector3 $pos1 , $pos2 */
		[
			$this->levelid,
			$this->pos1,
			$this->pos2,
			$this->uuid,
			$this->blocks
		] = unserialize($serialized);
	}
}