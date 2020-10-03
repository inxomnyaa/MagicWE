<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\clipboard;

use Exception;
use pocketmine\Server;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use Serializable;
use xenialdan\MagicWE2\helper\AsyncChunkManager;

abstract class Clipboard implements Serializable
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

	/** @var int|null */
	public ?int $levelid;
	/** @var string */
	public string $customName = "";

	/**
	 * Creates a chunk manager used for async editing
	 * @param Chunk[] $chunks
	 * @return AsyncChunkManager
	 */
	public static function getChunkManager(array $chunks): AsyncChunkManager
	{
		$manager = new AsyncChunkManager(0);
		foreach ($chunks as $chunk) {
            $manager->setChunk($chunk->getX(), $chunk->getZ(), $chunk);
        }
        return $manager;
    }

    /**
     * @return World
     * @throws Exception
     */
    public function getWorld(): World
    {
        if (is_null($this->levelid)) {
            throw new Exception("Level is not set!");
        }
		$level = Server::getInstance()->getWorldManager()->getWorld($this->levelid);
        if (is_null($level)) {
            throw new Exception("Level is not found!");
        }
        return $level;
    }

    /**
     * @param World $level
     */
    public function setWorld(World $level): void
    {
        $this->levelid = $level->getId();
    }

    /**
     * @return int
     */
    public function getWorldId(): int
    {
        return $this->levelid;
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