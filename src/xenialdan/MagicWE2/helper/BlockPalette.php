<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use Generator;
use InvalidArgumentException;
use JsonException;
use pocketmine\block\BlockFactory;
use pocketmine\block\utils\InvalidBlockStateException;
use pocketmine\item\Item;
use pocketmine\item\LegacyStringToItemParserException;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\UnexpectedTagTypeException;
use xenialdan\MagicWE2\exception\BlockQueryAlreadyParsedException;

class BlockPalette
{
	/**
	 * Contains BlockQuery[]
	 * @var WeightedRandom
	 */
	public WeightedRandom $randomBlockQueries;
	public string $name = "";

	/**
	 * BlockPalette constructor.
	 * @param string $name
	 */
	public function __construct(string $name = "")
	{
		if ($name !== "") $this->name = $name;
		$this->randomBlockQueries = new WeightedRandom();
	}

	/**
	 * @param string $blocksQuery
	 * @return BlockPalette
	 * @throws BlockQueryAlreadyParsedException
	 * @throws InvalidArgumentException
	 * @throws InvalidBlockStateException
	 * @throws LegacyStringToItemParserException
	 * @throws UnexpectedTagTypeException
	 * @throws \xenialdan\MagicWE2\exception\InvalidBlockStateException
	 */
	public static function fromString(string $blocksQuery): BlockPalette
	{
		$palette = self::CREATE();

		$pregSplit = preg_split('/,(?![^\[]*])/', trim($blocksQuery), -1, PREG_SPLIT_NO_EMPTY);
		if (!is_array($pregSplit)) throw new InvalidArgumentException("Regex matching failed");
		foreach ($pregSplit as $query) {
			// How to code ugly 101: https://3v4l.org/2KfNW
			preg_match_all('/([\w:]+)(?:\[([\w=,]*)])?/m', $query, $matches, PREG_SET_ORDER);
			[$blockMatch, $extraMatch] = [$matches[0] ?? [], $matches[1] ?? []];
			$blockMatch += [null, null, null];
			$extraMatch += [null, null];
			[[$fullBlockQuery, $blockId, $blockStatesQuery], [$fullExtraQuery, $weight]] = [$blockMatch, $extraMatch];
			$palette->addBlockQuery((new BlockQuery($query, $fullBlockQuery, $blockId, $blockStatesQuery, $fullExtraQuery, $weight))->parse());
		}
		$palette->randomBlockQueries->setup();

		return $palette;
	}

	public function addBlockQuery(BlockQuery $query): void
	{
		$this->randomBlockQueries->add($query, $query->weight);
	}

	//TODO addBlock

	/**
	 * @param int $amount
	 * @return Generator
	 * @throws InvalidArgumentException
	 */
	public function blocks(int $amount = 1): Generator
	{
		if ($amount < 1) throw new InvalidArgumentException('$amount must be greater than 0');
		$blockFactory = BlockFactory::getInstance();
		/** @var BlockQuery $blockQuery */
		foreach ($this->randomBlockQueries->generate($amount) as $blockQuery) {//TODO yield from?
			yield $blockFactory->fromFullBlock($blockQuery->blockFullId);//TODO yield blockFullId and do not yield Block?
		}
	}

	/**
	 * @return Generator
	 */
	public function palette(): Generator
	{
		$blockFactory = BlockFactory::getInstance();
		/** @var BlockQuery $blockQuery */
		foreach ($this->randomBlockQueries->indexes() as $blockQuery) {//TODO yield from?
			yield $blockFactory->fromFullBlock($blockQuery->blockFullId);//TODO yield blockFullId and do not yield Block? prob nah
		}
	}

	public function count(): int
	{
		return $this->randomBlockQueries->count();
	}

	public function empty(): bool
	{
		return $this->randomBlockQueries->count() === 0;
	}

	/**
	 * @return string
	 * @throws JsonException
	 */
	public function encode(): string
	{
		$e = [];
		/** @var BlockQuery $blockQuery */
		foreach ($this->randomBlockQueries->generate($this->randomBlockQueries->count()) as $blockQuery)
			$e[] = $blockQuery->blockFullId;
		return json_encode($e, JSON_THROW_ON_ERROR);
	}

	/**
	 * @param string $blocks
	 * @return array
	 * @throws JsonException
	 */
	public static function decode(string $blocks): array
	{
		$e = [];
		$blockFactory = BlockFactory::getInstance();
		foreach (json_decode($blocks, true, 512, JSON_THROW_ON_ERROR) as $block)
			$e[] = $blockFactory->fromFullBlock($block);
		return $e;
	}

	public static function CREATE(): self
	{
		return new self;
	}

	public function toItem(): Item
	{
		return VanillaItems::EMERALD();//TODO placeholder
	}

}