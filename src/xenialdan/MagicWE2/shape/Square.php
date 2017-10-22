<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

namespace xenialdan\MagicWE2\shape;


use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use xenialdan\MagicWE2\API;

class Square extends Shape{

	/**
	 * Square constructor.
	 * @param Level $level
	 * @param array $options
	 */
	public function __construct(Level $level, array $options){
		parent::__construct($level, $options);
	}

	public function getBlocksXYZ(Block ...$filterblocks){
		$blocks = [];
		for ($x = $this->getMinVec3()->getX(); $x < $this->getMaxVec3()->getX(); $x++){
			for ($z = $this->getMinVec3()->getZ(); $z < $this->getMaxVec3()->getZ(); $z++){
				for ($y = $this->getMinVec3()->getY(); $y < $this->getMaxVec3()->getY(); $y++){
					if (API::hasFlag($this->flags, API::FLAG_HOLLOW) && ($x > $this->getMinVec3()->getX() && $x < $this->getMaxVec3()->getX() - 1) && ($y > $this->getMinVec3()->getY() && $y < $this->getMaxVec3()->getY() - 1) && ($z > $this->getMinVec3()->getZ() && $z < $this->getMaxVec3()->getZ() - 1)) continue;
					$blocks[(int)floor($x)][(int)floor($y)][(int)floor($z)] = new Vector3((int)floor($x), (int)floor($y), (int)floor($z));
				}
			}
		}
		return $blocks;
	}

	public function setCenter(Vector3 $center){//TODO change diameter to width after command rewrite
		$this->center = $center;
		$this->setPos1(new Position(floor($this->getCenter()->getX() - $this->options['diameter'] / 2), floor($this->getCenter()->getY() - $this->options['height'] / 2), floor($this->getCenter()->getZ() - $this->options['diameter'] / 2), $this->getLevel()));
		$this->setPos2(new Position(floor($this->getCenter()->getX() + $this->options['diameter'] / 2), floor($this->getCenter()->getY() + $this->options['height'] / 2), floor($this->getCenter()->getZ() + $this->options['diameter'] / 2), $this->getLevel()));
	}
}