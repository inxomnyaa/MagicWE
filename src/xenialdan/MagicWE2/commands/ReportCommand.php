<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\Loader;

class ReportCommand extends BaseCommand
{

    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     * @throws \CortexPE\Commando\exception\ArgumentOrderException
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new TextArgument("title", true));
        $this->setPermission("we.command.report");
    }

    /**
     * @param CommandSender $sender
     * @param string $aliasUsed
     * @param BaseArgument[] $args
     */
    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        /** @var Player $sender */
        try {
            $url = "Please report your bug with this link (link also in console)" . PHP_EOL;
            $url .= "https://github.com/thebigsmileXD/MagicWE2/issues/new?labels=Bug&body=";
            $url .= urlencode(
                "### Description" . PHP_EOL . "<!-- DESCRIPTION OF YOUR ISSUE -->" .
                PHP_EOL .
                PHP_EOL . "<!-- DO NOT CHANGE MANUALLY -->" .
                PHP_EOL . "---" .
                PHP_EOL . TF::clean(implode(PHP_EOL, Loader::getInfo())));
            $url .= "&title=" . urlencode(TF::clean("[" . Loader::getInstance()->getDescription()->getVersion() . "] " . strval($args["title"] ?? "")));

            if (!$sender instanceof ConsoleCommandSender) $sender->sendMessage(Loader::PREFIX . $url);
            Loader::getInstance()->getLogger()->alert($url);
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
