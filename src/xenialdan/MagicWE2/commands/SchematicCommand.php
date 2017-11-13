<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use pocketmine\command\CommandSender;
use pocketmine\event\TranslationContainer;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\WEException;

class SchematicCommand extends WECommand{
	public function __construct(Plugin $plugin){
		parent::__construct("/schematic", $plugin);
		$this->setAliases(["/schem"]);
		$this->setPermission("we.command.schematic");
		$this->setDescription("Schematic handling");
		$this->setUsage("//schem <load|reload|cache>");//TODO rethink
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		/** @var Player $sender */
		$return = $sender->hasPermission($this->getPermission());
		if (!$return){
			$sender->sendMessage(new TranslationContainer(TextFormat::RED . "%commands.generic.permission"));
			return true;
		}
		$lang = Loader::getInstance()->getLanguage();
		try{
			if (empty($args)) throw new \InvalidArgumentCountException("No arguments supplied");
			switch ($type = strtolower(array_shift($args[0]))){
				case "load": {
					$path = $this->getPlugin()->getDataFolder() . "schematics/";
					@mkdir($path);
					$file = array_shift($args);
					if (empty(trim($file))) throw new \InvalidArgumentException("No arguments supplied");
					$sender->sendMessage("Under TODO");
					break;
				}
				case "reload": {
					$sender->sendMessage(Loader::$prefix . TextFormat::GREEN . "Reloading schematics");
					$sender->sendMessage(Loader::$prefix . TextFormat::YELLOW . "TODO");
					$sender->sendMessage(Loader::$prefix . TextFormat::GREEN . "Schematics reloaded");
					break;
				}
				case "cache": {
					$sender->sendMessage(Loader::$prefix . TextFormat::YELLOW . "TODO");
					break;
				}
				default:
					throw new \InvalidArgumentException("Unknown argument");
			}
			$return = API::copy(($session = API::getSession($sender))->getLatestSelection(), $session, ...$args);
		} catch (WEException $error){
			$sender->sendMessage(Loader::$prefix . TextFormat::RED . "Looks like you are missing an argument or used the command wrong!");
			$sender->sendMessage(Loader::$prefix . TextFormat::RED . $error->getMessage());
			$return = false;
		} catch (\Error $error){
			$this->getPlugin()->getLogger()->error($error->getMessage());
			$return = false;
		} finally{
			return $return;
		}
	}
}
