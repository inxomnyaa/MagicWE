<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2;

use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use xenialdan\MagicWE2\task\AsyncGetBlocksXYZTask;

class Selection{

	/** @var Level */
	private $level;
	/** @var Position */
	private $pos1;
	/** @var Position */
	private $pos2;

	public function __construct(Level $level, $minX = 0, $minY = 0, $minZ = 0, $maxX = 0, $maxY = 0, $maxZ = 0){
		$this->setLevel($level);
	}

	public function getAxisAlignedBB(): AxisAlignedBB{
		$minX = min(floor($this->getPos1()->getX()), floor($this->getPos2()->getX()));
		$minY = min(floor($this->getPos1()->getY()), floor($this->getPos2()->getY()));
		$minZ = min(floor($this->getPos1()->getZ()), floor($this->getPos2()->getZ()));
		$maxX = max(floor($this->getPos1()->getX()), floor($this->getPos2()->getX()));
		$maxY = max(floor($this->getPos1()->getY()), floor($this->getPos2()->getY()));
		$maxZ = max(floor($this->getPos1()->getZ()), floor($this->getPos2()->getZ()));
		return new AxisAlignedBB($minX, $minY, $minZ, $maxX, $maxY, $maxZ);
	}

	public function getLevel(){
		return $this->level;
	}

	public function setLevel(Level $level){
		$this->level = $level;
	}

	public function getPos1(){
		if (is_null($this->pos1)){
			throw new WEException("Position 1 is not set!");
		}
		return $this->pos1;
	}

	public function setPos1(Position $position){
		$this->pos1 = $position;
		return Loader::$prefix . TextFormat::GREEN . "Position 1 set";
	}

	public function getPos2(){
		if (is_null($this->pos2)){
			throw new WEException("Position 2 is not set!");
		}
		return $this->pos2;
	}

	public function setPos2(Position $position){
		$this->pos2 = $position;
		return Loader::$prefix . TextFormat::GREEN . "Position 2 set";
	}

	public function getMinVec3(){
		return new Vector3($this->getAxisAlignedBB()->minX, $this->getAxisAlignedBB()->minY, $this->getAxisAlignedBB()->minZ);
	}

	public function getMaxVec3(){
		return new Vector3($this->getAxisAlignedBB()->maxX, $this->getAxisAlignedBB()->maxY, $this->getAxisAlignedBB()->maxZ);
	}

	public function getSizeX(){
		return abs($this->getAxisAlignedBB()->maxX - $this->getAxisAlignedBB()->minX) + 1;
	}

	public function getSizeY(){
		return abs($this->getAxisAlignedBB()->maxY - $this->getAxisAlignedBB()->minY) + 1;
	}

	public function getSizeZ(){
		return abs($this->getAxisAlignedBB()->maxZ - $this->getAxisAlignedBB()->minZ) + 1;
	}

	public function getTotalCount(){
		return $this->getSizeX() * $this->getSizeY() * $this->getSizeZ();//TODO correct number on custom election shapes
	}

	/**
	 * Returns the blocks by their actual position
	 * @param Block[] $filterblocks If not empty, applying a filter on the block list
	 * @return array
	 */
	public function getBlocksXYZ(Block ...$filterblocks){
		$blocks = [];
		for ($x = floor($this->getAxisAlignedBB()->minX); $x <= floor($this->getAxisAlignedBB()->maxX); $x++){
			for ($y = floor($this->getAxisAlignedBB()->minY); $y <= floor($this->getAxisAlignedBB()->maxY); $y++){
				for ($z = floor($this->getAxisAlignedBB()->minZ); $z <= floor($this->getAxisAlignedBB()->maxZ); $z++){
					$block = $this->getLevel()->getBlock(new Position($x, $y, $z, $this->getLevel()));
					if (empty($filterblocks)) $blocks[(int)$x][(int)$y][(int)$z] = $block;
					else{
						foreach ($filterblocks as $filterblock){
							if ($block->getId() === $filterblock->getId() && $block->getDamage() === $filterblock->getDamage())
								$blocks[(int)$x][(int)$y][(int)$z] = $block;
						}
					}
				}
			}
		}
		return $blocks;
	}

