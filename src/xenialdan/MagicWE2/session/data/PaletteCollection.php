<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\session\data;

use pocketmine\item\Item;
use pocketmine\nbt\UnexpectedTagTypeException;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\exception\PaletteException;
use xenialdan\MagicWE2\helper\BlockPalette;
use xenialdan\MagicWE2\session\UserSession;
use function json_encode;

final class PaletteCollection
{
	/** @var array<string, BlockPalette> */
	public array $palettes = [];
	private UserSession $session;

	public function __construct(UserSession $session)
	{
		$this->session = $session;
	}

	/**
	 * @return UserSession
	 */
	public function getSession(): UserSession
	{
		return $this->session;
	}

	/** @return BlockPalette[] */
	public function getAll(): array
	{
		return $this->palettes;
	}

	public function getPalette(string $id): ?BlockPalette
	{
		return $this->palettes[$id];//TODO allow finding by custom name
	}

	public function addPalette(BlockPalette $palette, string $id): void
	{
		$this->palettes[$id] = $palette;
	}

	public function toJson(): string
	{
		//TODO
		$queries = [];
		foreach ($this->getAll() as $id => $palette) {
			$queries[$id] = $palette->toStringArray();
		}
		return json_encode(
			$queries
		);
	}

	/**
	 * @param Item $item
	 * @return BlockPalette
	 * @throws PaletteException
	 * @throws UnexpectedTagTypeException
	 */
	public function getPaletteFromItem(Item $item): BlockPalette
	{
		if ((($entry = $item->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE_PALETTE))) !== null) {
			$id = $entry->getString("id");//todo check if not found
			$palette = $this->getPalette($id);
			if ($palette instanceof BlockPalette) {
				return $palette;
			}
			throw new PaletteException("No palette with the id $id could be found!");
		}
		throw new PaletteException("The item is not a valid palette!");
	}
}
