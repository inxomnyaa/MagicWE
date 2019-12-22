<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\selection\info;

use ArgumentCountError;
use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\BaseCommand;
use Error;
use Exception;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;

class SizeCommand extends BaseCommand
{

    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     */
    protected function prepare(): void
    {
        $this->setPermission("we.command.selection.info.size");
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
                throw new Exception($lang->translateString('error.nosession', [Loader::getInstance()->getName()]));
            }
            $selection = $session->getLatestSelection();
            if (is_null($selection)) {
                throw new Exception($lang->translateString('error.noselection'));
            }
            if (!$selection->isValid()) {
                throw new Exception($lang->translateString('error.selectioninvalid'));
            }
            if ($selection->getLevel() !== $sender->getLevel()) {
                $sender->sendMessage(Loader::PREFIX . TF::GOLD . $lang->translateString('warning.differentlevel'));
            }
            $session->sendMessage(TF::DARK_AQUA . $lang->translateString('command.size'));
            $session->sendMessage(TF::AQUA . "Total: {$selection->getShape()->getTotalCount()} X: {$selection->getSizeX()} Y: {$selection->getSizeY()} Z: {$selection->getSizeZ()}");
        } catch (Exception $error) {
            $sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (ArgumentCountError $error) {
            $sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (Error $error) {
            Loader::getInstance()->getLogger()->logException($error);
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
        }
    }
}
