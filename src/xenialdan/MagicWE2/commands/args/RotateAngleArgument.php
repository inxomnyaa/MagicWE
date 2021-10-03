<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\args;

use CortexPE\Commando\args\StringEnumArgument;
use pocketmine\command\CommandSender;

class RotateAngleArgument extends StringEnumArgument
{
	/** @var array */
	protected const VALUES = ["90" => 90, "180" => 180, "270" => 270];

	public function getTypeName(): string
	{
		return "int";
	}

	public function getEnumName(): string
	{
		return "angle";
	}

	/**
	 * @param string $argument
	 * @param CommandSender $sender
	 * @return int
	 */
	public function parse(string $argument, CommandSender $sender): int
	{
		return (int)$this->getValue($argument);
	}
}