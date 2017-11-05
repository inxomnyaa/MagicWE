<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\Selection;
use xenialdan\MagicWE2\Session;
use xenialdan\MagicWE2\WEException;

class Pos2Command extends PluginCommand{
	public function __construct(Plugin $plugin){
		parent::__construct("/pos2", $plugin);
		$this->setAliases(["/2"]);
		$this->setPermission("we.command.pos");
		$this->setDescription("Select second position");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		/** @var Player $sender */
		$return = true;
		try{
			/** @var Session $session */
			$session = API::getSession($sender);
			$selection = $session->getLatestSelection() ?? $session->addSelection(new Selection($sender->getLevel())); // TODO check if the selection inside of the session updates
			$sender->sendMessage($selection->setPos2($sender->getPosition()));
		} catch (WEException $error){
			$sender->sendMessage(Loader::$prefix . TextFormat::RED . "Looks like you are missing an argument or used the command wrong!");
			$sender->sendMessage(Loader::$prefix . TextFormat::RED . $error->getMessage());
			$return = false;
		} catch (\Error $error){
			$this->getPlugin()->getLogger()->error($error->getMessage());
			$return = false;
		} finally{
			return parent::execute($sender, $commandLabel, $args) && $return;
		}
	}
}
