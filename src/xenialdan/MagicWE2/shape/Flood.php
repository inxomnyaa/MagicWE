<?php

namespace xenialdan\MagicWE2\shape;

use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;

class Flood extends Shape{
	private $limit = 10000;
	private $walked = [];
	private $foundBlocks = [];

	/**
	 * Square constructor.
	 * @param Level $level
	 * @param array $options
	 */
	public function __construct(Level $level, array $options){
		parent::__construct($level, $options);
		$this->limit = $options["limit"] ?? $this->limit;
	}

	/**
	 * @param int $flags
	 * @param Block[] ...$filterblocks
	 * @return array
	 */
	public function getBlocks(int $flags, Block ...$filterblocks){//TODO use filterblocks
		$current = $this->getLevel()->getBlock($this->getCenter());
		return $this->walk($current);
	}

	private function walk(Block $block){//TODO find a better calculation
		foreach ($block->getHorizontalSides() as $horizontalSide){
			if (count($this->foundBlocks) >= $this->limit){
				//Flood limit exceeded
				return $this->foundBlocks;
			}
			if (array_key_exists(($index = $this->calculateIndex($horizontalSide)), $this->walked)) continue;
			var_dump($index);
			$this->walked[$index] = true;
			if ($horizontalSide->getId() === 0){//todo any id selected
				$this->foundBlocks[] = $horizontalSide;
				$this->foundBlocks = array_merge($this->foundBlocks, $this->walk($horizontalSide));
			}
		}
		return $this->foundBlocks;
	}

	public function setCenter(Vector3 $center){
		$this->center = $center;
		$this->setPos1(new Position($center->getX(), $center->getY(), $center->getZ(), $this->getLevel()));
		$this->setPos2(new Position($center->getX(), $center->getY(), $center->getZ(), $this->getLevel()));
	}

	private function calculateIndex(Vector3 $vector3){
		return $vector3->getX() + $vector3->getZ() * $vector3->getX();//TODO better calculation
	}
}