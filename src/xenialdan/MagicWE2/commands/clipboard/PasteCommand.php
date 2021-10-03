<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\clipboard;

use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use Exception;
use InvalidArgumentException;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\exception\SelectionException;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;

class PasteCommand extends BaseCommand
{

	/**
	 * This is where all the arguments, permissions, sub-commands, etc would be registered
	 * @throws ArgumentOrderException
	 * @throws InvalidArgumentException
	 */
	protected function prepare(): void
	{
		$this->registerArgument(0, new TextArgument("flags", true));
		$this->setPermission("we.command.clipboard.paste");
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
		if (!$sender instanceof Player) {
			$sender->sendMessage(TF::RED . $lang->translateString('error.runingame'));
			return;
		}
		/** @var Player $sender */
		try {
			$session = SessionHelper::getUserSession($sender);
			if (is_null($session)) {
				throw new SessionException($lang->translateString('error.nosession', [Loader::getInstance()->getName()]));
			}
			$clipboard = $session->getCurrentClipboard();
			if (is_null($clipboard)) {
				throw new SelectionException($lang->translateString('error.noclipboard'));
			}
			if (!$clipboard instanceof SingleClipboard) {//TODO check if i want to pass ANY clipboard instead
				throw new SelectionException($lang->translateString('error.noclipboard'));
			}
			/*if (!API::hasFlag(API::flagParser(explode(" ", strval($args["flags"]))), API::FLAG_POSITION_RELATIVE)) {
				$clipboard->setOffset(new Vector3(0,0,0));//TODO fix? Move to API
			}*/
			$hasFlags = isset($args["flags"]);
			API::pasteAsync($clipboard, $session, $sender->getPosition(), $hasFlags ? API::flagParser(explode(" ", (string)$args["flags"])) : API::FLAG_BASE);
		} catch (Exception $error) {
			$sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
			$sender->sendMessage($this->getUsage());
		}
	}
}
