<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use xenialdan\MagicWE2\Loader;

class WandCommand extends BaseCommand
{

    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     */
    protected function prepare(): void
    {
        $this->setPermission("we.command.wand");
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
            /** @var Durable $item */
            $item = ItemFactory::get(ItemIds::WOODEN_AXE);
            $item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Loader::FAKE_ENCH_ID)));
            $item->setUnbreakable(true);
            $item->setCustomName(Loader::$prefix . TextFormat::BOLD . TextFormat::DARK_PURPLE . 'Wand');
            $item->setLore([//TODO translation
                'Left click air or a block to set the position 1 of a selection',
                'Right click air or a block to set the position 2 of a selection',
                'If you click in air its set to the block you are looking at',
                'Use //togglewand to toggle it\'s functionality'
            ]);
            $item->setNamedTagEntry(new CompoundTag("MagicWE", []));
            if (!$sender->getInventory()->contains($item)) $sender->getInventory()->addItem($item);
        } catch (\Exception $error) {
            $sender->sendMessage(Loader::$prefix . TextFormat::RED . "Looks like you are missing an argument or used the command wrong!");
            $sender->sendMessage(Loader::$prefix . TextFormat::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (\ArgumentCountError $error) {
            $sender->sendMessage(Loader::$prefix . TextFormat::RED . "Looks like you are missing an argument or used the command wrong!");
            $sender->sendMessage(Loader::$prefix . TextFormat::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (\Error $error) {
            Loader::getInstance()->getLogger()->logException($error);
            $sender->sendMessage(Loader::$prefix . TextFormat::RED . $error->getMessage());
        }
    }
}
