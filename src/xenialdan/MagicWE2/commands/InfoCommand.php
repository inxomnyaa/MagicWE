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

class InfoCommand extends BaseCommand
{

	/**
	 * This is where all the arguments, permissions, sub-commands, etc would be registered
	 * @throws InvalidArgumentException
	 */
	protected function prepare(): void
	{
		$this->setPermission("we.command.info");
	}

	/**
	 * @inheritDoc
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
	{
		$lang = Loader::getInstance()->getLanguage();
		if ($sender instanceof Player && SessionHelper::hasSession($sender)) {
			try {
				$lang = SessionHelper::getUserSession($sender)->getLanguage();
			} catch (SessionException) {
			}
		}
		try {
			$sender->sendMessage(rtrim(Loader::PREFIX, " ") . " " . $lang->translateString('command.info.title'));
			foreach (Loader::getInfo() as $i => $line) {
				if ($i <= 1) continue;
				$sender->sendMessage($line);
			}
		} catch (Exception $error) {
			$sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
			$sender->sendMessage($this->getUsage());
		}
	}
}
