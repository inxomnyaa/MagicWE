<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use BlockHorizons\libschematic\Schematic;
use pocketmine\command\CommandSender;
use pocketmine\event\TranslationContainer;
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
		$this->setUsage("//schem <load|reload|save>");
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
					$file = str_replace(".schematic", "", $file);
					try{
						if(!file_exists(Loader::$path["schematics"] . "/" . $file . ".schematic")){
							throw new \InvalidArgumentException(Loader::$path["schematics"] . "/" . $file . ".schematic not found!");
						}
						$schematic = new Schematic(Loader::$path["schematics"] . "/" . $file . ".schematic");
						$schematic = $schematic->decode();
						$schematic = $schematic->fixBlockIds();
					} catch (\InvalidArgumentException $exception){
						$sender->sendMessage(Loader::$prefix . TextFormat::RED . "The schematic " . $file . " contains no or corrupted data");
						$sender->sendMessage(Loader::$prefix . TextFormat::RED . $exception->getMessage());
						break;
					}
					$clipboard = API::addSchematic($schematic, $file);
					if (is_null($clipboard)) throw new \Exception("The schematic file you tried to load could not be turned into a clipboard");
					$sender->sendMessage((($session = API::getSession($sender))->setClipboards([0 => $clipboard]) ? TextFormat::GREEN . "Successfully loaded schematic into clipboard" : TextFormat::RED . "Could not load schematic into clipboard"));
					break;
				}
				case "reload": {
					$sender->sendMessage(Loader::$prefix . TextFormat::GREEN . "Reloading schematics");
					API::setSchematics([]);
					foreach (API::getSchematics() as $filename => $schematic){
						try{
							$schematic = new Schematic(Loader::$path["schematics"] . "/" . $filename . ".schematic");
							$schematic = $schematic->decode();
							$schematic = $schematic->fixBlockIds();
							API::addSchematic($schematic, $filename);
						} catch (\InvalidArgumentException $exception){
							$sender->sendMessage(Loader::$prefix . TextFormat::RED . "The schematic " . $filename . " contains no data");
							$sender->sendMessage(Loader::$prefix . TextFormat::RED . $exception->getMessage());
						}
					}
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
					$clipboard = $session->getClipboards()[0];//TODO multi-clipboard support
					if (is_null($clipboard)){
						throw new \Exception("No clipboard found - create a clipboard first");
					}
					$sender->sendMessage(Loader::$prefix . TextFormat::GREEN . "Saving clipboard as schematic");
					$file = str_replace(".schematic", "", $file);
					$success = true;
					try{
						$schematic = new Schematic();
						$schematic->setWidth($clipboard->getWidth());
						$schematic->setHeight($clipboard->getHeight());
						$schematic->setLength($clipboard->getLength());
						$schematic->setBlocks($clipboard->threeDeeArray());
						$schematic->encode();
						$schematic->save(Loader::$path["schematics"] . "/" . $file . ".schematic");
					} catch (\Throwable $error){ //TODO optimize
						$this->getPlugin()->getLogger()->error(TextFormat::RED . $error->getMessage() . ", " . $error->getFile() . ":" . $error->getLine());
						$this->getPlugin()->getLogger()->error(implode(PHP_EOL . TextFormat::RED, $error->getTrace()));
						$success = false;
					}
					$sender->sendMessage(($success ? TextFormat::GREEN . "Successfully saved schematic from clipboard" : TextFormat::RED . "Could not save schematic from clipboard"));
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
