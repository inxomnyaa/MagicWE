<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\clipboard;

use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\Position;
use pocketmine\world\World;

class RevertClipboard extends Clipboard
{
	/**
	 * @var Chunk[]
	 * @phpstan-var array<int, Chunk>
	 */
	public $chunks = [];
	/**
	 * @var array[]
	 * @phpstan-var array<array{int, Position|null}>
	 */
	public $blocksAfter;

	/**
	 * RevertClipboard constructor.
	 * @param int $worldId
	 * @param Chunk[] $chunks
	 * @param array[] $blocksAfter //CHANGED AS HACK
	 * @phpstan-param array<array{int, Position|null}> $blocksAfter
	 */
	public function __construct(int $worldId, array $chunks = [], array $blocksAfter = [])
	{
		$this->worldId = $worldId;
		$this->chunks = $chunks;
		$this->blocksAfter = $blocksAfter;
	}

	/**
	 * String representation of object
	 * @link http://php.net/manual/en/serializable.serialize.php
	 * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize()
    {
        $chunks = [];
        foreach ($this->chunks as $chunk)
			$chunks[World::chunkHash($chunk->getX(), $chunk->getZ())] = FastChunkSerializer::serialize($chunk);
        return serialize([
			$this->worldId,
			$chunks,
			$this->blocksAfter
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
	 * @noinspection PhpMissingParamTypeInspection
	 */
    public function unserialize($serialized)
    {
		[
			$this->worldId,
			$chunks,
			$this->blocksAfter
		] = unserialize($serialized/*, ['allowed_classes' => [__CLASS__]]*/);//TODO test pm4
		foreach ($chunks as $hash => $chunk)
			$this->chunks[$hash] = FastChunkSerializer::deserialize($chunk);
	}
}