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
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use xenialdan\customui\elements\Button;
use xenialdan\customui\elements\Input;
use xenialdan\customui\elements\Label;
use xenialdan\customui\elements\Slider;
use xenialdan\customui\elements\Toggle;
use xenialdan\customui\elements\UIElement;
use xenialdan\customui\windows\CustomForm;
use xenialdan\customui\windows\SimpleForm;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\shape\ShapeGenerator;

class BrushCommand extends BaseCommand
{
    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     */
    protected function prepare(): void
    {
        $this->setPermission("we.command.brush");
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
            $form = new SimpleForm(Loader::PREFIX . TextFormat::BOLD . TextFormat::DARK_PURPLE . $lang->translateString('ui.brush.title'), $lang->translateString('ui.brush.select.title'));
            $form->addButton(new Button($lang->translateString('ui.brush.select.type.sphere')));
            $form->addButton(new Button($lang->translateString('ui.brush.select.type.cylinder')));
            $form->addButton(new Button($lang->translateString('ui.brush.select.type.cuboid')));
            $form->addButton(new Button($lang->translateString('ui.brush.select.type.cube')));
            $form->addButton(new Button($lang->translateString('ui.brush.select.type.clipboard')));
            $form->setCallable(function (Player $player, $data) use ($lang, $form) {
                $selectedOption = $data;
                switch ($data) {
                    case $lang->translateString('ui.brush.select.type.sphere'):
                        {
                            ///
                            $form = new CustomForm(Loader::PREFIX . TextFormat::BOLD . TextFormat::DARK_PURPLE . $lang->translateString('ui.brush.settings.title', [ucfirst($selectedOption)]));
                            $form->addElement(new Input($lang->translateString('ui.brush.options.blocks'), $lang->translateString('ui.brush.options.blocks.placeholder')));
                            $form->addElement(new Slider($lang->translateString('ui.brush.options.diameter'), 1, 50, 1.0));
                            $form->addElement(new Label($lang->translateString('ui.brush.options.flags')));
                            $form->addElement(new Toggle($lang->translateString('ui.flags.keepexistingblocks'), false));
                            $form->addElement(new Toggle($lang->translateString('ui.flags.keepair'), false));
                            $form->addElement(new Toggle($lang->translateString('ui.flags.hollow'), false));
                            $form->addElement(new Toggle($lang->translateString('ui.flags.natural'), false));
                            $form->setCallable(function (Player $player, $data) use ($selectedOption, $form) {
                                $item = ItemFactory::get(ItemIds::WOODEN_SHOVEL);
                                $item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Loader::FAKE_ENCH_ID)));
                                $item->setCustomName(Loader::PREFIX . TextFormat::BOLD . TextFormat::DARK_PURPLE . ucfirst($selectedOption) . ' brush');
                                $item->setLore(BrushCommand::generateLore($form->getContent(), $data));
                                $flags = BrushCommand::translateElementsToFlags($form->getContent(), $data);
                                $item->setNamedTagEntry(new CompoundTag("MagicWE", [
                                    new IntTag("type", ShapeGenerator::TYPE_SPHERE),
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
                            $form = new CustomForm(Loader::PREFIX . TextFormat::BOLD . TextFormat::DARK_PURPLE . $lang->translateString('ui.brush.settings.title', [ucfirst($selectedOption)]));
                            $form->addElement(new Input($lang->translateString('ui.brush.options.blocks'), $lang->translateString('ui.brush.options.blocks.placeholder')));
                            $form->addElement(new Slider($lang->translateString('ui.brush.options.diameter'), 1, 50, 1.0));
                            $form->addElement(new Slider($lang->translateString('ui.brush.options.height'), 1, 50, 1.0));
                            $form->addElement(new Label($lang->translateString('ui.brush.options.flags')));
                            $form->addElement(new Toggle($lang->translateString('ui.flags.keepexistingblocks'), false));
                            $form->addElement(new Toggle($lang->translateString('ui.flags.keepair'), false));
                            $form->addElement(new Toggle($lang->translateString('ui.flags.hollow'), false));
                            $form->addElement(new Toggle($lang->translateString('ui.flags.hollowclosed'), false));
                            $form->addElement(new Toggle($lang->translateString('ui.flags.natural'), false));
                            $form->setCallable(function (Player $player, $data) use ($selectedOption, $form) {
                                $item = ItemFactory::get(ItemIds::WOODEN_SHOVEL);
                                $item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Loader::FAKE_ENCH_ID)));
                                $item->setCustomName(Loader::PREFIX . TextFormat::BOLD . TextFormat::DARK_PURPLE . ucfirst($selectedOption) . ' brush');
                                $item->setLore(BrushCommand::generateLore($form->getContent(), $data));
                                $flags = BrushCommand::translateElementsToFlags($form->getContent(), $data);
                                $item->setNamedTagEntry(new CompoundTag("MagicWE", [
                                    new IntTag("type", ShapeGenerator::TYPE_CYLINDER),
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
                            $form = new CustomForm(Loader::PREFIX . TextFormat::BOLD . TextFormat::DARK_PURPLE . $lang->translateString('ui.brush.settings.title', [ucfirst($selectedOption)]));
                            $form->addElement(new Input($lang->translateString('ui.brush.options.blocks'), $lang->translateString('ui.brush.options.blocks.placeholder')));
                            $form->addElement(new Slider($lang->translateString('ui.brush.options.width'), 1, 100, 1.0));
                            $form->addElement(new Slider($lang->translateString('ui.brush.options.height'), 1, 100, 1.0));
                            $form->addElement(new Slider($lang->translateString('ui.brush.options.depth'), 1, 100, 1.0));
                            $form->addElement(new Label($lang->translateString('ui.brush.options.flags')));
                            $form->addElement(new Toggle($lang->translateString('ui.flags.keepexistingblocks'), false));
                            $form->addElement(new Toggle($lang->translateString('ui.flags.keepair'), false));
                            $form->addElement(new Toggle($lang->translateString('ui.flags.hollow'), false));
                            $form->addElement(new Toggle($lang->translateString('ui.flags.natural'), false));
                            $form->setCallable(function (Player $player, $data) use ($selectedOption, $form) {
                                $item = ItemFactory::get(ItemIds::WOODEN_SHOVEL);
                                $item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Loader::FAKE_ENCH_ID)));
                                $item->setCustomName(Loader::PREFIX . TextFormat::BOLD . TextFormat::DARK_PURPLE . ucfirst($selectedOption) . ' brush');
                                $item->setLore(BrushCommand::generateLore($form->getContent(), $data));
                                $flags = BrushCommand::translateElementsToFlags($form->getContent(), $data);
                                $item->setNamedTagEntry(new CompoundTag("MagicWE", [
                                    new IntTag("type", ShapeGenerator::TYPE_CUBOID),
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
                    case $lang->translateString('ui.brush.select.type.cube'):
                        {
                            ///
                            $form = new CustomForm(Loader::PREFIX . TextFormat::BOLD . TextFormat::DARK_PURPLE . $lang->translateString('ui.brush.settings.title', [ucfirst($selectedOption)]));
                            $form->addElement(new Input($lang->translateString('ui.brush.options.blocks'), $lang->translateString('ui.brush.options.blocks.placeholder')));
                            $form->addElement(new Slider($lang->translateString('ui.brush.options.width'), 1, 100, 1.0));
                            $form->addElement(new Label($lang->translateString('ui.brush.options.flags')));
                            $form->addElement(new Toggle($lang->translateString('ui.flags.keepexistingblocks'), false));
                            $form->addElement(new Toggle($lang->translateString('ui.flags.keepair'), false));
                            $form->addElement(new Toggle($lang->translateString('ui.flags.hollow'), false));
                            $form->addElement(new Toggle($lang->translateString('ui.flags.natural'), false));
                            $form->setCallable(function (Player $player, $data) use ($selectedOption, $form) {
                                $item = ItemFactory::get(ItemIds::WOODEN_SHOVEL);
                                $item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Loader::FAKE_ENCH_ID)));
                                $item->setCustomName(Loader::PREFIX . TextFormat::BOLD . TextFormat::DARK_PURPLE . ucfirst($selectedOption) . ' brush');
                                $item->setLore(BrushCommand::generateLore($form->getContent(), $data));
                                $flags = BrushCommand::translateElementsToFlags($form->getContent(), $data);
                                $item->setNamedTagEntry(new CompoundTag("MagicWE", [
                                    new IntTag("type", ShapeGenerator::TYPE_CUBE),
                                    new StringTag("blocks", $data[0]),
                                    new FloatTag("width", $data[1]),
                                    new FloatTag("height", $data[1]),
                                    new FloatTag("depth", $data[1]),
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
                            $form = new CustomForm(Loader::PREFIX . TextFormat::BOLD . TextFormat::DARK_PURPLE . $lang->translateString('ui.brush.settings.title', [ucfirst($selectedOption)]));
                            $form->addElement(new Label($lang->translateString('ui.brush.options.flags')));
                            $form->addElement(new Toggle($lang->translateString('ui.flags.keepexistingblocks'), false));
                            $form->addElement(new Toggle($lang->translateString('ui.flags.keepair'), false));
                            $form->addElement(new Toggle($lang->translateString('ui.flags.hollow'), false));
                            $form->addElement(new Toggle($lang->translateString('ui.flags.natural'), false));
                            $form->setCallable(function (Player $player, $data) use ($selectedOption, $form) {
                                $item = ItemFactory::get(ItemIds::WOODEN_SHOVEL);
                                $item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Loader::FAKE_ENCH_ID)));
                                $item->setCustomName(Loader::PREFIX . TextFormat::BOLD . TextFormat::DARK_PURPLE . ucfirst($selectedOption) . ' brush');
                                $item->setLore(BrushCommand::generateLore($form->getContent(), $data));
                                $flags = BrushCommand::translateElementsToFlags($form->getContent(), $data);
                                $item->setNamedTagEntry(new CompoundTag("MagicWE", [
                                    new IntTag("type", ShapeGenerator::TYPE_CUSTOM),
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
                case $lang->translateString('ui.flags.keepexistingblocks'):
                    {
                        if ($data[$i]) $flags[] = "-keepblocks";
                        break;
                    }
                case $lang->translateString('ui.flags.keepair'):
                    {
                        if ($data[$i]) $flags[] = "-keepair";
                        break;
                    }
                case $lang->translateString('ui.flags.hollow'):
                    {
                        if ($data[$i]) $flags[] = "-h";
                        break;
                    }
                case $lang->translateString('ui.flags.hollowclosed'):
                    {
                        if ($data[$i]) $flags[] = "-hc";
                        break;
                    }
                case $lang->translateString('ui.flags.natural'):
                    {
                        if ($data[$i]) $flags[] = "-n";
                        break;
                    }
            }
        }
        return API::flagParser($flags);
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
