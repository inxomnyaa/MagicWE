<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use pocketmine\command\CommandSender;
use pocketmine\lang\TranslationContainer;
use pocketmine\form\CustomForm;
use pocketmine\form\element\CustomFormElement;
use pocketmine\form\element\Dropdown;
use pocketmine\form\element\Input;
use pocketmine\form\element\Label;
use pocketmine\form\element\Slider;
use pocketmine\form\element\Toggle;
use pocketmine\form\Form;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use xenialdan\MagicWE2\Loader;

class FloodCommand extends WECommand{
	public function __construct(Plugin $plugin){
		parent::__construct("/flood", $plugin);
		$this->setPermission("we.command.flood");
		$this->setDescription("Opens the flood tool menu");
		$this->setUsage("//flood");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		/** @var Player $sender */
		$return = $sender->hasPermission($this->getPermission());
		if (!$return){
			$sender->sendMessage(new TranslationContainer(TextFormat::RED . "%commands.generic.permission"));
			return true;
		}
		// NOTE: https://github.com/pmmp/PocketMine-MP/pull/1476 was denied, therefore Forms was not implemented in core.
		$lang = Loader::getInstance()->getLanguage();
		try{
			if ($sender instanceof Player){
				$sender->sendForm(
					new class(Loader::$prefix . TextFormat::BOLD . TextFormat::DARK_PURPLE . $lang->translateString('ui.flood.title'), [
							new Slider($lang->translateString('ui.flood.options.limit'), 100, 10000, 100.0, 1000.0),
							new Input($lang->translateString('ui.flood.options.blocks'), $lang->translateString('ui.flood.options.blocks.placeholder')),
							new Label($lang->translateString('ui.flood.options.label.infoapply'))]
					) extends CustomForm{
						public function onSubmit(Player $player): ?Form{
							$lang = Loader::getInstance()->getLanguage();
							$item = ItemFactory::get(ItemIds::BUCKET, 1);
							$item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::PROTECTION)));
							$item->setCustomName(Loader::$prefix . TextFormat::BOLD . TextFormat::DARK_PURPLE . 'Flood');
							$item->setLore(
								array_map(function (CustomFormElement $value){
									if ($value instanceof Dropdown){
										return strval($value->getText() . ": " . $value->getSelectedOption());
									}
									if ($value instanceof Toggle){
										return strval($value->getText() . ": " . ($value->getValue() ? "Yes" : "No"));
									}
									return strval($value->getText() . ": " . $value->getValue());
								}, array_filter($this->getAllElements(), function (CustomFormElement $element){ return !$element instanceof Label; }))
							);
							$item->setNamedTagEntry(new CompoundTag("MagicWE", [
								new StringTag("blocks", $this->getElement(1)->getValue()),
								new FloatTag("limit", $this->getElement(0)->getValue()),
							]));
							$player->getInventory()->addItem($item);
							return null;
						}
					}
				);
			} else{
				$sender->sendMessage(TextFormat::RED . "Console can not use this command.");
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
