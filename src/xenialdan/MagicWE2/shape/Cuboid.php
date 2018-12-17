<?php

namespace xenialdan\MagicWE2\shape;

use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;

class Cuboid extends Shape {

	/**
	 * Square constructor.
	 * @param Level $level
	 * @param array $options
	 */
	public function __construct(Level $level, array $options) {
		parent::__construct($level, $options);
	}

	public function setCenter(Vector3 $center) {//TODO change diameter to width after command rewrite
		$this->center = $center;
		try {
			$this->setPos1(new Position(floor($this->getCenter()->getX() - $this->options['width'] / 2), floor($this->getCenter()->getY() - $this->options['height'] / 2), floor($this->getCenter()->getZ() - $this->options['depth'] / 2), $this->getLevel()));
		} catch (\Exception $e) {
		}
		try {
			$this->setPos2(new Position(floor($this->getCenter()->getX() + $this->options['width'] / 2), floor($this->getCenter()->getY() + $this->options['height'] / 2), floor($this->getCenter()->getZ() + $this->options['depth'] / 2), $this->getLevel()));
		} catch (\Exception $e) {
		}
	}
}