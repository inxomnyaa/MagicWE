<?php

namespace xenialdan\MagicWE2\shape;

use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use xenialdan\MagicWE2\API;

class Sphere extends Shape{

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
					$vector3 = new Position((int)floor($x), (int)floor($y), (int)floor($z));
					if ($vector3->distanceSquared($this->getCenter()) <= (($this->options['diameter'] / 2) ** 2) && (!API::hasFlag($this->flags, API::FLAG_HOLLOW) || $vector3->distanceSquared($this->getCenter()) >= ((($this->options['diameter'] / 2) - 1) ** 2)))
						$blocks[] = $this->getLevel()->getBlock($vector3);
				}
			}
		}
		return $blocks;
	}

	public function setCenter(Vector3 $center){//TODO change diameter to width after command rewrite
		$this->center = $center;
		$this->setPos1(new Position(floor($this->getCenter()->getX() - $this->options['diameter'] / 2), floor($this->getCenter()->getY() - $this->options['diameter'] / 2), floor($this->getCenter()->getZ() - $this->options['diameter'] / 2), $this->getLevel()));
		$this->setPos2(new Position(floor($this->getCenter()->getX() + $this->options['diameter'] / 2), floor($this->getCenter()->getY() + $this->options['diameter'] / 2), floor($this->getCenter()->getZ() + $this->options['diameter'] / 2), $this->getLevel()));
	}
}