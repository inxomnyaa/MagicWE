<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use BadMethodCallException;
use pocketmine\math\Vector3;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\SubChunk;
use pocketmine\world\utils\SubChunkExplorer;
use pocketmine\world\World;

class SubChunkIterator extends SubChunkExplorer
{
	/** @var AsyncWorld */
	protected $world;

	public function __construct(AsyncWorld $manager)
	{
		parent::__construct($manager);
	}

	public function getManager(): AsyncWorld
	{
		return $this->world;
	}

	public function getCurrentSubChunk(): SubChunk
	{
		if ($this->currentSubChunk === null) {
			throw new BadMethodCallException("Tried to access unknown Chunk");
		}
		return $this->currentSubChunk;
	}

	public function getCurrentChunk(): Chunk
	{
		if ($this->currentChunk === null) {
			throw new BadMethodCallException("Tried to access unknown Chunk");
		}
		return $this->currentChunk;
	}

	/**
	 * @param Vector3 $vector
	 * @return int
	 * @throws BadMethodCallException
	 * @throws BadMethodCallException
	 */
	public function getBlock(Vector3 $vector): int
	{
		return $this->getBlockAt($vector->getFloorX(), $vector->getFloorY(), $vector->getFloorZ());
	}

	/**
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @return int
	 * @throws BadMethodCallException
	 * @throws BadMethodCallException
	 */
	public function getBlockAt(int $x, int $y, int $z): int
	{
		$y = (int)min(World::Y_MAX - 1, max(0, $y));
		$this->moveTo($x, $y, $z);
		return $this->getCurrentSubChunk()->getFullBlock($x & 0x0f, $y & 0x0f, $z & 0x0f);
	}

	/**
	 * @param Vector3 $vector
	 * @param int $block
	 * @throws BadMethodCallException
	 * @throws BadMethodCallException
	 */
	public function setBlock(Vector3 $vector, int $block): void
	{
		$this->setBlockAt($vector->getFloorX(), $vector->getFloorY(), $vector->getFloorX(), $block);
	}

	/**
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @param int $block
	 * @throws BadMethodCallException
	 * @throws BadMethodCallException
	 */
	public function setBlockAt(int $x, int $y, int $z, int $block): void
	{
		$y = (int)min(World::Y_MAX - 1, max(0, $y));
		$this->moveTo($x, $y, $z);
		$this->getCurrentSubChunk()->setFullBlock($x & 0x0f, $y & 0x0f, $z & 0x0f, $block);
	}

}