<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\utility;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\exception\CalculationException;
use xenialdan\MagicWE2\Loader;

class CalculateCommand extends BaseCommand
{

    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     * @throws \CortexPE\Commando\exception\ArgumentOrderException
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new TextArgument("expression", false));
        $this->setPermission("we.command.utility.calculate");
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
            $sender->sendMessage(strval($args["expression"]) . " = " . API::evalAsMath(strval($args["expression"])));
        } catch (CalculationException $error) {
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
            $sender->sendMessage(Loader::PREFIX . TF::RED . strval($args["expression"]));
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
