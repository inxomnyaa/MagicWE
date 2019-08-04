<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use xenialdan\MagicWE2\Loader;

class LimitCommand extends BaseCommand
{

    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     * @throws \CortexPE\Commando\exception\ArgumentOrderException
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new IntegerArgument("limit", true));
        $this->setPermission("we.command.limit");
        $this->setUsage("//limit [limit: int|-1]");
    }

    /**
     * @param CommandSender $sender
     * @param string $aliasUsed
     * @param BaseArgument[] $args
     */
    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        try {
            if (empty($args["limit"])) {
                $limit = Loader::getInstance()->getConfig()->get("limit", -1);
                $sender->sendMessage(Loader::PREFIX . TextFormat::GREEN . "Current limit: " . ($limit < 0 ? "Disabled" : $limit));
            } else {
                Loader::getInstance()->getConfig()->set("limit", intval($args["limit"]));
                $sender->sendMessage(Loader::PREFIX . TextFormat::GREEN . "Block change limit was set to " . intval($args["limit"]));
            }
        } catch (\Exception $error) {
            $sender->sendMessage(Loader::PREFIX . TextFormat::RED . "Looks like you are missing an argument or used the command wrong!");
            $sender->sendMessage(Loader::PREFIX . TextFormat::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (\ArgumentCountError $error) {
            $sender->sendMessage(Loader::PREFIX . TextFormat::RED . "Looks like you are missing an argument or used the command wrong!");
            $sender->sendMessage(Loader::PREFIX . TextFormat::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (\Error $error) {
            Loader::getInstance()->getLogger()->logException($error);
            $sender->sendMessage(Loader::PREFIX . TextFormat::RED . $error->getMessage());
        }
    }
}
