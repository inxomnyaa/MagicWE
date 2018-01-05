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

	/** @var Block[] */
	private $data = [];
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

	public function flip($directions = self::DIRECTION_DEFAULT){//TODO _ACTUALLY_ move to API //TODO other directions
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
		foreach ($this->getData() as $block){
			$newblock = clone $block;
			$newpos = $newblock->add($this->getOffset())->floor();//TEST IF FLOOR OR CEIL
			$newpos = $newpos->setComponents($newpos->getX() * $multiplier["x"], $newpos->getY() * $multiplier["y"], $newpos->getZ() * $multiplier["z"]);
			$newpos = $newpos->subtract($this->getOffset())->floor();//TEST IF FLOOR OR CEIL
			$newblock->position(new Position($newpos->getX(), $newpos->getY(), $newpos->getZ(), $block->getLevel()));
			switch ($newblock){
				case $newblock instanceof Stair: {
					$meta = $newblock->getDamage();
					if (API::hasFlag($directions, self::FLIP_X)){
						$meta ^= 0x03;
					}
					if (API::hasFlag($directions, self::FLIP_Y)){
						$meta |= 0x04; //correct
					}
					if (API::hasFlag($directions, self::FLIP_Z)){
						$meta ^= 0x03;
					}
					$newblock->setDamage($meta);
				}
				//TODO check + flip up, flip down etc
			}
			$newdata[] = $newblock;
		}
		$this->setData($newdata);
	}

	public function rotate($rotations = 0){//TODO maybe move to API
		$newdata = [];
		foreach ($this->getData() as $block){
			$newblock = clone $block;
			$newpos = $newblock->add($this->getOffset())->floor();//TEST IF FLOOR OR CEIL
			switch ($rotations % 4){
				case 1: {
					$newpos = $newpos->setComponents(-$newpos->getZ(), $newpos->getY(), $newpos->getX());
					break;
				}
				case 2: {
					$newpos = $newpos->setComponents(-$newpos->getX(), $newpos->getY(), -$newpos->getZ());
					break;
				}
				case 3: {
					$newpos = $newpos->setComponents(-$newpos->getZ(), $newpos->getY(), $newpos->getX());
					break;
				}
				default: {
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
}