<?php

namespace xenialdan\MagicWE2;

use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class WECommands extends PluginCommand{

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		/*$subCommand = strtolower(array_shift($args));
		if (!isset($this->subCommands[$subCommand])){
			return $this->sendHelp($sender);
		}*/
		$canUse = $this->canUse($sender);
		if ($canUse){
			if (!$this->execute($sender, $args)){
				$sender->sendMessage(TextFormat::YELLOW . "Usage: " . $this->getUsage());
			}
		} elseif (!($sender instanceof Player)){
			$sender->sendMessage(TextFormat::RED . "Please run this command in-game.");
		} else{
			$sender->sendMessage(TextFormat::RED . "You do not have permissions to run this command");
		}
		return true;
	}

	private function canUse($sender){
		return false;
	}

	private function sendHelp(CommandSender $sender){
		$sender->sendMessage($this->getUsage());
	}
}
