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

class ReplaceCommand extends WECommand{
	public function __construct(Plugin $plugin){
		parent::__construct("/replace", $plugin);
		$this->setPermission("we.command.replace");
		$this->setDescription("Replace blocks in an area");
		$this->setUsage("//replace [flags...]");
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
			if (empty($args) && count($args) < 2) throw new \InvalidArgumentCountException("No arguments supplied");
			$messages = [];
			$error = false;
			$blocks1 = API::blockParser(array_shift($args), $messages, $error);
			$blocks2 = API::blockParser(array_shift($args), $messages, $error);
			foreach ($messages as $message){
				$sender->sendMessage($message);
			}
			$return = !$error;
			if ($return){
				$sender->sendMessage(API::replace(($session = API::getSession($sender))->getLatestSelection(), $sender->getLevel(), $blocks1, $blocks2, ...$args));
			} else{
				throw new \InvalidArgumentException("Could not replace with the selected blocks");
			}
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
