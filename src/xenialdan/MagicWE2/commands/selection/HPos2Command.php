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

class HPos2Command extends BaseCommand{

	/**
	 * This is where all the arguments, permissions, sub-commands, etc would be registered
	 * @throws InvalidArgumentException
	 */
	protected function prepare() : void{
		$this->setPermission("we.command.selection.hpos");
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
			if(($selection = $session->getLatestSelection()) === null){
				$session->addSelection(($selection = new Selection($session->getUUID(), $sender->getWorld()))); // TODO check if the selection inside of the session updates
			}
			if(is_null($selection)){
				throw new Error("No selection created - Check the console for errors");
			}
			$target = $sender->getTargetBlock(Loader::getInstance()->getToolDistance());
			if($target === null){
				$sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.notarget'));
				return;
			}
			$selection->setPos2($target->getPosition());
		} catch (Exception $error) {
			$sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
			$sender->sendMessage($this->getUsage());
		} catch (Error $error) {
			Loader::getInstance()->getLogger()->logException($error);
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
		}
	}
}
