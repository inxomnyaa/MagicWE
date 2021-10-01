<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use Exception;
use InvalidArgumentException;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;

class LimitCommand extends BaseCommand
{

	/**
	 * This is where all the arguments, permissions, sub-commands, etc would be registered
	 * @throws ArgumentOrderException
	 * @throws InvalidArgumentException
	 */
	protected function prepare(): void
	{
		$this->registerArgument(0, new IntegerArgument("limit", true));
		$this->setPermission("we.command.limit");
		$this->setUsage("//limit [limit: int|-1]");
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
			if (empty($args["limit"])) {
				$limit = Loader::getInstance()->getConfig()->get("limit", -1);
				$sender->sendMessage(Loader::PREFIX . TF::GREEN . $lang->translateString('command.limit.current', [($limit < 0 ? ucfirst(Loader::getInstance()->getLanguage()->translateString('disabled')) : $limit)]));
			} else {
				Loader::getInstance()->getConfig()->set("limit", (int)$args["limit"]);
				$sender->sendMessage(Loader::PREFIX . TF::GREEN . $lang->translateString('command.limit.set', [(int)$args["limit"]]));
			}
		} catch (Exception $error) {
			$sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
			$sender->sendMessage($this->getUsage());
		}
	}
}
