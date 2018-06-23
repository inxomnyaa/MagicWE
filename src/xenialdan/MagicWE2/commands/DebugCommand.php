<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use pocketmine\command\CommandSender;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\lang\TranslationContainer;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use xenialdan\MagicWE2\Loader;

class DebugCommand extends WECommand{
	public function __construct(Plugin $plugin){
		parent::__construct("/debug", $plugin);
		$this->setPermission("we.command.debug");
		$this->setDescription("Gives you the debug stick");
		$this->setUsage("//debug");
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
			$item = ItemFactory::get(ItemIds::STICK);
			$item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::PROTECTION)));
			$item->setCustomName(Loader::$prefix . TextFormat::BOLD . TextFormat::DARK_PURPLE . 'Debug Stick');
			$item->setLore([//TODO translation
				'Left click a block to get information',
				'like the name and damage values of a block',
				'Use //toggledebug to toggle it\'s functionality'
			]);
			$item->setNamedTagEntry(new CompoundTag("MagicWE", []));
			$sender->getInventory()->addItem($item);
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
