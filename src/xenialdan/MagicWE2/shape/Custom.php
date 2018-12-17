<?php

namespace xenialdan\MagicWE2\shape;

use pocketmine\level\Level;
use pocketmine\math\Vector3;

class Custom extends Shape {

	private $blockarray = [];

	/**
	 * Square constructor.
	 * @param Level $level
	 * @param array $options
	 */
	public function __construct(Level $level, array $options) {
		parent::__construct($level, $options);
	}

	/**
	 * @param array $blockarray
	 */
	public function setBlockarray(array $blockarray) {
		$this->blockarray = $blockarray;
	}

	public function setCenter(Vector3 $center) {
		$this->center = $center;
	}
}