<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use JsonException;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;

abstract class BlockPalette
{
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
		foreach (json_decode($blocks, true, 512, JSON_THROW_ON_ERROR) as $block)
			$e[] = BlockFactory::getInstance()->fromFullBlock($block);
		return $e;
	}

}