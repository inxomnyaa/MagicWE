<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\args;

use CortexPE\Commando\args\RawStringArgument;
use InvalidArgumentException;
use pocketmine\command\CommandSender;
use xenialdan\MagicWE2\exception\BlockQueryAlreadyParsedException;
use xenialdan\MagicWE2\helper\BlockPalette;

class BlocksArgument extends RawStringArgument
{

	public function canParse(string $testString, CommandSender $sender): bool
	{
		//TODO optimize; TODO check if returning false here triggers argument being passed to another $arg
		try {
			//TODO if regex match '/,(?![^\[]*])/' return true
			BlockPalette::fromString($testString);
		} catch (BlockQueryAlreadyParsedException | InvalidArgumentException $e) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $argument
	 * @param CommandSender $sender
	 *
	 * @return BlockPalette
	 */
	public function parse(string $argument, CommandSender $sender)
	{
		return BlockPalette::fromString($argument);
	}
}