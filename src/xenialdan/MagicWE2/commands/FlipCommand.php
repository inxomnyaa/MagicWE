<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\Clipboard;
use xenialdan\MagicWE2\Loader;

class FlipCommand extends WECommands{
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
		$return = true;
		try{
			if (empty($args)) throw new \TypeError("No arguments supplied");

			$reflectionClass = new \ReflectionClass(Clipboard::class);
			$constants = $reflectionClass->getConstants();
			$args = array_flip(array_change_key_case(array_flip($args), CASE_UPPER));
			$flags = Clipboard::DIRECTION_DEFAULT;
			var_dump(__LINE__ . PHP_EOL);
			foreach ($args as $arg){
				var_dump($flags);
				if (array_key_exists("FLIP_" . $arg, $constants)){
					var_dump(__LINE__ . PHP_EOL);
					$flags ^= 1 << $constants("FLIP_" . $arg);
				} else throw new \TypeError('"' . $arg . '" is not a valid input');
				var_dump(__LINE__ . PHP_EOL);
				var_dump($flags);
			}
			var_dump(__LINE__ . PHP_EOL);
			($session = API::getSession($sender))->getClipboards()[0]->flip($flags);//TODO multi-clipboard support
			var_dump(__LINE__ . PHP_EOL);
			$sender->sendMessage(Loader::$prefix . "Successfully tried to flip clipboard");
			var_dump(__LINE__ . PHP_EOL);
		} catch (\Error $error){
			$sender->sendMessage(Loader::$prefix . TextFormat::RED . "Looks like you are missing an argument or used the command wrong!");
			$sender->sendMessage(Loader::$prefix . TextFormat::RED . $error->getMessage());
			$return = false;
		} finally{
			return $return;
		}
	}
}
