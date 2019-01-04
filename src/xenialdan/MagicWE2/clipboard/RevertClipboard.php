<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\clipboard;

use pocketmine\level\format\Chunk;
use pocketmine\level\Level;

class RevertClipboard extends Clipboard
{
    /**
     * RevertClipboard constructor.
     * @param int $levelId
     * @param Chunk[] $chunks
     */
    public function __construct(int $levelId, array $chunks = [])
    {
        $this->levelid = $levelId;
        $this->chunks = $chunks;
    }

    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize()
    {
        return serialize([
            $this->levelid,
            $this->getTouchedChunks()
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
    public function unserialize($serialized)
    {
        [
            $this->levelid,
            $chunks
        ] = unserialize($serialized);
        foreach ($chunks as $hash => $chunk)
            $this->chunks[$hash] = Chunk::fastDeserialize($chunk);
    }

    /**
     * @return array
     */
    public function getTouchedChunks(): array
    {
        $touchedChunks = [];
        foreach ($this->chunks as $chunk)
            $touchedChunks[Level::chunkHash($chunk->getX(), $chunk->getZ())] = $chunk->fastSerialize();
        return $touchedChunks;
    }
}