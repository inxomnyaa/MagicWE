<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use pocketmine\command\CommandSender;
use pocketmine\lang\TranslationContainer;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\Loader;

class CylinderCommand extends WECommand{
	public function __construct(Plugin $plugin){
		parent::__construct("/cylinder", $plugin);
        $this->setAliases(["/cyl"]);
        $this->setPermission("we.command.cyl");
		$this->setDescription("Fill an area");
		$this->setUsage("//cyl <blocks> <diameter> <height> [flags]");
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
			if (count($args) < 2) throw new \ArgumentCountError("No or too less arguments supplied");
			$messages = [];
			$error = false;
            $blocks = array_shift($args);
            $diameter = intval(array_shift($args));
            $height = intval(array_shift($args) ?? 1);
			foreach ($messages as $message){
				$sender->sendMessage($message);
			}
			$return = !$error;
			if ($return){
				$session = API::getSession($sender);
				if (is_null($session)){
					throw new \Exception("No session was created - probably no permission to use " . $this->getPlugin()->getName());
				}
				$return = API::createBrush($sender->getLevel()->getBlock($sender), new CompoundTag("MagicWE", [
                    new StringTag("type", $lang->translateString('ui.brush.select.type.cylinder')),
                    new StringTag("blocks", $blocks),
                    new FloatTag("diameter", $diameter),
                    new FloatTag("height", $height),
                    new IntTag("flags", API::flagParser($args)),
                ]), $session);
			} else{
				$return = false;
				throw new \InvalidArgumentException("Could not fill with the selected blocks");
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
