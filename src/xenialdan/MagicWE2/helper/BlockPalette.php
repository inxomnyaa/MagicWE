<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use Generator;
use InvalidArgumentException;
use JsonException;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use xenialdan\MagicWE2\exception\BlockQueryAlreadyParsedException;

class BlockPalette
{
	/**
	 * Contains BlockQuery[]
	 * @var WeightedRandom
	 */
	public WeightedRandom $randomBlockQueries;
	public string $name;

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
	 * @return static
	 * @throws InvalidArgumentException
	 * @throws BlockQueryAlreadyParsedException
	 */
	public static function fromString(string $blocksQuery): self
	{
		$palette = self::EMPTY();

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

	public function addBlockQuery(BlockQuery $query)
	{
		$this->randomBlockQueries->add($query, $query->weight);
	}

	//TODO addBlock

	/**
	 * @return Generator|Block
	 * @throws InvalidArgumentException
	 */
	public function blocks(int $amount = 1): Generator
	{
		if ($amount < 1) throw new InvalidArgumentException('$amount must be positive');
		$this->randomBlockQueries->setup();//TODO check if performance impact is too big (i.e when calling this method multiple times)
		/** @var BlockFactory $blockFactory */
		$blockFactory = BlockFactory::getInstance();
		/** @var BlockQuery $blockQuery */
		foreach ($this->randomBlockQueries->generate($amount) as $blockQuery) {
			yield $blockFactory->fromFullBlock($blockQuery->blockFullId);//TODO yield blockFullId and do not yield Block?
		}
	}

	/**
	 * @param Block[] $blocks
	 * @return string
	 * @throws JsonException
	 */
	public static function encode(array $blocks): string
	{
		$e = [];
		foreach ($blocks as $block)
			/** @noinspection PhpInternalEntityUsedInspection */ $e[] = $block->getFullId();
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
		/** @var BlockFactory $blockFactory */
		$blockFactory = BlockFactory::getInstance();
		foreach (json_decode($blocks, true, 512, JSON_THROW_ON_ERROR) as $block)
			$e[] = $blockFactory->fromFullBlock($block);
		return $e;
	}

	public static function EMPTY(): self
	{
		return new self;
	}

}