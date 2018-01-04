<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use pocketmine\command\CommandSender;
use pocketmine\event\TranslationContainer;
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
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\WEException;

class BrushCommand extends WECommand{
	public function __construct(Plugin $plugin){
		parent::__construct("/brush", $plugin);
		$this->setPermission("we.command.brush");
		$this->setDescription("Opens the brush tool menu");
		$this->setUsage("//brush");
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
			if ($sender instanceof Player){
				$sender->sendForm(
					new class(Loader::$prefix . TextFormat::BOLD . TextFormat::DARK_PURPLE . $lang->translateString('ui.brush.title'), [
							new Dropdown($lang->translateString('ui.brush.options.type.title'), [
								$lang->translateString('ui.brush.options.type.sphere'),
								$lang->translateString('ui.brush.options.type.cylinder'),
								$lang->translateString('ui.brush.options.type.square')]),//TODO rectangle, custom shapes etc
							//TODO BIG TODO: Move all this into a new UI based on what was selected
							//TODO BIG TODO: Move all this into a new UI based on what was selected
							//TODO BIG TODO: Move all this into a new UI based on what was selected
							new Slider($lang->translateString('ui.brush.options.diameter'), 1, 100, 1.0),
							new Slider($lang->translateString('ui.brush.options.height'), 1, 100, 1.0),
							new Input($lang->translateString('ui.brush.options.blocks'), $lang->translateString('ui.brush.options.blocks.placeholder')),
							new Label($lang->translateString('ui.brush.options.label.flags')),
							new Toggle($lang->translateString('ui.brush.options.flags.keepexistingblocks'), false),
							new Toggle($lang->translateString('ui.brush.options.flags.keepair'), false),
							new Toggle($lang->translateString('ui.brush.options.flags.hollow'), false),
							new Toggle($lang->translateString('ui.brush.options.flags.natural'), false),
							new Label($lang->translateString('ui.brush.options.label.infoapply'))]
					) extends CustomForm{
						public function onSubmit(Player $player): ?Form{
							$lang = Loader::getInstance()->getLanguage();
							$item = ItemFactory::get(ItemIds::WOODEN_SHOVEL);
							$item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::PROTECTION)));
							$item->setCustomName(Loader::$prefix . TextFormat::BOLD . TextFormat::DARK_PURPLE . 'Brush');
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
							/** @var Dropdown $dropdown */
							$dropdown = $this->getElement(0);
							$flags = [];
							/** @var Toggle $value */
							foreach ([$this->getElement(5), $this->getElement(6), $this->getElement(7), $this->getElement(8), $this->getElement(9)] as $value){
								switch ($value->getText()){
									case $lang->translateString('ui.brush.options.flags.keepexistingblocks'): {
										if ($value->getValue()) $flags[] = "-keepblocks";
										break;
									}
									case $lang->translateString('ui.brush.options.flags.keepair'): {
										if ($value->getValue()) $flags[] = "-keepair";
										break;
									}
									case $lang->translateString('ui.brush.options.flags.hollow'): {
										if ($value->getValue()) $flags[] = "-h";
										break;
									}
									case $lang->translateString('ui.brush.options.flags.natural'): {
										if ($value->getValue()) $flags[] = "-n";
										break;
									}
								}
							}
							$item->setNamedTagEntry(new CompoundTag("MagicWE", [
								new StringTag("type", $dropdown->getSelectedOption()),
								new StringTag("blocks", $this->getElement(3)->getValue()),
								new FloatTag("diameter", $this->getElement(1)->getValue()),
								new FloatTag("height", $this->getElement(2)->getValue()),
								new IntTag("flags", API::flagParser($flags)),
							]));
							$player->getInventory()->addItem($item);
							return null;
						}
					}
				);
			} else{
				$sender->sendMessage(TextFormat::RED . "Console can not use this command.");
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
