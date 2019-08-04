<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
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
            $name = TextFormat::DARK_PURPLE . "[" . TextFormat::GOLD . "XenialDan" . TextFormat::DARK_PURPLE . "] ";
            $sender->sendMessage($name . "Greetings! Would you like to buy me an energy drink to stay awake during coding sessions?");
            $sender->sendMessage($name . "Donations are welcomed! Consider donating on " . TextFormat::DARK_AQUA . "Pay" . TextFormat::AQUA . "Pal:");
            $sender->sendMessage($name . TextFormat::DARK_AQUA . "https://www.paypal.me/xenialdan");
            $sender->sendMessage($name . "Thank you! With " . TextFormat::BOLD . TextFormat::RED . "<3" . TextFormat::RESET . TextFormat::DARK_PURPLE . " - MagicWE2 by https://github.com/thebigsmileXD");
            $colorHeart = (mt_rand(0, 1) === 1 ? TextFormat::DARK_RED : TextFormat::DARK_PURPLE);
            $sender->sendMessage(
                TextFormat::BOLD . $colorHeart . "   ****     ****   " . PHP_EOL .
                TextFormat::BOLD . $colorHeart . " **    ** **    ** " . PHP_EOL .
                TextFormat::BOLD . $colorHeart . "**       *       **" . PHP_EOL .
                TextFormat::BOLD . $colorHeart . " **     " . TextFormat::GOLD . "MWE" . $colorHeart . "     ** " . PHP_EOL .
                TextFormat::BOLD . $colorHeart . "  **           **  " . PHP_EOL .
                TextFormat::BOLD . $colorHeart . "    **       **    " . PHP_EOL .
                TextFormat::BOLD . $colorHeart . "      **   **      " . PHP_EOL .
                TextFormat::BOLD . $colorHeart . "        ***        " . PHP_EOL .
                TextFormat::BOLD . $colorHeart . "         *         "
            );
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
