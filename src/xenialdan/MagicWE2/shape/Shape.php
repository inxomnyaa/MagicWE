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
use xenialdan\MagicWE2\Selection;

abstract class Shape extends Selection{//TODO test
	public $flags = 1;
	public $blocks = "";
	public $options = [];
	public $center = null;

	public function __construct(Level $level, array $options){
		$this->options = $options;
		$this->flags = $options['flags'];
		$this->blocks = $options['blocks'];
		parent::__construct($level);
	}

	public function getBlocksXYZ(Block ...$filterblocks){
		return [];
	}

	public function getCenter(){
		return $this->center??new Vector3();
	}

	public function setCenter(Vector3 $center){
		$this->center = $center;
		$this->setPos1(new Position($center->getX(), $center->getY(), $center->getZ(), $this->getLevel()));
		$this->setPos2(new Position($center->getX(), $center->getY(), $center->getZ(), $this->getLevel()));
	}
}