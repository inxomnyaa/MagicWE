<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\tool;

use CortexPE\Commando\BaseCommand;
use Error;
use Exception;
use InvalidArgumentException;
use pocketmine\command\CommandSender;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;

class DebugCommand extends BaseCommand
{

	/**
	 * This is where all the arguments, permissions, sub-commands, etc would be registered
	 * @throws InvalidArgumentException
	 */
	protected function prepare(): void
	{
		$this->setPermission("we.command.tool.debug");
	}

	/**
	 * @param CommandSender $sender
	 * @param string $aliasUsed
	 * @param mixed[] $args
	 */
    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $lang = Loader::getInstance()->getLanguage();
        if ($sender instanceof Player && SessionHelper::hasSession($sender)) {
            try {
                $lang = SessionHelper::getUserSession($sender)->getLanguage();
            } catch (SessionException $e) {
            }
        }
        if (!$sender instanceof Player) {
            $sender->sendMessage(TF::RED . $lang->translateString('error.runingame'));
            return;
        }
        /** @var Player $sender */
        try {
			$item = ItemFactory::getInstance()->get(ItemIds::STICK);
			$item->addEnchantment(new EnchantmentInstance(Loader::$ench));
			$item->setCustomName(Loader::PREFIX . TF::BOLD . TF::DARK_PURPLE . $lang->translateString('tool.debug'));
			$item->setLore([
				$lang->translateString('tool.debug.lore.1'),
				$lang->translateString('tool.debug.lore.2'),
				$lang->translateString('tool.debug.lore.3')
			]);
			$item->getNamedTag()->setTag(API::TAG_MAGIC_WE, CompoundTag::create());
			$sender->getInventory()->addItem($item);
		} catch (Exception $error) {
            $sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (Error $error) {
            Loader::getInstance()->getLogger()->logException($error);
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
        }
    }
}
