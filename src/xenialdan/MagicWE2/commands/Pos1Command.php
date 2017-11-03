<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\Selection;
use xenialdan\MagicWE2\Session;
use xenialdan\MagicWE2\WECommands;

class Pos1Command extends WECommands{
	public function __construct(Plugin $plugin){
		parent::__construct("/pos1", $plugin);
		$this->setAliases(["/1"]);
		$this->setPermission("we.command.pos");
		$this->setDescription("Select first position");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		/** @var Player $sender */
		/** @var Session $session */
		if (!($session = API::getSession($sender))->isWandEnabled()){
			$sender->sendMessage(Loader::$prefix . TextFormat::RED . "The wand tool is disabled. Use //togglewand to re-enable it");//TODO #translation
			return true; //TODO false?
		}
		$selection = $session->getLatestSelection() ?? $session->addSelection(new Selection($sender->getLevel())); // TODO check if the selection inside of the session updates
		$sender->sendMessage($selection->setPos1($sender->getPosition()));
		return true;
	}
}
