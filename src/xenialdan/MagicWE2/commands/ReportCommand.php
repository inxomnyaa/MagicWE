<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use Exception;
use InvalidArgumentException;
use pocketmine\command\CommandSender;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;

class ReportCommand extends BaseCommand
{

	/**
	 * This is where all the arguments, permissions, sub-commands, etc would be registered
	 * @throws ArgumentOrderException
	 * @throws InvalidArgumentException
	 */
	protected function prepare(): void
	{
		$this->registerArgument(0, new TextArgument("title", true));
		$this->setPermission("we.command.report");
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
			$url = "Please report your bug with this link (link also in console)" . TF::EOL;
			$url .= "https://github.com/thebigsmileXD/MagicWE2/issues/new?labels=Bug&body=";
			$url .= urlencode(
				"### Description" . TF::EOL . "<!-- DESCRIPTION OF YOUR ISSUE -->" .
				TF::EOL .
				TF::EOL . "<!-- DO NOT CHANGE MANUALLY -->" .
				TF::EOL . "---" .
				TF::EOL . TF::clean(implode(TF::EOL, Loader::getInfo())));
			$url .= "&title=" . urlencode(TF::clean("[" . Loader::getInstance()->getDescription()->getVersion() . "] " . ((string)($args["title"] ?? ""))));

			if (!$sender instanceof ConsoleCommandSender) $sender->sendMessage(Loader::PREFIX . $url);
			Loader::getInstance()->getLogger()->alert($url);
		} catch (Exception $error) {
			$sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
			$sender->sendMessage($this->getUsage());
		}
	}
}
