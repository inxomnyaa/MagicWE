<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use pocketmine\command\CommandSender;
use pocketmine\lang\TranslationContainer;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\Loader;

class PasteCommand extends WECommand {
	public function __construct(Plugin $plugin) {
		parent::__construct("/paste", $plugin);
		$this->setPermission("we.command.paste");
		$this->setDescription("Paste blocks from your clipboard");
		$this->setUsage("//paste [flags...]");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) {
		/** @var Player $sender */
		$return = $sender->hasPermission($this->getPermission());
		if (!$return) {
			$sender->sendMessage(new TranslationContainer(TextFormat::RED . "%commands.generic.permission"));
			return true;
		}
		$lang = Loader::getInstance()->getLanguage();
		try {
			$session = API::getSession($sender);
			if (is_null($session)) {
				throw new \Exception("No session was created - probably no permission to use " . $this->getPlugin()->getName());
			}
			$clipboard = $session->getClipboards()[0];//TODO multi-clipboard support
			if (is_null($clipboard)) {
				throw new \Exception("No clipboard found - create a clipboard first");
			}
            if (!API::hasFlag(API::flagParser($args), API::FLAG_POSITION_RELATIVE)) {
                $clipboard->setOffset(new Vector3());
            }
            $return = API::pasteAsync($clipboard, $session, $sender->asPosition(), API::flagParser($args));
		} catch (\Exception $error) {
			$sender->sendMessage(Loader::$prefix . TextFormat::RED . "Looks like you are missing an argument or used the command wrong!");
			$sender->sendMessage(Loader::$prefix . TextFormat::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
			$return = false;
		} catch (\ArgumentCountError $error) {
			$sender->sendMessage(Loader::$prefix . TextFormat::RED . "Looks like you are missing an argument or used the command wrong!");
			$sender->sendMessage(Loader::$prefix . TextFormat::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
			$return = false;
		} catch (\Error $error) {
			$this->getPlugin()->getLogger()->error($error->getMessage());
			$sender->sendMessage(Loader::$prefix . TextFormat::RED . $error->getMessage());
			$return = false;
		} finally {
			return $return;
		}
	}
}
