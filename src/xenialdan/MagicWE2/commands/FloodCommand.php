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
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use xenialdan\customui\elements\Input;
use xenialdan\customui\elements\Label;
use xenialdan\customui\elements\Slider;
use xenialdan\customui\windows\CustomForm;
use xenialdan\MagicWE2\Loader;

/*use pocketmine\form\CustomForm;
use pocketmine\form\element\CustomFormElement;
use pocketmine\form\element\Dropdown;
use pocketmine\form\element\Input;
use pocketmine\form\element\Label;
use pocketmine\form\element\Slider;
use pocketmine\form\element\Toggle;*/

class FloodCommand extends WECommand
{
    public function __construct(Plugin $plugin)
    {
        parent::__construct("/flood", $plugin);
        $this->setPermission("we.command.flood");
        $this->setDescription("Opens the flood tool menu");
        $this->setUsage("//flood");
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
                $form = new CustomForm(Loader::$prefix . TextFormat::BOLD . TextFormat::DARK_PURPLE . $lang->translateString('ui.flood.title'));
                $form->addElement(new Slider($lang->translateString('ui.flood.options.limit'), 0, 5000, 500.0));
                $form->addElement(new Input($lang->translateString('ui.flood.options.blocks'), $lang->translateString('ui.flood.options.blocks.placeholder')));
                $form->addElement(new Label($lang->translateString('ui.flood.options.label.infoapply')));
                $form->setCallable(function (Player $player, $data) use ($form) {
                    $item = ItemFactory::get(ItemIds::BUCKET, 1);
                    $item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::PROTECTION)));
                    $item->setCustomName(Loader::$prefix . TextFormat::BOLD . TextFormat::DARK_PURPLE . 'Flood');
                    $item->setLore(BrushCommand::generateLore($form->getContent(), $data));
                    $item->setNamedTagEntry(new CompoundTag("MagicWE", [
                        new StringTag("blocks", $data[1]),
                        new FloatTag("limit", $data[0]),
                    ]));
                    $player->getInventory()->addItem($item);
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
}
