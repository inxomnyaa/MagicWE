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

class AsyncFillCommand extends WECommand{
	public function __construct(Plugin $plugin){
		parent::__construct("/aset", $plugin);
		$this->setAliases(["/afill"]);
		$this->setPermission("we.command.aset");
		$this->setDescription("Fill an area asynchronously");
		$this->setUsage("//afill <blocks> [flags...]");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		$sender->sendMessage("The command is currently under TODO and being worked on.");
		return true;
		/** @var Player $sender */
		$return = $sender->hasPermission($this->getPermission());
		if (!$return){
			$sender->sendMessage(new TranslationContainer(TextFormat::RED . "%commands.generic.permission"));
			return true;
		}
		$lang = Loader::getInstance()->getLanguage();
		try{
			$messages = [];
			$error = false;
			$newblocks = API::blockParser(array_shift($args), $messages, $error);
			foreach ($messages as $message){
				$sender->sendMessage($message);
			}
			$return = !$error;
			if ($return){
				API::fillAsync(($session = API::getSession($sender))->getLatestSelection(), $session, $newblocks, ...$args);
			} else{
				throw new \TypeError("Could not fill with the selected blocks");
			}
		} catch (\Exception $error){
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
