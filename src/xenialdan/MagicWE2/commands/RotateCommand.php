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

class RotateCommand extends WECommand{
	public function __construct(Plugin $plugin){
		parent::__construct("/rotate", $plugin);
		$this->setPermission("we.command.rotate");
		$this->setDescription("rotate a clipboard");
		$this->setUsage("//rotate <1|2|3|-1|-2|-3>");
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
			if (intval($args[0]) != $args[0]) throw new \InvalidArgumentException("You must use a number as argument");
			$args[0] = intval($args[0]);

			$sender->sendMessage(Loader::$prefix . "Trying to rotate clipboard by " . 90 * $args[0] . " degrees");
			$session = API::getSession($sender);
			if (is_null($session)){
				throw new \Exception("No session was created - probably no permission to use " . $this->getPlugin()->getName());
			}
			$clipboard = $session->getClipboards()[0];
			if (is_null($clipboard)){
				throw new \Exception("No clipboard found - create a clipboard first");
			}
			$clipboard->rotate($args[0]);//TODO multi-clipboard support
			$sender->sendMessage(Loader::$prefix . "Successfully rotated clipboard");
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
