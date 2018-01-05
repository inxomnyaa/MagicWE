<?php

namespace xenialdan\MagicWE2\shape;

use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use xenialdan\MagicWE2\Selection;

abstract class Shape extends Selection{//TODO test
	public $flags = 1;
	public $blocks = "";
	public $options = [];
	public $center = null;

	public function __construct(Level $level, array $options){
		$this->options = $options;
		if(isset($options['flags'])) $this->flags = $options['flags'];
		if(isset($options['blocks'])) $this->blocks = $options['blocks'];
		parent::__construct($level);
	}

	/**
	 * @param int $flags
	 * @param Block[] ...$filterblocks
	 * @return array
	 */
	public function getBlocks(int $flags, Block ...$filterblocks){
		return [];
	}

	public function getCenter(){
		return $this->center ?? new Vector3();
	}

	public function setCenter(Vector3 $center){
		$this->center = $center;
		$this->setPos1(new Position($center->getX(), $center->getY(), $center->getZ(), $this->getLevel()));
		$this->setPos2(new Position($center->getX(), $center->getY(), $center->getZ(), $this->getLevel()));
	}
}