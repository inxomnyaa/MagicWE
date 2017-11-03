<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\WECommands;

class TogglewandCommand extends WECommands{
	public function __construct(Plugin $plugin){
		parent::__construct("/togglewand", $plugin);
		$this->setPermission("we.command.togglewand");
		$this->setDescription("Toggle the wand tool on/off");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		/** @var Player $sender */
		$sender->sendMessage(($session = API::getSession($sender))->setWandEnabled(!$session->isWandEnabled()));
		return true;
	}
}
