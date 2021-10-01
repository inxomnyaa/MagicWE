<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\tool;

use CortexPE\Commando\BaseCommand;
use InvalidArgumentException;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as TF;

class FloodCommand extends BaseCommand
{

	/**
	 * This is where all the arguments, permissions, sub-commands, etc would be registered
	 * @throws InvalidArgumentException
	 */
	protected function prepare(): void
	{
		$this->setPermission("we.command.tool.floodfill");
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
	{
		$sender->sendMessage(TF::RED . "TEMPORARILY DISABLED!");
		/*
		if (!$sender instanceof Player) return;
		/** @var Player $sender * /
		$lang = Loader::getInstance()->getLanguage();
		try {
			if ($sender instanceof Player) {
				$form = new CustomForm(Loader::PREFIX . TF::BOLD . TF::LIGHT_PURPLE . $lang->translateString('ui.flood.title'));
				$form->addElement(new Slider($lang->translateString('ui.flood.options.limit'), 0, 5000, 500.0));
				$form->addElement(new Input($lang->translateString('ui.flood.options.blocks'), $lang->translateString('ui.flood.options.blocks.placeholder')));
				$form->addElement(new Label($lang->translateString('ui.flood.options.label.infoapply')));
				$form->setCallable(function (Player $player, $data) use ($form) {
					$item = ItemFactory::get(ItemIds::BUCKET, 1);
					$item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Loader::FAKE_ENCH_ID)));
					$item->setCustomName(Loader::PREFIX . TF::BOLD . TF::LIGHT_PURPLE . 'Flood');
					$item->setLore(BrushCommand::generateLore($form->getContent(), $data));
					$item->setNamedTagEntry(new CompoundTag(API::TAG_MAGIC_WE, [
						new StringTag("blocks", $data[1]),
						new FloatTag("limit", $data[0]),
					]));
					$player->getInventory()->addItem($item);
				});
				$sender->sendForm($form);
			} else {
				$sender->sendMessage(TF::RED . "Console can not use this command.");
			}
		} catch (\Exception $error) {
			$sender->sendMessage(Loader::PREFIX . TF::RED . Loader::getInstance()->getLanguage()->translateString('error.command-error'));
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
			$sender->sendMessage($this->getUsage());
		} catch (\ArgumentCountError $error) {
			$sender->sendMessage(Loader::PREFIX . TF::RED . Loader::getInstance()->getLanguage()->translateString('error.command-error'));
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
			$sender->sendMessage($this->getUsage());
		} catch (\Error $error) {
			Loader::getInstance()->getLogger()->logException($error);
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
		}*/
	}
}
