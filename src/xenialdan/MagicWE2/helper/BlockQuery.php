<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use InvalidArgumentException;
use pocketmine\block\utils\InvalidBlockStateException;
use pocketmine\item\LegacyStringToItemParserException;
use pocketmine\nbt\UnexpectedTagTypeException;
use xenialdan\MagicWE2\exception\BlockQueryAlreadyParsedException;
use function preg_match_all;
use const PREG_SET_ORDER;

final class BlockQuery
{
	public string $query;
	public ?string $fullBlockQuery = null;
	public ?string $blockId = null;
	public ?string $blockStatesQuery = null;
	public ?string $fullExtraQuery = null;
	public float $weight;//TODO check which are optional
	public ?int $blockFullId = null;

	/**
	 * BlockQuery constructor.
	 * @param string $query
	 * @param string|null $fullBlockQuery
	 * @param string|null $blockId
	 * @param string|null $blockStatesQuery
	 * @param string|null $fullExtraQuery
	 * @param float|null $weight
	 */
	public function __construct(string $query, ?string $fullBlockQuery, ?string $blockId, ?string $blockStatesQuery, ?string $fullExtraQuery, ?float $weight)
	{
		$this->query = $query;
		$this->fullBlockQuery = $fullBlockQuery;
		$this->blockId = $blockId;
		$this->blockStatesQuery = $blockStatesQuery;
		$this->fullExtraQuery = $fullExtraQuery;
		if ($weight === null) $this->weight = 1;
		else $this->weight = $weight / 100;
	}

	/**
	 * @param bool $update
	 * @return $this
	 * @throws BlockQueryAlreadyParsedException
	 * @throws InvalidArgumentException
	 * @throws InvalidBlockStateException
	 * @throws LegacyStringToItemParserException
	 * @throws UnexpectedTagTypeException
	 * @throws \xenialdan\MagicWE2\exception\InvalidBlockStateException
	 */
	public function parse(bool $update = true): self
	{
		//calling methods should check with hasBlock() before parse()
		if (!$update && $this->hasBlock()) throw new BlockQueryAlreadyParsedException("FullBlockID is already parsed");
		$blockstateParser = BlockStatesParser::getInstance();
		$this->blockFullId = $blockstateParser::fromString($this)->getFullId();//this should already set the blockFullId because it is a reference
		//var_dump($this->hasBlock() ? "Has block, " . $this->blockFullId : "Does not have block");
		//TODO throw BlockQueryParsingFailedException if blockFullId was not set? `if(!$this->hasBlock())`
		return $this;
	}

	public static function fromString(string $query): self
	{
		// How to code ugly 101: https://3v4l.org/2KfNW
		preg_match_all('/([\w:]+)(?:\[([\w=,]*)])?/m', $query, $matches, PREG_SET_ORDER);
		[$blockMatch, $extraMatch] = [$matches[0] ?? [], $matches[1] ?? []];
		$blockMatch += [null, null, null];
		$extraMatch += [null, null];
		[[$fullBlockQuery, $blockId, $blockStatesQuery], [$fullExtraQuery, $weight]] = [$blockMatch, $extraMatch];
		return (new self($query, $fullBlockQuery, $blockId, $blockStatesQuery, $fullExtraQuery, $weight))->parse();
	}

	public function hasBlockStates(): bool
	{
		return $this->blockStatesQuery !== null;
	}

	public function hasExtraQuery(): bool
	{
		return $this->blockStatesQuery !== null;
	}

	public function hasBlock(): bool
	{
		return $this->blockFullId !== null;
	}

}
