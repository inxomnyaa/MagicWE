<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\selection;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\session\Session;

class HPos2Command extends BaseCommand
{

    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     */
    protected function prepare(): void
    {
        $this->setPermission("we.command.selection.hpos");
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
            /** @var Session $session */
            $session = SessionHelper::getUserSession($sender);
            if (is_null($session)) {
                throw new \Exception("No session was created - probably no permission to use " . Loader::getInstance()->getName());
            }
            $selection = $session->getLatestSelection() ?? $session->addSelection(new Selection($session->getUUID(), $sender->getLevel())); // TODO check if the selection inside of the session updates
            if (is_null($selection)) {
                throw new \Error("No selection created - Check the console for errors");
            }
            $target = $sender->getTargetBlock(Loader::getInstance()->getToolDistance());
            if ($target === null) {
                $sender->sendMessage(Loader::PREFIX . TF::RED . "No target block found. Increase tool range with //setrange if needed");
                return;
            }
            $selection->setPos2($target);
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
}
