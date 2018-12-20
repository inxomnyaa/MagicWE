<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2;

use pocketmine\block\Block;
use pocketmine\level\ChunkManager;
use pocketmine\level\Level;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use xenialdan\MagicWE2\shape\Shape;

class Clipboard extends Shape
{
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
	private $blocks;
    /** @var Vector3 */
    public $offset;

    /**
     * Clipboard constructor.
     * @param Level $level
     * @param Block[] $blocks
     */
    public function __construct(Level $level, array $blocks = [])
    {
        parent::__construct($level, []);
        $this->setBlocks($blocks);
	}

	/**
	 * @param ChunkManager $manager
	 * @param array $filterblocks
	 * @param int $flags
	 * @return \Generator|Block
	 * @throws \Exception
	 */
	public function getBlocks(ChunkManager $manager, array $filterblocks = [], int $flags = API::FLAG_BASE): \Generator {
        $offset = $this->offset;
        $cBasePos = $this->pos1;
        $startPos = $cBasePos->add($offset);
		foreach ($this->blocks as $block) {
            if (API::hasFlag($flags, API::FLAG_PASTE_WITHOUT_AIR) && $block->getId() === Block::AIR)
                continue;
            if (!API::hasFlag($flags, API::FLAG_POSITION_RELATIVE)) {
                $vector3 = $startPos->add($block->subtract($cBasePos));
                $block->setComponents($vector3->x, $vector3->y, $vector3->z);
            }
            //NON-MODIFIED
			yield $block;
		}
	}

    /**
	 * Replaces the block array with the given blocks
	 * @param Block[] $blocks
	 */
    public function setBlocks(array $blocks)
    {//TODO make this not lag the main thread
        $this->blocks = [];
        $this->pushBlocks($blocks);
	}

	/**
	 * Pushes a block to the end of the block array
	 * Ignores duplicated blocks TODO
	 * @param Block $block
	 */
    public function pushBlock(Block $block)
    {//TODO make this not lag the main thread
		$this->blocks[] = $block;
        if (is_null($this->pos1))
            $this->pos1 = $block->asVector3();
        if (is_null($this->pos2))
            $this->pos2 = $block->asVector3();
        if ($this->pos1->x < $block->x)
            $this->pos1->setComponents($block->x, $this->pos1->y, $this->pos1->z);
        if ($this->pos1->y < $block->y)
            $this->pos1->setComponents($this->pos1->x, $block->y, $this->pos1->z);
        if ($this->pos1->y < $block->y)
            $this->pos1->setComponents($this->pos1->x, $this->pos1->y, $block->z);
        if ($this->pos2->x > $block->x)
            $this->pos2->setComponents($block->x, $this->pos2->y, $this->pos2->z);
        if ($this->pos2->y > $block->y)
            $this->pos2->setComponents($this->pos2->x, $block->y, $this->pos2->z);
        if ($this->pos2->y > $block->y)
            $this->pos2->setComponents($this->pos2->x, $this->pos2->y, $block->z);
	}

	/**
	 * Pushes a block array to the end of the block array
	 * Ignores duplicated blocks TODO
	 * @param Block[] $blocks
	 */
    public function pushBlocks(array $blocks)
    {//TODO make this not lag the main thread
        foreach ($blocks as $block)
            $this->pushBlock($block);
	}

	/**
	 * Clears the block array
	 */
	public function clear() {
		$this->blocks = [];
	}

	public function getTotalCount(): int {
		return count($this->blocks);
	}

    /**
     * @return array
     * @throws \Exception
     */
    public function getTouchedChunks(): array
    {
        $this->recalculateAABB();
        $maxX = $this->getMaxVec3()->x >> 4;
        $minX = $this->getMinVec3()->x >> 4;
        $maxZ = $this->getMaxVec3()->z >> 4;
        $minZ = $this->getMinVec3()->z >> 4;
        print "from $minX:$minZ to $maxX:$maxZ" . PHP_EOL;
        $touchedChunks = [];
        for ($x = $minX; $x <= $maxX; $x++) {
            for ($z = $minZ; $z <= $maxZ; $z++) {
                $chunk = $this->getLevel()->getChunk($x, $z, true);
                if ($chunk === null) {
                    continue;
                }
                print "Touched Chunk at: $x:$z" . PHP_EOL;
                $touchedChunks[Level::chunkHash($x, $z)] = $chunk->fastSerialize();
            }
        }
        print "Touched chunks count: " . count($touchedChunks) . PHP_EOL;;
        return $touchedChunks;
    }

    public function recalculateAABB()
    {//TODO only use offset when API::FLAG_UNCENTERED is set? (currently setting offset to [0,0,0] in API to avoid this behaviour)
        if ($this->isValid())
            $this->aabb = new AxisAlignedBB(
                min($this->pos1->x, $this->pos2->x) + $this->offset->x,
                min($this->pos1->y, $this->pos2->y) + $this->offset->y,
                min($this->pos1->z, $this->pos2->z) + $this->offset->z,
                max($this->pos1->x, $this->pos2->x) + $this->offset->x,
                max($this->pos1->y, $this->pos2->y) + $this->offset->y,
                max($this->pos1->z, $this->pos2->z) + $this->offset->z
            );
        else print "not valid";
    }

    /**
     * @return Vector3
     */
    public function getOffset(): Vector3
    {
        return $this->offset;
    }

    /**
     * @param Vector3 $offset
     */
    public function setOffset(Vector3 $offset): void
    {
        $this->offset = $offset;
    }

	/**
	 * String representation of object
	 * @link http://php.net/manual/en/serializable.serialize.php
	 * @return string the string representation of the object or null
	 * @since 5.1.0
	 */
	public function serialize() {
		return serialize([
			$this->levelid,
			$this->pos1,
			$this->pos2,
			$this->uuid,
            $this->offset,
			$this->blocks
		]);
	}

	/**
	 * Constructs the object
	 * @link http://php.net/manual/en/serializable.unserialize.php
	 * @param string $serialized <p>
	 * The string representation of the object.
	 * </p>
	 * @return void
	 * @since 5.1.0
	 */
	public function unserialize($serialized) {
		[
			$this->levelid,
			$this->pos1,
			$this->pos2,
			$this->uuid,
            $this->offset,
			$this->blocks
		] = unserialize($serialized);
	}
}