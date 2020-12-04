<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\args;

use CortexPE\Commando\args\StringEnumArgument;
use pocketmine\command\CommandSender;
use xenialdan\MagicWE2\Loader;

class LanguageArgument extends StringEnumArgument
{
    public function getTypeName(): string
    {
        return "string";
    }

    public function parse(string $argument, CommandSender $sender)
    {
        return array_search($argument, Loader::getInstance()->getLanguageList(), true);
    }

    public function getEnumValues(): array
    {
        return array_values(Loader::getInstance()->getLanguageList());
    }

    public function getEnumName(): string
    {
        return "language";
    }
}
