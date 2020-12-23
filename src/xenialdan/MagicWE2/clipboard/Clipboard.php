<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\clipboard;

use Exception;
use pocketmine\Server;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use Serializable;
use xenialdan\MagicWE2\exception\SelectionException;
use xenialdan\MagicWE2\helper\AsyncChunkManager;

abstract class Clipboard implements Serializable
{
	public const DIRECTION_DEFAULT = 1;

	public const FLIP_X = 0x01;
	public const FLIP_WEST = 0x01;
	public const FLIP_EAST = 0x01;
	public const FLIP_Y = 0x02;
	public const FLIP_UP = 0x02;
	public const FLIP_DOWN = 0x02;
	public const FLIP_Z = 0x03;
	public const FLIP_NORTH = 0x03;
	public const FLIP_SOUTH = 0x03;

	/** @var int|null */
	public $worldId;
	/** @var string */
	public $customName = "";

	/**
	 * Creates a chunk manager used for async editing
	 * @param Chunk[] $chunks
	 * @return AsyncChunkManager
	 */
	public static function getChunkManager(array $chunks): AsyncChunkManager
	{
		$manager = new AsyncChunkManager();
		foreach ($chunks as $hash => $chunk) {
			World::getXZ($hash, $x, $z);
			$manager->setChunk($x, $z, $chunk);
		}
		return $manager;
	}

    /**
     * @return World
     * @throws Exception
     */
    public function getWorld(): World
	{
		if (is_null($this->worldId)) {
			throw new SelectionException("World is not set!");
		}
		$world = Server::getInstance()->getWorldManager()->getWorld($this->worldId);
		if (is_null($world)) {
			throw new SelectionException("World is not found!");
		}
		return $world;
	}

	/**
	 * @param World $world
	 */
	public function setWorld(World $world): void
	{
		$this->worldId = $world->getId();
	}

    /**
     * @return int
     */
    public function getWorldId(): int
    {
		return $this->worldId;
    }

    /**
     * @return string
     */
    public function getCustomName(): string
    {
        return $this->customName;
    }

    /**
     * @param string $customName
     */
    public function setCustomName(string $customName): void
    {
        $this->customName = $customName;
    }
}