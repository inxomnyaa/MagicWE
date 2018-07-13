<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use pocketmine\command\CommandSender;
use pocketmine\lang\TranslationContainer;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\Clipboard;
use xenialdan\MagicWE2\Loader;

class FlipCommand extends WECommand{
	public function __construct(Plugin $plugin){
		parent::__construct("/flip", $plugin);
		$this->setPermission("we.command.flip");
		$this->setDescription("Flip a clipboard");
		$this->setUsage("//flip <X|Y|Z|UP|DOWN|WEST|EAST|NORTH|SOUTH...>");
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

			$reflectionClass = new \ReflectionClass(Clipboard::class);
			$constants = $reflectionClass->getConstants();
			$args = array_flip(array_change_key_case(array_flip($args), CASE_UPPER));
			$flags = Clipboard::DIRECTION_DEFAULT;
			foreach ($args as $arg){
				if (array_key_exists("FLIP_" . $arg, $constants)){
					$flags ^= 1 << $constants["FLIP_" . $arg];
				} else{
					$return = false;
					throw new \InvalidArgumentException('"' . $arg . '" is not a valid input');
				}
			}
			$sender->sendMessage(Loader::$prefix . "Trying to flip clipboard by " . implode("|", $args));
			$session = API::getSession($sender);
			if (is_null($session)){
				throw new \Exception("No session was created - probably no permission to use " . $this->getPlugin()->getName());
			}
			$clipboard = $session->getClipboards()[0];//TODO multi-clipboard support
			if (is_null($clipboard)){
				throw new \Exception("No clipboard found - create a clipboard first");
			}
			$clipboard->flip($flags);
			$sender->sendMessage(Loader::$prefix . "Successfully flipped clipboard");
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
