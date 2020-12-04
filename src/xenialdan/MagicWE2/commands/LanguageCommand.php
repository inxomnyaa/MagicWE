<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use Exception;
use InvalidArgumentException;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\customui\elements\Dropdown;
use xenialdan\customui\elements\Label;
use xenialdan\customui\windows\CustomForm;
use xenialdan\MagicWE2\commands\args\LanguageArgument;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;

class LanguageCommand extends BaseCommand
{

    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     * @throws ArgumentOrderException
     * @throws InvalidArgumentException
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new LanguageArgument("language", true));
        $this->setPermission("we.command.language");
    }

    /**
     * @param CommandSender $sender
     * @param string $aliasUsed
     * @param BaseArgument[] $args
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
            $session = SessionHelper::getUserSession($sender);
            if (is_null($session)) {
                throw new SessionException($lang->translateString('error.nosession', [Loader::getInstance()->getName()]));
            }
            if (isset($args["language"])) {
                /** @var LanguageArgument $languageArgument */
                $languageArgument = $args["language"];
                $session->setLanguage((string)$languageArgument);
                return;
            }
            $languages = Loader::getInstance()->getLanguageList();
            $form = new CustomForm(Loader::PREFIX . TF::BOLD . TF::DARK_PURPLE . $lang->translateString('ui.language.title'));
            $form->addElement(new Label($lang->translateString('ui.language.label')));
            $dropdown = new Dropdown($lang->translateString('ui.language.dropdown'), array_values($languages));
            $dropdown->setOptionAsDefault($session->getLanguage()->getName());
            $form->addElement($dropdown);
            $form->setCallable(function (Player $player, $data) use ($session, $languages) {
                $langShort = array_search($data[1], $languages, true);
                if (!is_string($langShort)) {
                    throw new InvalidArgumentException("Invalid data received");
                }
                $session->setLanguage($langShort);
            });
            $sender->sendForm($form);
        } catch (Exception $error) {
            $sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        }
    }
}
