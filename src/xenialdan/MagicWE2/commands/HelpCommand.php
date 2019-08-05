<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\Loader;

class HelpCommand extends BaseCommand
{
    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     * @throws \CortexPE\Commando\exception\ArgumentOrderException
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new RawStringArgument("command", true));
        $this->setPermission("we.command.help");
    }

    /**
     * @param CommandSender $sender
     * @param string $aliasUsed
     * @param BaseArgument[] $args
     */
    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $lang = Loader::getInstance()->getLanguage();
        try {
            $cmds = [];
            /** @var Command $cmd */
            if (empty($args["command"])) {
                foreach (array_filter(Loader::getInstance()->getServer()->getCommandMap()->getCommands(), function (Command $command) use ($sender) {
                    return strpos($command->getName(), "/") !== false && $command->testPermissionSilent($sender);
                }) as $cmd) {
                    $cmds[$cmd->getName()] = $cmd;
                }
            } else {
                if (($cmd = Loader::getInstance()->getServer()->getCommandMap()->getCommand("/" . str_replace("/", "", TF::clean(strval($args["command"]))))) instanceof Command) {
                    $cmds[$cmd->getName()] = $cmd;
                } else {
                    $sender->sendMessage(TF::RED . str_replace("/", "//", Loader::getInstance()->getServer()->getLanguage()->translateString("%commands.generic.notFound")));
                    return;
                }
            }
            foreach ($cmds as $command) {
                $message = TF::LIGHT_PURPLE . "/" . $command->getName();
                if (!empty(($aliases = $command->getAliases()))) {
                    foreach ($aliases as $i => $alias) {
                        $aliases[$i] = "/" . $alias;
                    }
                    $message .= TF::DARK_PURPLE . " [" . implode(",", $aliases) . "]";
                }
                $message .= TF::AQUA . " " . $command->getDescription() . PHP_EOL . " - " . $command->getUsage();
                $sender->sendMessage($message);
            }
        } catch (\Exception $error) {
            $sender->sendMessage(Loader::PREFIX . TF::RED . "Looks like you are missing an argument or used the command wrong!");
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (\ArgumentCountError $error) {
            $sender->sendMessage(Loader::PREFIX . TF::RED . "Looks like you are missing an argument or used the command wrong!");
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (\Error $error) {
            Loader::getInstance()->getLogger()->logException($error);
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
        }
    }
}
