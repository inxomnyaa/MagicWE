<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\region;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\Loader;

class OverlayCommand extends BaseCommand
{

    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     * @throws \CortexPE\Commando\exception\ArgumentOrderException
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new RawStringArgument("blocks", false));
        $this->setPermission("we.command.overlay");
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
            $messages = [];
            $error = false;
            $blocks = API::blockParser(strval($args["blocks"]), $messages, $error);
            foreach ($messages as $message) {
                $sender->sendMessage($message);
            }
            $return = !$error;
            if ($return) {
                $session = API::getSession($sender);
                if (is_null($session)) {
                    throw new \Exception("No session was created - probably no permission to use " . Loader::getInstance()->getName());
                }
                $selection = $session->getLatestSelection();
                if (is_null($selection)) {
                    throw new \Exception("No selection found - select an area first");
                }
                if (!$selection->isValid()) {
                    throw new \Exception("The selection is not valid! Check if all positions are set!");
                }
                if ($selection->getLevel() !== $sender->getLevel()) {
                    $sender->sendMessage(Loader::PREFIX . TF::GOLD . "[WARNING] You are editing in a level which you are currently not in!");
                }
                API::overlayReplaceAsync($selection, $session, [], $blocks, API::flagParser(explode(" ", strval($args["flags"]))));
            } else {
                throw new \InvalidArgumentException("Could not replace with the selected blocks");
            }
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
