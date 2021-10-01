<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use CortexPE\Commando\BaseCommand;
use Exception;
use InvalidArgumentException;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;

class DonateCommand extends BaseCommand
{

	/**
	 * This is where all the arguments, permissions, sub-commands, etc would be registered
	 * @throws InvalidArgumentException
	 */
	protected function prepare(): void
	{
		$this->setPermission("we.command.donate");
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
	{
		$lang = Loader::getInstance()->getLanguage();
		if ($sender instanceof Player && SessionHelper::hasSession($sender)) {
			try {
				$lang = SessionHelper::getUserSession($sender)->getLanguage();
			} catch (SessionException $e) {
			}
		}
		try {
			$name = TF::LIGHT_PURPLE . "[" . TF::GOLD . "XenialDan" . TF::LIGHT_PURPLE . "] ";
			$sender->sendMessage($name . "Greetings! Would you like to buy me an energy drink to stay awake during coding sessions?");
			$sender->sendMessage($name . "Donations are welcomed! Consider donating on " . TF::DARK_AQUA . "Pay" . TF::AQUA . "Pal:");
			$sender->sendMessage($name . TF::DARK_AQUA . "https://www.paypal.me/xenialdan");
			$sender->sendMessage($name . "Thank you! With " . TF::BOLD . TF::RED . "<3" . TF::RESET . TF::LIGHT_PURPLE . " - MagicWE2 by https://github.com/thebigsmileXD");
			$colorHeart = (random_int(0, 1) === 1 ? TF::DARK_RED : TF::LIGHT_PURPLE);
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
		} catch (Exception $error) {
			$sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
			$sender->sendMessage($this->getUsage());
		}
	}
}
