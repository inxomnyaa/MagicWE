<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\args;

use CortexPE\Commando\args\StringEnumArgument;
use pocketmine\command\CommandSender;

class MirrorAxisArgument extends StringEnumArgument
{
	protected const VALUES = ["x" => "x", "z" => "z", "y" => "y", "xz" => "xz"];

	public function getTypeName(): string
	{
		return "string";
	}

	public function getEnumName(): string
	{
		return "axis";
	}

	/**
	 * @param string $argument
	 * @param CommandSender $sender
	 * @return string //TODO consider changing to Axis
	 */
	public function parse(string $argument, CommandSender $sender): string
	{
		return (string)$this->getValue($argument);
	}
}