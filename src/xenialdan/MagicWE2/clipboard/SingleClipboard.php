<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\clipboard;

use Generator;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use xenialdan\MagicWE2\helper\BlockEntry;
use xenialdan\MagicWE2\selection\Selection;

class SingleClipboard extends Clipboard
{
	/** @var BlockEntry[] */
	private array $entries = [];
	/** @var Selection */
	public Selection $selection;
	/** @var Vector3 */
	public Vector3 $position;

	/**
	 * SingleClipboard constructor.
	 * @param Vector3 $position
	 */
	public function __construct(Vector3 $position)
	{
		$this->position = $position->asVector3()->floor();
	}

	public function addEntry(int $x, int $y, int $z, BlockEntry $entry): void
	{
		$this->entries[World::blockHash($x, $y, $z)] = $entry;
	}

	public function clear(): void
	{
		$this->entries = [];
	}

	/**
	 * @param int|null $x
	 * @param int|null $y
	 * @param int|null $z
	 * @return Generator
	 */
	public function iterateEntries(?int &$x, ?int &$y, ?int &$z): Generator
	{
		foreach ($this->entries as $hash => $entry) {
			World::getBlockXYZ($hash, $x, $y, $z);
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
	public function serialize(): string
	{
		// TODO: Implement serialize() method.
		return serialize([
			$this->entries,
			$this->selection,
			$this->position
		]);
	}

	/**
	 * Constructs the object
	 * @link https://php.net/manual/en/serializable.unserialize.php
	 * @param string $data <p>
	 * The string representation of the object.
	 * </p>
	 * @return void
	 * @since 5.1
	 */
	public function unserialize($data)
	{
		// TODO: Implement unserialize() method.
		[
			$this->entries,
			$this->selection,
			$this->position
		] = unserialize($data/*, ['allowed_classes' => [BlockEntry::class, Selection::class, Vector3::class]]*/);
	}
}