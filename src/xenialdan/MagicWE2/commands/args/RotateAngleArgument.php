<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\args;

use CortexPE\Commando\args\StringEnumArgument;
use pocketmine\command\CommandSender;

class RotateAngleArgument extends StringEnumArgument
{
    /** @var array<string,int> */
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
     * @inheritDoc
     */
    public function parse(string $argument, CommandSender $sender)
    {
        return intval(self::VALUES[$argument]);//TODO maybe make better?
    }
}