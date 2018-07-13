<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use pocketmine\command\CommandSender;
use pocketmine\lang\TranslationContainer;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\Loader;

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
			if (empty($args)) throw new \ArgumentCountError("No arguments supplied");
			$session = API::getSession($sender);
			if (is_null($session)){
				throw new \Exception("No session was created - probably no permission to use " . $this->getPlugin()->getName());
			}
			switch ($type = strtolower(array_shift($args))){
				case "load": {
					if (count($args) < 1) throw new \ArgumentCountError("Too less arguments supplied");
					$path = $this->getPlugin()->getDataFolder() . "schematics/";
					@mkdir($path);
					$file = array_shift($args);
					if (empty(trim($file))) throw new \InvalidArgumentException("No file name supplied");
					$sender->sendMessage(Loader::$prefix . TextFormat::YELLOW . "Beta command - not yet properly implemented");
					break;
				}
				case "reload": {
					$sender->sendMessage(Loader::$prefix . TextFormat::GREEN . "Reloading schematics");
					$sender->sendMessage(Loader::$prefix . TextFormat::YELLOW . "Beta command - not yet properly implemented");
					$sender->sendMessage(Loader::$prefix . TextFormat::GREEN . "Schematics reloaded");
					break;
				}
				case "cache": {
					$sender->sendMessage(Loader::$prefix . TextFormat::YELLOW . "Beta command - not yet properly implemented");
					break;
				}
				case "save": {
					$sender->sendMessage(Loader::$prefix . TextFormat::YELLOW . "Beta command - not yet properly implemented");
					if (count($args) < 1) throw new \ArgumentCountError("Too less arguments supplied");
					$path = $this->getPlugin()->getDataFolder() . "schematics/";
					@mkdir($path);
					$file = array_shift($args);
					if (empty(trim($file))) throw new \InvalidArgumentException("No file name supplied");
					$selection = $session->getLatestSelection();
					if (is_null($selection)){
						throw new \Exception("No selection found - select an area first");
					}
					#$schematic = new Schematic();
					#$schematic->setBlocks($selection->getBlocks(...));
					#$schematic->save($file);
					break;
				}
				default: {
					$return = false;
					throw new \InvalidArgumentException("Unknown argument");
				}
			}
		} catch (\Exception $error){
			$sender->sendMessage(Loader::$prefix . TextFormat::RED . "Looks like you are missing an argument or used the command wrong!");
			$sender->sendMessage(Loader::$prefix . TextFormat::RED . $error->getMessage());
			$return = false;
		} catch (\ArgumentCountError $error){
			$sender->sendMessage(Loader::$prefix . TextFormat::RED . "Looks like you are missing an argument or used the command wrong!");
			$sender->sendMessage(Loader::$prefix . TextFormat::RED . $error->getMessage());
			$return = false;
		} catch (\Error $error){
			$this->getPlugin()->getLogger()->error($error->getMessage());
			$sender->sendMessage(Loader::$prefix . TextFormat::RED . $error->getMessage());
			$return = false;
		} finally{
			return $return;
		}
	}
}
