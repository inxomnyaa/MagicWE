<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\Clipboard;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\WEException;

class FlipCommand extends PluginCommand{
	public function __construct(Plugin $plugin){
		parent::__construct("/flip", $plugin);
		$this->setPermission("we.command.flip");
		$this->setDescription("Flip a clipboard");
	}

	public function getUsage(): string{
		return "//flip <X|Y|Z|UP|DOWN|WEST|EAST|NORTH|SOUTH> [...]";
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		/** @var Player $sender */
		$return = parent::execute($sender, $commandLabel, $args);
		if(!$return) return $return;
		try{
			if (empty($args)) throw new \InvalidArgumentCountException("No arguments supplied");

			$reflectionClass = new \ReflectionClass(Clipboard::class);
			$constants = $reflectionClass->getConstants();
			$args = array_flip(array_change_key_case(array_flip($args), CASE_UPPER));
			$flags = Clipboard::DIRECTION_DEFAULT;
			foreach ($args as $arg){
				if (array_key_exists("FLIP_" . $arg, $constants)){
					$flags ^= 1 << $constants["FLIP_" . $arg];
				} else throw new \InvalidArgumentException('"' . $arg . '" is not a valid input');
			}
			$sender->sendMessage(Loader::$prefix . "Trying to flip clipboard by " . implode("|", $args));
			($session = API::getSession($sender))->getClipboards()[0]->flip($flags);//TODO multi-clipboard support
			$sender->sendMessage(Loader::$prefix . "Successfully flipped clipboard");
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
