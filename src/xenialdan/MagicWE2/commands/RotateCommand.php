<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use xenialdan\MagicWE2\API;
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
        $this->setPermission("we.command.rotate");
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
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . $lang->translateString('runingame'));
            return;
        }
        /** @var Player $sender */
        try {
            $rotation = intval($args["degrees"]);
            $sender->sendMessage(Loader::$prefix . "Trying to rotate clipboard by " . 90 * $rotation . " degrees");
            $session = API::getSession($sender);
            if (is_null($session)) {
                throw new \Exception("No session was created - probably no permission to use " . Loader::getInstance()->getName());
            }
            $clipboard = $session->getCurrentClipboard();
            if (is_null($clipboard)) {
                throw new \Exception("No clipboard found - create a clipboard first");
            }
            $clipboard->rotate($rotation);//TODO add back
            $sender->sendMessage(Loader::$prefix . "Successfully rotated clipboard");
        } catch (\Exception $error) {
            $sender->sendMessage(Loader::$prefix . TextFormat::RED . "Looks like you are missing an argument or used the command wrong!");
            $sender->sendMessage(Loader::$prefix . TextFormat::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (\ArgumentCountError $error) {
            $sender->sendMessage(Loader::$prefix . TextFormat::RED . "Looks like you are missing an argument or used the command wrong!");
            $sender->sendMessage(Loader::$prefix . TextFormat::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (\Error $error) {
            Loader::getInstance()->getLogger()->logException($error);
            $sender->sendMessage(Loader::$prefix . TextFormat::RED . $error->getMessage());
        }
    }
}
