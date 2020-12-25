<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\args;

use CortexPE\Commando\args\RawStringArgument;
use pocketmine\command\CommandSender;
use xenialdan\MagicWE2\helper\BlockPalette;

class BlocksArgument extends RawStringArgument
{

	public function canParse(string $testString, CommandSender $sender): bool
	{
		//TODO optimize; TODO check if returning false here triggers argument being passed to another $arg
		//TODO if regex match '/,(?![^\[]*])/' return true
		$pattern = '/[\w:]+(?:\[[\w=,]*])?%?\d*/';
		$r = preg_match_all($pattern, $testString);
		return $r !== false && $r >= 1;
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