<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\Selection;

class Pos1Command extends PluginCommand{
	public function __construct(Plugin $plugin){
		parent::__construct("/pos1", $plugin);
		$this->setAliases(["/1"]);
		$this->setPermission("we.command.pos");
		$this->setDescription("Select first position");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		/** @var Player $sender */
		if (!isset(Loader::$selections[$sender->getLowerCaseName()])) Loader::$selections[$sender->getLowerCaseName()] = new Selection($sender->getLevel());
		$sender->sendMessage(Loader::$selections[$sender->getLowerCaseName()]->setPos1($sender->getPosition()));
		return true;
	}
}
