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
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use xenialdan\customui\elements\Dropdown;
use xenialdan\customui\elements\Input;
use xenialdan\customui\elements\Label;
use xenialdan\customui\elements\Slider;
use xenialdan\customui\elements\Toggle;
use xenialdan\customui\elements\UIElement;
use xenialdan\customui\windows\CustomForm;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\Loader;

class BrushCommand extends WECommand
{
    public function __construct(Plugin $plugin)
    {
        parent::__construct("/brush", $plugin);
        $this->setPermission("we.command.brush");
        $this->setDescription("Opens the brush tool menu");
        $this->setUsage("//brush");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        /** @var Player $sender */
        $return = $sender->hasPermission($this->getPermission());
        if (!$return) {
            $sender->sendMessage(new TranslationContainer(TextFormat::RED . "%commands.generic.permission"));
            return true;
        }
        $lang = Loader::getInstance()->getLanguage();
        try {
            if ($sender instanceof Player) {
                $form =
                    new CustomForm(Loader::$prefix . TextFormat::BOLD . TextFormat::DARK_PURPLE . $lang->translateString('ui.brush.title'));
                $form->addElement(new Dropdown($lang->translateString('ui.brush.select.title'), [
                    $lang->translateString('ui.brush.select.type.sphere'),
                    $lang->translateString('ui.brush.select.type.cylinder'),
                    $lang->translateString('ui.brush.select.type.cuboid'),
                    $lang->translateString('ui.brush.select.type.clipboard')]));
                $form->setCallable(function (Player $player, $data) use ($lang, $form) {
                    /** @var Dropdown $dropdown */
                    $selectedOption = $data[0];
                    switch ($selectedOption) {
                        case $lang->translateString('ui.brush.select.type.sphere'):
                            {
                                ///
                                $form = new CustomForm(Loader::$prefix . TextFormat::BOLD . TextFormat::DARK_PURPLE . $lang->translateString('ui.brush.settings.title', [ucfirst($selectedOption)]));
                                $form->addElement(new Input($lang->translateString('ui.brush.options.blocks'), $lang->translateString('ui.brush.options.blocks.placeholder')));
                                $form->addElement(new Slider($lang->translateString('ui.brush.options.diameter'), 1, 50, 1.0));
                                $form->addElement(new Toggle($lang->translateString('ui.brush.options.flags'), false));
                                $form->setCallable(function (Player $player, $data) use ($selectedOption, $form) {
                                    $item = ItemFactory::get(ItemIds::WOODEN_SHOVEL);
                                    $item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::PROTECTION)));
                                    $item->setCustomName(Loader::$prefix . TextFormat::BOLD . TextFormat::DARK_PURPLE . ucfirst($selectedOption) . ' brush');
                                    $item->setLore(BrushCommand::generateLore($form->getContent(), $data));
                                    //TODO if($data(2)->getValue() === true) -> show flag window
                                    $flags = BrushCommand::translateElementsToFlags([], []);
                                    $item->setNamedTagEntry(new CompoundTag("MagicWE", [
                                        new StringTag("type", $selectedOption),
                                        new StringTag("blocks", $data[0]),
                                        new FloatTag("diameter", $data[1]),
                                        new IntTag("flags", $flags),
                                    ]));
                                    $player->getInventory()->addItem($item);
                                });
                                $player->sendForm($form);
                                ///
                                break;
                            }
                        case $lang->translateString('ui.brush.select.type.cylinder'):
                            {
                                ///
                                $form = new CustomForm(Loader::$prefix . TextFormat::BOLD . TextFormat::DARK_PURPLE . $lang->translateString('ui.brush.settings.title', [ucfirst($selectedOption)]));
                                $form->addElement(new Input($lang->translateString('ui.brush.options.blocks'), $lang->translateString('ui.brush.options.blocks.placeholder')));
                                $form->addElement(new Slider($lang->translateString('ui.brush.options.diameter'), 1, 50, 1.0));
                                $form->addElement(new Slider($lang->translateString('ui.brush.options.height'), 1, 50, 1.0));
                                $form->addElement(new Toggle($lang->translateString('ui.brush.options.flags'), false));
                                $form->setCallable(function (Player $player, $data) use ($selectedOption, $form) {
                                    $item = ItemFactory::get(ItemIds::WOODEN_SHOVEL);
                                    $item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::PROTECTION)));
                                    $item->setCustomName(Loader::$prefix . TextFormat::BOLD . TextFormat::DARK_PURPLE . ucfirst($selectedOption) . ' brush');
                                    $item->setLore(BrushCommand::generateLore($form->getContent(), $data));
                                    //TODO if($data(3)->getValue() === true) -> show flag window
                                    $flags = BrushCommand::translateElementsToFlags([], []);
                                    $item->setNamedTagEntry(new CompoundTag("MagicWE", [
                                        new StringTag("type", $selectedOption),
                                        new StringTag("blocks", $data[0]),
                                        new FloatTag("diameter", $data[1]),
                                        new FloatTag("height", $data[2]),
                                        new IntTag("flags", $flags),
                                    ]));
                                    $player->getInventory()->addItem($item);
                                    return null;
                                });
                                $player->sendForm($form);
                                ///
                                break;
                            }
                        case $lang->translateString('ui.brush.select.type.cuboid'):
                            {
                                ///
                                $form = new CustomForm(Loader::$prefix . TextFormat::BOLD . TextFormat::DARK_PURPLE . $lang->translateString('ui.brush.settings.title', [ucfirst($selectedOption)]));
                                $form->addElement(new Input($lang->translateString('ui.brush.options.blocks'), $lang->translateString('ui.brush.options.blocks.placeholder')));
                                $form->addElement(new Slider($lang->translateString('ui.brush.options.width'), 1, 100, 1.0));
                                $form->addElement(new Slider($lang->translateString('ui.brush.options.height'), 1, 100, 1.0));
                                $form->addElement(new Slider($lang->translateString('ui.brush.options.depth'), 1, 100, 1.0));
                                $form->addElement(new Toggle($lang->translateString('ui.brush.options.flags'), false));
                                $form->setCallable(function (Player $player, $data) use ($selectedOption, $form) {
                                    $item = ItemFactory::get(ItemIds::WOODEN_SHOVEL);
                                    $item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::PROTECTION)));
                                    $item->setCustomName(Loader::$prefix . TextFormat::BOLD . TextFormat::DARK_PURPLE . ucfirst($selectedOption) . ' brush');
                                    $item->setLore(BrushCommand::generateLore($form->getContent(), $data));
                                    //TODO if($data(4)->getValue() === true) -> show flag window
                                    $flags = BrushCommand::translateElementsToFlags([], []);
                                    $item->setNamedTagEntry(new CompoundTag("MagicWE", [
                                        new StringTag("type", $selectedOption),
                                        new StringTag("blocks", $data[0]),
                                        new FloatTag("width", $data[1]),
                                        new FloatTag("height", $data[2]),
                                        new FloatTag("depth", $data[3]),
                                        new IntTag("flags", $flags),
                                    ]));
                                    $player->getInventory()->addItem($item);
                                    return null;
                                });
                                $player->sendForm($form);
                                ///
                                break;
                            }
                        case $lang->translateString('ui.brush.select.type.clipboard'):
                            {
                                ///
                                $form = new CustomForm(Loader::$prefix . TextFormat::BOLD . TextFormat::DARK_PURPLE . $lang->translateString('ui.brush.settings.title', [ucfirst($selectedOption)]));
                                $form->addElement(new Toggle($lang->translateString('ui.brush.options.flags'), false));
                                $form->setCallable(function (Player $player, $data) use ($selectedOption, $form) {
                                    $item = ItemFactory::get(ItemIds::WOODEN_SHOVEL);
                                    $item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::PROTECTION)));
                                    $item->setCustomName(Loader::$prefix . TextFormat::BOLD . TextFormat::DARK_PURPLE . ucfirst($selectedOption) . ' brush');
                                    $item->setLore(BrushCommand::generateLore($form->getContent(), $data));
                                    //TODO if($data(0)->getValue() === true) -> show flag window
                                    $flags = BrushCommand::translateElementsToFlags([], []);
                                    $item->setNamedTagEntry(new CompoundTag("MagicWE", [
                                        new StringTag("type", $selectedOption),
                                        new IntTag("flags", $flags),
                                    ]));
                                    $player->getInventory()->addItem($item);
                                    return null;
                                });
                                $player->sendForm($form);
                                ///
                                break;
                            }
                        default:
                            {
                                //unimplemented type
                            }

                    }
                    return null;
                });
                $sender->sendForm($form);
            } else {
                $sender->sendMessage(TextFormat::RED . "Console can not use this command.");
            }
        } catch (\Exception $error) {
            $sender->sendMessage(Loader::$prefix . TextFormat::RED . "Looks like you are missing an argument or used the command wrong!");
            $sender->sendMessage(Loader::$prefix . TextFormat::RED . $error->getMessage());
            $return = false;
        } catch (\ArgumentCountError $error) {
            $sender->sendMessage(Loader::$prefix . TextFormat::RED . "Looks like you are missing an argument or used the command wrong!");
            $sender->sendMessage(Loader::$prefix . TextFormat::RED . $error->getMessage());
            $return = false;
        } catch (\Error $error) {
            $this->getPlugin()->getLogger()->error($error->getMessage());
            $sender->sendMessage(Loader::$prefix . TextFormat::RED . $error->getMessage());
            $return = false;
        } finally {
            return $return;
        }
    }

    /**
     * @param UIElement[] $elements
     * @param $data
     * @return int
     */
    public static function translateElementsToFlags(array $elements, array $data)
    {
        $lang = Loader::getInstance()->getLanguage();
        $flags = [];
        foreach ($elements as $i => $value) {
            if (!$value instanceof Toggle) continue;
            switch ($value->getText()) {
                case $lang->translateString('ui.brush.options.flags.keepexistingblocks'):
                    {
                        if ($data[$i]) $flags[] = "-keepblocks";
                        break;
                    }
                case $lang->translateString('ui.brush.options.flags.keepair'):
                    {
                        if ($data[$i]) $flags[] = "-keepair";
                        break;
                    }
                case $lang->translateString('ui.brush.options.flags.hollow'):
                    {
                        if ($data[$i]) $flags[] = "-h";
                        break;
                    }
                case $lang->translateString('ui.brush.options.flags.natural'):
                    {
                        if ($data[$i]) $flags[] = "-n";
                        break;
                    }
            }
        }
        return API::flagParser($flags);
    }

    public static function showFlagUI()
    {
        /*
          new Label($lang->translateString('ui.brush.options.label.flags')),
          new Toggle($lang->translateString('ui.brush.options.flags.keepexistingblocks'), false),
          new Toggle($lang->translateString('ui.brush.options.flags.keepair'), false),
          new Toggle($lang->translateString('ui.brush.options.flags.hollow'), false),
          new Toggle($lang->translateString('ui.brush.options.flags.natural'), false),
          new Label($lang->translateString('ui.brush.options.label.infoapply'))
        */
    }

    /**
     * @param UIElement[] $elements
     * @param array $data
     * @return array
     */
    public static function generateLore(array $elements, array $data)
    {
        $return = [];
        foreach ($elements as $i => $element) {
            if ($element instanceof Label) continue;
            if ($element instanceof Toggle) {
                $return[] = strval($element->getText() . ": " . ($data[$i] ? "Yes" : "No"));
                continue;
            }
            $return[] = strval($element->getText() . ": " . $data[$i]);
        }
        return $return;
    }
}
