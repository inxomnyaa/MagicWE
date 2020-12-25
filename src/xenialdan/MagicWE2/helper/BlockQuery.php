<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use xenialdan\MagicWE2\exception\BlockQueryAlreadyParsedException;

final class BlockQuery
{
	public string $query;
	public ?string $fullBlockQuery;
	public ?string $blockId;
	public ?string $blockStatesQuery;
	public ?string $fullExtraQuery;
	public float $weight;//TODO check which are optional
	public ?int $blockFullId;

	/**
	 * BlockQuery constructor.
	 * @param string $query
	 * @param string|null $fullBlockQuery
	 * @param string|null $blockId
	 * @param string|null $blockStatesQuery
	 * @param string|null $fullExtraQuery
	 * @param string|null $weight
	 */
	public function __construct(string $query, ?string $fullBlockQuery, ?string $blockId, ?string $blockStatesQuery, ?string $fullExtraQuery, ?string $weight)
	{
		$this->query = $query;
		$this->fullBlockQuery = $fullBlockQuery;
		$this->blockId = $blockId;
		$this->blockStatesQuery = $blockStatesQuery;
		$this->fullExtraQuery = $fullExtraQuery;
		$this->weight = (float)($weight ?? "100") / 100;
	}

	public function parse(bool $update = true): self
	{
		//calling methods should check with hasBlock() before parse()
		if (!$update && $this->hasBlock()) throw new BlockQueryAlreadyParsedException("FullBlockID is already parsed");
		/** @var BlockStatesParser $blockStatesParser */
		$blockStatesParser = BlockStatesParser::getInstance();
		$blockStatesParser::fromString($this);//this should already set the blockFullId because it is a reference
		var_dump($this->hasBlock() ? "Has block, " . $this->blockFullId : "Does not have block");
		//TODO throw BlockQueryParsingFailedException if blockFullId was not set? `if(!$this->hasBlock())`
		return $this;
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