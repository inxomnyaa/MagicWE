<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\selection;

use CortexPE\Commando\BaseCommand;
use Error;
use Exception;
use InvalidArgumentException;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\session\UserSession;
use function is_null;
use function var_dump;

class Pos2Command extends BaseCommand{

	/**
	 * This is where all the arguments, permissions, sub-commands, etc would be registered
	 * @throws InvalidArgumentException
	 */
	protected function prepare() : void{
		$this->setPermission("we.command.selection.pos");
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
		if (!$sender instanceof Player) {
			$sender->sendMessage(TF::RED . $lang->translateString('error.runingame'));
			return;
		}
		/** @var Player $sender */
		try{
			$session = SessionHelper::getUserSession($sender);
			if(!$session instanceof UserSession){
				throw new SessionException($lang->translateString('error.nosession', [Loader::getInstance()->getName()]));
			}
			var_dump(__CLASS__ . "::" . __FUNCTION__ . " (line " . __LINE__ . ")");
			if(($selection = $session->getLatestSelection()) === null){
				var_dump(__CLASS__ . "::" . __FUNCTION__ . " (line " . __LINE__ . ")");
				$session->addSelection(($selection = new Selection($session->getUUID(), $sender->getWorld()))); // TODO check if the selection inside of the session updates
			}
			if(is_null($selection)){
				throw new Error("No selection created - Check the console for errors");
			}
			var_dump(__CLASS__ . "::" . __FUNCTION__ . " (line " . __LINE__ . ")");
			$selection->setPos2($sender->getPosition());
			var_dump(__CLASS__ . "::" . __FUNCTION__ . " (line " . __LINE__ . ")");
		} catch (Exception $error) {
			Loader::getInstance()->getLogger()->logException($error);
			$sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
			$sender->sendMessage($this->getUsage());
		} catch (Error $error) {
			Loader::getInstance()->getLogger()->logException($error);
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
		}
	}
}