	/**
	 * Returns the blocks by their actual position
	 * @param Block[] $filterblocks If not empty, applying a filter on the block list
	 * @return array
	 */
	public function getAsyncBlocksXYZ(Block ...$filterblocks){
		$blocks = [];
		/*for ($x = floor($this->getAxisAlignedBB()->minX); $x <= floor($this->getAxisAlignedBB()->maxX); $x++){
			for ($y = floor($this->getAxisAlignedBB()->minY); $y <= floor($this->getAxisAlignedBB()->maxY); $y++){
				for ($z = floor($this->getAxisAlignedBB()->minZ); $z <= floor($this->getAxisAlignedBB()->maxZ); $z++){
					$block = $this->getLevel()->getBlock(new Position($x, $y, $z, $this->getLevel()));
					if (empty($filterblocks)) $blocks[(int)$x][(int)$y][(int)$z] = $block;
					else{
						foreach ($filterblocks as $filterblock){
							if ($block->getId() === $filterblock->getId() && $block->getDamage() === $filterblock->getDamage())
								$blocks[(int)$x][(int)$y][(int)$z] = $block;
						}
					}
				}
			}
		}*/
		Server::getInstance()->getScheduler()->scheduleAsyncTask($asynctask = new AsyncGetBlocksXYZTask($this, $filterblocks));
		return $asynctask->getResult();
	}

	/**
	 * Returns the blocks by their relative position to the minX;minY;minZ position
	 * @param Block[] $filterblocks If not empty, applying a filter on the block list
	 * @return array
	 */
	public function getBlocksRelativeXYZ(Block ...$filterblocks){
		$blocks = [];
		for ($x = floor($this->getAxisAlignedBB()->minX), $rx = 0; $x <= floor($this->getAxisAlignedBB()->maxX); $x++, $rx++){
			for ($y = floor($this->getAxisAlignedBB()->minY), $ry = 0; $y <= floor($this->getAxisAlignedBB()->maxY); $y++, $ry++){
				for ($z = floor($this->getAxisAlignedBB()->minZ), $rz = 0; $z <= floor($this->getAxisAlignedBB()->maxZ); $z++, $rz++){
					$block = $this->getLevel()->getBlock(new Position($x, $y, $z, $this->getLevel()));
					if (empty($filterblocks)) $blocks[(int)$rx][(int)$ry][(int)$rz] = $block;
					else{
						foreach ($filterblocks as $filterblock){
							if ($block->getId() === $filterblock->getId() && $block->getDamage() === $filterblock->getDamage())
								$blocks[(int)$rx][(int)$ry][(int)$rz] = $block;
						}
					}
				}
			}
		}
		return $blocks;
	}

	/**
	 * TODO optimize
	 * e.g. do not use + 16 but % 16 or sth like that
	 *
	 * @return array
	 */
	public function getTouchedChunks(): array {
		$maxX = floor($this->getAxisAlignedBB()->maxX);
		$minX = floor($this->getAxisAlignedBB()->minX);
		$maxZ = floor($this->getAxisAlignedBB()->maxZ);
		$minZ = floor($this->getAxisAlignedBB()->minZ);
		$touchedChunks = [];
		for($x = $minX; $x <= $maxX + 16; $x += 16) {
			for($z = $minZ; $z <= $maxZ + 16; $z += 16) {
				$chunk = $this->getLevel()->getChunk($x >> 4, $z >> 4, true);
				$touchedChunks[Level::chunkHash($x >> 4, $z >> 4)] = $chunk->fastSerialize();
			}
		}
		var_dump(count($touchedChunks));
		return $touchedChunks;
	}
}