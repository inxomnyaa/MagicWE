<?php

namespace xenialdan\MagicWE2\shape;

use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use xenialdan\MagicWE2\API;

class Cuboid extends Shape{

	/**
	 * Square constructor.
	 * @param Level $level
	 * @param array $options
	 */
	public function __construct(Level $level, array $options){
		parent::__construct($level, $options);
	}

	/**
	 * @param int $flags
	 * @param Block[] ...$filterblocks
	 * @return array
	 */
	public function getBlocks(int $flags, Block ...$filterblocks){
		$blocks = [];
		for ($x = $this->getMinVec3()->getX(); $x <= $this->getMaxVec3()->getX(); $x++){
			for ($z = $this->getMinVec3()->getZ(); $z <= $this->getMaxVec3()->getZ(); $z++){
				for ($y = $this->getMinVec3()->getY(); $y <= $this->getMaxVec3()->getY(); $y++){
					if (API::hasFlag($this->flags, API::FLAG_HOLLOW) && ($x > $this->getMinVec3()->getX() && $x < $this->getMaxVec3()->getX()) && ($y > $this->getMinVec3()->getY() && $y < $this->getMaxVec3()->getY()) && ($z > $this->getMinVec3()->getZ() && $z < $this->getMaxVec3()->getZ())) continue;
					$blocks[] = $this->getLevel()->getBlock(new Vector3((int)floor($x), (int)floor($y), (int)floor($z)));
				}
			}
		}
		return $blocks;
	}

	public function setCenter(Vector3 $center){//TODO change diameter to width after command rewrite
		$this->center = $center;
		$this->setPos1(new Position(floor($this->getCenter()->getX() - $this->options['width'] / 2), floor($this->getCenter()->getY() - $this->options['height'] / 2), floor($this->getCenter()->getZ() - $this->options['depth'] / 2), $this->getLevel()));
		$this->setPos2(new Position(floor($this->getCenter()->getX() + $this->options['width'] / 2), floor($this->getCenter()->getY() + $this->options['height'] / 2), floor($this->getCenter()->getZ() + $this->options['depth'] / 2), $this->getLevel()));
	}
}