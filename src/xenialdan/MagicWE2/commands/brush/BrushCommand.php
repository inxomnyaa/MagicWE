<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\brush;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\BaseCommand;
use muqsit\invmenu\InvMenu;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\customui\elements\Button;
use xenialdan\customui\elements\Label;
use xenialdan\customui\elements\Toggle;
use xenialdan\customui\elements\UIElement;
use xenialdan\customui\windows\SimpleForm;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\session\UserSession;
use xenialdan\MagicWE2\tool\Brush;
use xenialdan\MagicWE2\tool\BrushProperties;

class BrushCommand extends BaseCommand
{
    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     * @throws \CortexPE\Commando\exception\SubCommandCollision
     */
    protected function prepare(): void
    {
        $this->registerSubCommand(new BrushNameCommand("name", "Get name or rename a brush"));
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
            $sender->sendMessage(TF::RED . $lang->translateString('runingame'));
            return;
        }
        /** @var Player $sender */
        try {
            $session = SessionHelper::getUserSession($sender);
            if (!$session instanceof UserSession) {
                throw new \Exception("No session was created - probably no permission to use " . Loader::getInstance()->getName());
            }
            $form = new SimpleForm(Loader::PREFIX . TF::BOLD . TF::DARK_PURPLE . $lang->translateString('ui.brush.title'), $lang->translateString('Brush main menu'));
            $form->addButton(new Button($lang->translateString('Create new')));
            $form->addButton(new Button($lang->translateString('Get session brush')));
            $form->addButton(new Button($lang->translateString('Edit brush in hand')));
            $form->setCallable(function (Player $player, $data) use ($lang, $form, $session) {
                try {
                    switch ($data) {
                        case $lang->translateString('Create new'):
                            {
                                $brush = new Brush(new BrushProperties());
                                if ($brush instanceof Brush) {
                                    $player->sendForm($brush->getForm());
                                }
                                break;
                            }
                        case $lang->translateString('Get session brush'):
                            {
                                $menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST)->readonly(false);
                                foreach ($session->getBrushes() as $brush) {
                                    $menu->getInventory()->addItem($brush->toItem());
                                }
                                $menu->send($player, "Session brushes");
                                break;
                            }
                        case $lang->translateString('Edit brush in hand'):
                            {
                                $brush = $session->getBrushFromItem($player->getInventory()->getItemInHand());
                                if ($brush instanceof Brush) {
                                    $player->sendForm($brush->getForm(false));
                                }
                                break;
                            }
                    }
                    return null;
                } catch (\Exception $error) {
                    $session->sendMessage(TF::RED . "An error occurred");
                    $session->sendMessage(TF::RED . $error->getMessage());
                }
            });
            $sender->sendForm($form);
        } catch (\Exception $error) {
            $sender->sendMessage(Loader::PREFIX . TF::RED . "Looks like you are missing an argument or used the command wrong!");
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (\ArgumentCountError $error) {
            $sender->sendMessage(Loader::PREFIX . TF::RED . "Looks like you are missing an argument or used the command wrong!");
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (\Error $error) {
            Loader::getInstance()->getLogger()->logException($error);
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
        }
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
