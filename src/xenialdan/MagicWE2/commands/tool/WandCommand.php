<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\tool;

use CortexPE\Commando\BaseCommand;
use Error;
use Exception;
use InvalidArgumentException;
use pocketmine\command\CommandSender;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;

class WandCommand extends BaseCommand
{

	/**
	 * This is where all the arguments, permissions, sub-commands, etc would be registered
	 * @throws InvalidArgumentException
	 */
	protected function prepare(): void
	{
		$this->setPermission("we.command.tool.wand");
	}

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
			/** @var Durable $item */
			$item = VanillaItems::WOODEN_AXE()
				->addEnchantment(new EnchantmentInstance(Loader::$ench))
				->setUnbreakable(true)
				->setCustomName(Loader::PREFIX . TF::BOLD . TF::LIGHT_PURPLE . $lang->translateString('tool.wand'))
				->setLore([
					TF::RESET . $lang->translateString('tool.wand.lore.1'),
					TF::RESET . $lang->translateString('tool.wand.lore.2'),
					TF::RESET . $lang->translateString('tool.wand.lore.3')
				]);
			$item->getNamedTag()->setTag(API::TAG_MAGIC_WE, CompoundTag::create());
			if (!$sender->getInventory()->contains($item)) $sender->getInventory()->addItem($item);
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
