<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\clipboard;

use Generator;
use pocketmine\level\Level;
use xenialdan\MagicWE2\helper\BlockEntry;
use xenialdan\MagicWE2\selection\Selection;

class SingleClipboard extends Clipboard
{
    /** @var BlockEntry[] */
    private $entries = [];
    /** @var Selection */
    public $selection;

    public function addEntry(int $x, int $y, int $z, BlockEntry $entry): void
    {
        $this->entries[Level::blockHash($x, $y, $z)] = $entry;
    }

    /**
     * @param int $x
     * @param int $y
     * @param int $z
     * @return Generator|BlockEntry[]
     */
    public function iterateEntries(&$x, &$y, &$z): Generator
    {
        foreach ($this->entries as $hash => $entry) {
            Level::getBlockXYZ($hash, $x, $y, $z);
            yield $entry;
        }
    }

    public function getTotalCount(): int
    {
        return count($this->entries);
    }

    /**
     * String representation of object
     * @link https://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1
     */
    public function serialize()
    {
        // TODO: Implement serialize() method.
        return serialize([
            $this->entries,
            $this->selection
        ]);
    }

    /**
     * Constructs the object
     * @link https://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     * @return void
     * @since 5.1
     */
    public function unserialize($serialized)
    {
        // TODO: Implement unserialize() method.
        [
            $this->entries,
            $this->selection
        ] = unserialize($serialized);
    }
}