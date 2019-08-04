<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use xenialdan\MagicWE2\Loader;

class DebugCommand extends BaseCommand
{

    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     */
    protected function prepare(): void
    {
        $this->setPermission("we.command.debug");
    }

    /**
     * @param CommandSender $sender
     * @param string $aliasUsed
     * @param BaseArgument[] $args
     */
    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $lang = Loader::getInstance()->getLanguage();
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . $lang->translateString('runingame'));
            return;
        }
        /** @var Player $sender */
        try {
            $item = ItemFactory::get(ItemIds::STICK);
            $item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Loader::FAKE_ENCH_ID)));
            $item->setCustomName(Loader::PREFIX . TextFormat::BOLD . TextFormat::DARK_PURPLE . 'Debug Stick');
            $item->setLore([//TODO translation
                'Left click a block to get information',
                'like the name and damage values of a block',
                'Use //toggledebug to toggle it\'s functionality'
            ]);
            $item->setNamedTagEntry(new CompoundTag("MagicWE", []));
            $sender->getInventory()->addItem($item);
        } catch (\Exception $error) {
            $sender->sendMessage(Loader::PREFIX . TextFormat::RED . "Looks like you are missing an argument or used the command wrong!");
            $sender->sendMessage(Loader::PREFIX . TextFormat::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (\ArgumentCountError $error) {
            $sender->sendMessage(Loader::PREFIX . TextFormat::RED . "Looks like you are missing an argument or used the command wrong!");
            $sender->sendMessage(Loader::PREFIX . TextFormat::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (\Error $error) {
            Loader::getInstance()->getLogger()->logException($error);
            $sender->sendMessage(Loader::PREFIX . TextFormat::RED . $error->getMessage());
        }
    }
}
