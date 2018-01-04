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

class SetCommand extends WECommand{
	public function __construct(Plugin $plugin){
		parent::__construct("/set", $plugin);
		$this->setAliases(["/fill"]);
		$this->setPermission("we.command.set");
		$this->setDescription("Fill an area");
		$this->setUsage("//set <blocks> [flags]");
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
			$messages = [];
			$error = false;
			$newblocks = API::blockParser(array_shift($args), $messages, $error);
			foreach ($messages as $message){
				$sender->sendMessage($message);
			}
			$return = !$error;
			if ($return){
				$session = API::getSession($sender);
				if (is_null($session)){
					throw new WEException("No session was created - probably no permission to use " . $this->getPlugin()->getName());
				}
				$selection = $session->getLatestSelection();
				if (is_null($selection)){
					throw new WEException("No selection found - select an area first");
				}
				$return = API::fill($selection, $session, $newblocks, ...$args);
			} else{
				throw new \InvalidArgumentException("Could not fill with the selected blocks");
			}
		} catch (WEException $error){
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
