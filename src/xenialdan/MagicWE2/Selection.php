<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2;

use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat;
use pocketmine\utils\UUID;

class Selection{

	/** @var Level */
	private $level;
	/** @var Position */
	private $pos1;
	/** @var Position */
	private $pos2;
	/** @var UUID */
	private $uuid;

	public function __construct(Level $level, $minX = 0, $minY = 0, $minZ = 0, $maxX = 0, $maxY = 0, $maxZ = 0){
		$this->setLevel($level);
		$this->setUUID(UUID::fromRandom());
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
			throw new \Exception("Position 1 is not set!");
		}
		return $this->pos1;
	}

	public function setPos1(Position $position){
		$this->pos1 = $position;
		return Loader::$prefix . TextFormat::GREEN . "Position 1 set to X: " . $position->getX() . " Y: " . $position->getY() . " Z: " . $position->getZ();
	}

	public function getPos2(){
		if (is_null($this->pos2)){
			throw new \Exception("Position 2 is not set!");
		}
		return $this->pos2;
	}

	public function setPos2(Position $position){
		$this->pos2 = $position;
		return Loader::$prefix . TextFormat::GREEN . "Position 2 set to X: " . $position->getX() . " Y: " . $position->getY() . " Z: " . $position->getZ();
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
	 * @param int $flags
	 * @param Block[] $filterblocks If not empty, applying a filter on the block list
	 * @return array
	 */
	public function getBlocks(int $flags, Block ...$filterblocks){
		$blocks = [];
		for ($x = floor($this->getAxisAlignedBB()->minX); $x <= floor($this->getAxisAlignedBB()->maxX); $x++){
			for ($y = floor($this->getAxisAlignedBB()->minY); $y <= floor($this->getAxisAlignedBB()->maxY); $y++){
				for ($z = floor($this->getAxisAlignedBB()->minZ); $z <= floor($this->getAxisAlignedBB()->maxZ); $z++){
					$block = $this->getLevel()->getBlock(new Vector3($x, $y, $z));
					#$block->setComponents((int)$x,(int)$y,(int)$z);
					$block->position(new Position((int)$x, (int)$y, (int)$z, $this->getLevel()));
					if (empty($filterblocks)) $blocks[] = $block;
					else{
						foreach ($filterblocks as $filterblock){
							if (($block->getId() === $filterblock->getId()) && ((API::hasFlag($flags, API::FLAG_VARIANT) && $block->getVariant() === $filterblock->getVariant()) || (!API::hasFlag($flags, API::FLAG_VARIANT) && ($block->getDamage() === $filterblock->getDamage() || API::hasFlag($flags, API::FLAG_KEEP_META)))))
								$blocks[] = $block;
						}
					}
				}
			}
		}
		return $blocks;
	}

	/**
	 * Returns the blocks by their relative position to the minX;minY;minZ position
	 * @param int $flags
	 * @param Block[] $filterblocks If not empty, applying a filter on the block list
	 * @return array
	 */
	public function getBlocksRelative(int $flags, Block ...$filterblocks){
		$blocks = [];
		for ($x = floor($this->getAxisAlignedBB()->minX), $rx = 0; $x <= floor($this->getAxisAlignedBB()->maxX); $x++, $rx++){
			for ($y = floor($this->getAxisAlignedBB()->minY), $ry = 0; $y <= floor($this->getAxisAlignedBB()->maxY); $y++, $ry++){
				for ($z = floor($this->getAxisAlignedBB()->minZ), $rz = 0; $z <= floor($this->getAxisAlignedBB()->maxZ); $z++, $rz++){
					$block = $this->getLevel()->getBlock(new Vector3($x, $y, $z));
					$block->position(new Position((int)$rx, (int)$ry, (int)$rz, $this->getLevel()));
					if (empty($filterblocks)) $blocks[] = $block;
					else{
						foreach ($filterblocks as $filterblock){
							if (($block->getId() === $filterblock->getId()) && ((API::hasFlag($flags, API::FLAG_VARIANT) && $block->getVariant() === $filterblock->getVariant()) || (!API::hasFlag($flags, API::FLAG_VARIANT) && $block->getDamage() === $filterblock->getDamage())))
								$blocks[] = $block;
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
	public function getTouchedChunks(): array{
		$maxX = floor($this->getAxisAlignedBB()->maxX);
		$minX = floor($this->getAxisAlignedBB()->minX);
		$maxZ = floor($this->getAxisAlignedBB()->maxZ);
		$minZ = floor($this->getAxisAlignedBB()->minZ);
		$touchedChunks = [];
		for ($x = $minX; $x <= $maxX + 16; $x += 16){
			for ($z = $minZ; $z <= $maxZ + 16; $z += 16){
				$chunk = $this->getLevel()->getChunk($x >> 4, $z >> 4, true);
				$touchedChunks[Level::chunkHash($x >> 4, $z >> 4)] = $chunk->fastSerialize();
			}
		}
		return $touchedChunks;
	}

	/**
	 * @return array
	 */
	public function __serialize(){
		$array = [];
		return array_merge($array, (array)$this->getAxisAlignedBB(), [
			"minx" => $this->getMinVec3()->getX(),
			"miny" => $this->getMinVec3()->getY(),
			"minz" => $this->getMinVec3()->getZ(),
			"maxx" => $this->getMaxVec3()->getX(),
			"maxy" => $this->getMaxVec3()->getY(),
			"maxz" => $this->getMaxVec3()->getZ(),
			"levelname" => $this->getLevel()->getName(),
			"totalcount" => $this->getTotalCount()
		]);
	}

	private function setUUID(UUID $uuid){
		$this->uuid = $uuid;
	}

	/**
	 * @return UUID
	 */
	public function getUUID(){
		return $this->uuid;
	}
}