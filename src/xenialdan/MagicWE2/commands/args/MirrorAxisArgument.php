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
     * @inheritDoc
     */
    public function parse(string $argument, CommandSender $sender)
    {
        return $argument;//TODO very sloppy -.-
    }
}