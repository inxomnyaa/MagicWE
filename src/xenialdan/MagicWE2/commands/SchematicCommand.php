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
use xenialdan\MagicWE2\WEException;

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
			if (empty($args)) throw new \InvalidArgumentCountException("No arguments supplied");
			switch ($type = strtolower(array_shift($args))){
				case "load": {
					$path = $this->getPlugin()->getDataFolder() . "schematics/";
					@mkdir($path);
					$file = array_shift($args);
					if (empty($file) || trim($file) === "") throw new \InvalidArgumentException("No filename supplied");
					$file = str_replace(".schematic", "", $file);
					try{
						$schematic = new Schematic(Loader::$path["schematics"] . "/" . $file . ".schematic");
						$schematic = $schematic->decode();
						$schematic = $schematic->fixBlockIds();
					} catch (\InvalidArgumentException $exception){
						$sender->sendMessage(Loader::$prefix . TextFormat::RED . "The schematic " . $file . " contains no data");
						$sender->sendMessage(Loader::$prefix . TextFormat::RED . $exception->getMessage());
						throw $exception;//TODO check if the InvalidArgumentException comes out and aborts here
					}
					$clipboard = API::addSchematic($schematic, $file);
					if (is_null($clipboard)) throw new WEException("The schematic file you tried to load could not be turned into a clipboard");
					$sender->sendMessage((($session = API::getSession($sender))->setClipboards([$clipboard]) ? TextFormat::GREEN . "Successfully loaded schematic into clipboard" : TextFormat::RED . "Could not load schematic into clipboard"));
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
				case "save": {
					$sender->sendMessage(Loader::$prefix . TextFormat::GREEN . "Saving clipboard as schematic");
					$sender->sendMessage(Loader::$prefix . TextFormat::YELLOW . "TODO");
					$file = array_shift($args);
					if (empty($file) || trim($file) === "") throw new \InvalidArgumentException("No filename supplied");
					$file = str_replace(".schematic", "", $file);
					$session = API::getSession($sender);
					$clipboard = $session->getClipboards()[0];//TODO latest clipboard
					$success = true;
					try{
						$schematic = new Schematic();
						$schematic->setWidth($clipboard->getWidth());
						$schematic->setHeight($clipboard->getHeight());
						$schematic->setLength($clipboard->getLength());
						$schematic->setBlocks($clipboard->threeDeeArray());
						$schematic->encode();
						$schematic->save(Loader::$path["schematics"] . "/" . $file . ".schematic");
					} catch (\Throwable $error){
						$this->getPlugin()->getLogger()->error(TextFormat::RED . $error->getMessage() . ", " . $error->getFile() . ":" . $error->getLine());
						$this->getPlugin()->getLogger()->error(implode(PHP_EOL . TextFormat::RED , $error->getTrace()));
						$success = false;
					}
					$sender->sendMessage(($success ? TextFormat::GREEN . "Successfully saved schematic from clipboard" : TextFormat::RED . "Could not save schematic from clipboard"));
					break;
				}
				default:
					throw new \InvalidArgumentException("Unknown argument");
			}
		} catch (\InvalidArgumentException $error){
			$sender->sendMessage(Loader::$prefix . TextFormat::RED . "Looks like you are missing an argument or used the command wrong!");
			$sender->sendMessage(Loader::$prefix . TextFormat::RED . $error->getMessage());
			$return = false;
		} catch (WEException $error){
			$sender->sendMessage(Loader::$prefix . TextFormat::RED . "A weak exception occurred!");
			$sender->sendMessage(Loader::$prefix . TextFormat::RED . $error->getMessage());
			$return = true;
		} catch (\Exception $error){
			$sender->sendMessage(Loader::$prefix . TextFormat::RED . "An exception occurred");
			$sender->sendMessage(Loader::$prefix . TextFormat::RED . $error->getMessage());
			$this->getPlugin()->getLogger()->error(TextFormat::RED . "An exception occurred");
			$this->getPlugin()->getLogger()->error(TextFormat::RED . $error->getMessage() . ", " . $error->getFile() . ":" . $error->getLine());
			$this->getPlugin()->getLogger()->error(implode(PHP_EOL . TextFormat::RED , $error->getTrace()));
			$return = true;
		} catch (\Error $error){
			$this->getPlugin()->getLogger()->error($error->getMessage());
			$return = true;
		} finally{
			return $return;
		}
	}
}
