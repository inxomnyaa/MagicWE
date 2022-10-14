<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\SimpleChunkManager;
use pocketmine\world\World;
use RuntimeException;
use Serializable;
use xenialdan\MagicWE2\clipboard\RevertClipboard;
use xenialdan\MagicWE2\exception\SelectionException;
use xenialdan\MagicWE2\selection\Selection;
use function igbinary_serialize;
use function igbinary_unserialize;

class AsyncWorld extends SimpleChunkManager implements Serializable{
//	/** @var CompoundTag[] *///TODO maybe CacheableNbt
//	protected array $tiles = [];

	public function __construct(){
		parent::__construct(World::Y_MIN, World::Y_MAX);
	}

	public static function fromRevertClipboard(RevertClipboard $clipboard) : self{
		$world = new self();
		foreach($clipboard->chunks as $hash => $chunk){
			World::getXZ($hash, $x, $z);
			$world->setChunk($x, $z, $clipboard->getWorld()->getChunk($x, $z));
		}
		return $world;
	}

	/**
	 * @return Chunk[]
	 */
	public function getChunks() : array{
		return $this->chunks;
	}

	/**
	 * May not be called from async task
	 * @throws SelectionException|RuntimeException
	 */
	public function copyChunks(Selection $selection) : void{
//		if(!$selection->isValid()) return;
		$this->cleanChunks();

		$shape = $selection->getShape();
		$aabb = $shape->getAABB();
		$world = $selection->getWorld();
		$maxX = $aabb->maxX >> Chunk::COORD_BIT_SIZE;
		$minX = $aabb->minX >> Chunk::COORD_BIT_SIZE;
		$maxZ = $aabb->maxZ >> Chunk::COORD_BIT_SIZE;
		$minZ = $aabb->minZ >> Chunk::COORD_BIT_SIZE;
		for($x = $minX; $x <= $maxX; $x++){
			for($z = $minZ; $z <= $maxZ; $z++){
				$chunk = $world->getChunk($x, $z);
				if($chunk === null){
					continue;
				}
				$this->setChunk($x, $z, $chunk);
//				print __METHOD__ . " Touched Chunk at: $x:$z" . PHP_EOL;
			}
		}
	}

	public function getBlockFullIdAt(int $x, int $y, int $z) : int{
		if($this->isInWorld($x, $y, $z) && ($chunk = $this->getChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE)) !== null){
			return $chunk->getFullBlock($x & Chunk::COORD_MASK, $y, $z & Chunk::COORD_MASK);
		}
		return 0;//TODO idk
	}

	public function serialize(){
		$chunks = [];
		foreach($this->getChunks() as $hash => $chunk){
			$chunks[$hash] = FastChunkSerializer::serializeTerrain($chunk);
		}
		return igbinary_serialize([$this->getMinY(), $this->getMaxY(), $chunks]);
	}

	public function unserialize(string $data){
		[$minY, $maxY, $chunks] = igbinary_unserialize($data);
		parent::__construct($minY, $maxY);//TODO test
		foreach($chunks as $hash => $chunk){
			World::getXZ($hash, $x, $z);
			$chunk = FastChunkSerializer::deserializeTerrain($chunk);
			$this->setChunk($x, $z, $chunk);
		}
	}
}