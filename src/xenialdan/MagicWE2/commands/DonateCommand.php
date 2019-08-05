<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\Loader;

class DonateCommand extends BaseCommand
{

    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     */
    protected function prepare(): void
    {
        $this->setPermission("we.command.donate");
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
            $name = TF::DARK_PURPLE . "[" . TF::GOLD . "XenialDan" . TF::DARK_PURPLE . "] ";
            $sender->sendMessage($name . "Greetings! Would you like to buy me an energy drink to stay awake during coding sessions?");
            $sender->sendMessage($name . "Donations are welcomed! Consider donating on " . TF::DARK_AQUA . "Pay" . TF::AQUA . "Pal:");
            $sender->sendMessage($name . TF::DARK_AQUA . "https://www.paypal.me/xenialdan");
            $sender->sendMessage($name . "Thank you! With " . TF::BOLD . TF::RED . "<3" . TF::RESET . TF::DARK_PURPLE . " - MagicWE2 by https://github.com/thebigsmileXD");
            $colorHeart = (mt_rand(0, 1) === 1 ? TF::DARK_RED : TF::DARK_PURPLE);
            $sender->sendMessage(
                TF::BOLD . $colorHeart . "   ****     ****   " . TF::EOL .
                TF::BOLD . $colorHeart . " **    ** **    ** " . TF::EOL .
                TF::BOLD . $colorHeart . "**       *       **" . TF::EOL .
                TF::BOLD . $colorHeart . " **     " . TF::GOLD . "MWE" . $colorHeart . "     ** " . TF::EOL .
                TF::BOLD . $colorHeart . "  **           **  " . TF::EOL .
                TF::BOLD . $colorHeart . "    **       **    " . TF::EOL .
                TF::BOLD . $colorHeart . "      **   **      " . TF::EOL .
                TF::BOLD . $colorHeart . "        ***        " . TF::EOL .
                TF::BOLD . $colorHeart . "         *         "
            );
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
