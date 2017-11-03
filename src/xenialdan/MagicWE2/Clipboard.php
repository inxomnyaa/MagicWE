<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2;


use pocketmine\block\Block;
use pocketmine\block\Stair;
use pocketmine\level\Position;
use pocketmine\math\Vector3;


class Clipboard{
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

	private $data;
	/** @var Position */
	private $offset;

	public function __construct($data = null){
		$this->setData($data);
	}

	public function getData(){ //TODO check if by reference
		return $this->data;
	}

	public function setData($data){
		$this->data = $data;
	}

	public function setOffset(Vector3 $offset){
		$this->offset = $offset;
	}

	public function getOffset(){
		return $this->offset;
	}

	public function flip($directions = self::DIRECTION_DEFAULT){//TODO maybe move to API //TODO other directions
		$multiplier = ["x" => 1, "y" => 1, "z" => 1];//TODO maybe vector3
		if (API::hasFlag($directions, self::FLIP_X)){
			$multiplier["x"] = -1;
		}
		if (API::hasFlag($directions, self::FLIP_Y)){
			$multiplier["y"] = -1;
		}
		if (API::hasFlag($directions, self::FLIP_Z)){
			$multiplier["z"] = -1;
		}
		$newdata = [];
		/** @var Block $block */
		foreach ($this->getData() as $block){
			$newblock = clone $block;
			$newpos = $newblock->add($this->getOffset());
			var_dump($newpos->asVector3());
			$newpos = $newpos->setComponents($newpos->getX() * $multiplier["x"], $newpos->getX() * $multiplier["x"], $newpos->getX() * $multiplier["x"]);
			$newpos = $newpos->subtract($this->getOffset());
			$newblock->position(new Position($newpos->getFloorX(), $newpos->getFloorY(), $newpos->getFloorZ()));
			switch ($newblock){
				case $newblock instanceof Stair: {
					$meta = $newblock->getDamage();
					$faces = [
						0 => 0,
						1 => 2,
						2 => 1,
						3 => 3
					];
					if (API::hasFlag($directions, self::FLIP_X)){
						$meta |= 0x01;
					}
					if (API::hasFlag($directions, self::FLIP_Y)){
						$meta |= 0x01;
					}
					if (API::hasFlag($directions, self::FLIP_Z)){
						$meta |= 0x04; //correct
					}
				}
				//TODO check + flip up, flip down etc
			}
		}
		$this->setData($newdata);
	}

	/** TODO list:
	 * Serialize, deserialize to/from file
	 * Flip
	 * Rotate
	 */
}