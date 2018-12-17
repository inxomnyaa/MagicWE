<?php

namespace xenialdan\MagicWE2\shape;

use pocketmine\block\Block;
use pocketmine\level\ChunkManager;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\AsyncChunkManager;

class Flood extends Shape {
	private $limit = 10000;
	/** @var Block[] */
	private $walked = [];
	/** @var Block[] */
	private $nextToCheck = [];
	/** @var int */
	private $y;

	/**
	 * Square constructor.
	 * @param Level $level
	 * @param array $options
	 */
	public function __construct(Level $level, array $options) {
		parent::__construct($level, $options);
		$this->limit = $options["limit"] ?? $this->limit;
	}

	/**
	 * Returns the blocks by their actual position
	 * @param Level|AsyncChunkManager|ChunkManager $manager The level or AsyncChunkManager
	 * @param Block[] $filterblocks If not empty, applying a filter on the block list
	 * @param int $flags
	 * @return \Generator|Block
	 * @throws \Exception
	 */
	public function getBlocks(ChunkManager $manager, array $filterblocks = [], int $flags = API::FLAG_BASE): \Generator {
		$this->validateChunkManager($manager);
		$this->y = $this->getCenter()->getY();
		$block = $manager->getBlockAt($this->getCenter()->x, $this->getCenter()->y, $this->getCenter()->z);
		$block->setComponents($this->getCenter()->x, $this->getCenter()->y, $this->getCenter()->z);
		var_dump($block);
		$this->walked[] = $block;
		$this->nextToCheck = $this->walked;
		foreach ($this->walk($manager) as $block) {
			yield $block;
		}
	}

	/**
	 * @param Level|AsyncChunkManager|ChunkManager $manager
	 * @return Block[]
	 */
	private function walk(ChunkManager $manager): array {
		/** @var Block[] $walkTo */
		$walkTo = [];
		foreach ($this->nextToCheck as $next) {
			$sides = iterator_to_array($this->getHorizontalSides($manager, $next));
			$walkTo = array_merge($walkTo, array_filter($sides, function (Block $side) use ($walkTo) {
				return $side->getId() === 0 && !in_array($side, $walkTo) && !in_array($side, $this->walked) && !in_array($side, $this->nextToCheck) && $side->distanceSquared($this->getCenter()) <= ($this->limit / pi());
			}));
		}
		$this->walked = array_merge($this->walked, $walkTo);
		$this->nextToCheck = $walkTo;
		if (!empty($this->nextToCheck)) $this->walk($manager);
		return $this->walked;
	}

	/**
	 * @param Level|AsyncChunkManager|ChunkManager $manager
	 * @param Vector3 $vector3
	 * @return \Generator|Block
	 */
	private function getHorizontalSides(ChunkManager $manager, Vector3 $vector3): \Generator {
		$side = $vector3->getSide(Vector3::SIDE_NORTH);
		$block = $manager->getBlockAt($side->x, $side->y, $side->z);
		$block->setComponents($side->x, $side->y, $side->z);
		yield $block;
		$side = $vector3->getSide(Vector3::SIDE_SOUTH);
		$block = $manager->getBlockAt($side->x, $side->y, $side->z);
		$block->setComponents($side->x, $side->y, $side->z);
		yield $block;
		$side = $vector3->getSide(Vector3::SIDE_WEST);
		$block = $manager->getBlockAt($side->x, $side->y, $side->z);
		$block->setComponents($side->x, $side->y, $side->z);
		yield $block;
		$side = $vector3->getSide(Vector3::SIDE_EAST);
		$block = $manager->getBlockAt($side->x, $side->y, $side->z);
		$block->setComponents($side->x, $side->y, $side->z);
		yield $block;
	}

	/**
	 * @deprecated TODO rewrite
	 * @param int $flags
	 * @param Block[] $filterblocks
	 * @return array
	 * @throws \Exception
	 */
	public function getBlocksOld(int $flags, Block ...$filterblocks) {//TODO use filterblocks
		$this->y = $this->getCenter()->getY();
		$this->walked[] = $this->getLevel()->getBlock($this->getCenter());
		$this->nextToCheck[] = $this->getLevel()->getBlock($this->getCenter());
		return $this->walkOld();
	}

	private function walkOld() {
		/** @var Block[] $walkTo */
		$walkTo = [];
		/** @var Block $next */
		foreach ($this->nextToCheck as $next) {
			$sides = $next->getHorizontalSides();
			$walkTo = array_merge($walkTo, array_filter($sides, function (Block $side) use ($walkTo) {
				return $side->getId() === 0 && !in_array($side, $walkTo) && !in_array($side, $this->walked) && !in_array($side, $this->nextToCheck) && $side->distanceSquared($this->getCenter()) <= ($this->limit / pi());
			}));
		}
		$this->walked = array_merge($this->walked, $walkTo);
		$this->nextToCheck = $walkTo;
		if (!empty($this->nextToCheck)) $this->walk();
		return $this->walked;
	}

	public function setCenter(Vector3 $center) {
		$this->center = $center;
		try {
			$this->setPos1(new Position($center->getX(), $center->getY(), $center->getZ(), $this->getLevel()));
		} catch (\Exception $e) {
		}
		try {
			$this->setPos2(new Position($center->getX(), $center->getY(), $center->getZ(), $this->getLevel()));
		} catch (\Exception $e) {
		}
	}

	public function getTotalCount() {
		return $this->limit;
	}
}