<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\clipboard;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;

class RotateCommand extends BaseCommand
{
    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     * @throws \CortexPE\Commando\exception\ArgumentOrderException
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new IntegerArgument("degrees", false));
        $this->setPermission("we.command.clipboard.rotate");
        $this->setUsage("//rotate <degrees: 1|2|3|-1|-2|-3>");
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
            $rotation = intval($args["degrees"]);
            $sender->sendMessage(Loader::PREFIX . $lang->translateString('command.rotate.try', [90 * $rotation]));
            $session = SessionHelper::getUserSession($sender);
            if (is_null($session)) {
                throw new \Exception($lang->translateString('error.nosession', [Loader::getInstance()->getName()]));
            }
            $clipboard = $session->getCurrentClipboard();
            if (is_null($clipboard)) {
                throw new \Exception($lang->translateString('error.noclipboard'));
            }
            $clipboard->rotate($rotation);//TODO add back
            $sender->sendMessage(Loader::PREFIX . $lang->translateString('command.rotate.success'));
        } catch (\Exception $error) {
            $sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (\ArgumentCountError $error) {
            $sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (\Error $error) {
            Loader::getInstance()->getLogger()->logException($error);
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
        }
    }
}
