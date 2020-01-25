<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\clipboard;

use ArgumentCountError;
use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use Error;
use Exception;
use InvalidArgumentException;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;
use ReflectionClass;
use xenialdan\MagicWE2\clipboard\Clipboard;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;

class FlipCommand extends BaseCommand
{

    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     * @throws ArgumentOrderException
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new TextArgument("direction", false));
        $this->setPermission("we.command.clipboard.flip");
        $this->setUsage("//flip <direction: X|Y|Z|UP|DOWN|WEST|EAST|NORTH|SOUTH...>");
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
            $reflectionClass = new ReflectionClass(Clipboard::class);
            $constants = $reflectionClass->getConstants();
            $args2 = array_flip(array_change_key_case(array_flip(explode(" ", strval($args["direction"]))), CASE_UPPER));
            $flags = Clipboard::DIRECTION_DEFAULT;
            foreach ($args2 as $arg) {
                if (array_key_exists("FLIP_" . $arg, $constants)) {
                    $flags ^= 1 << $constants["FLIP_" . $arg];
                } else {
                    throw new InvalidArgumentException('"' . $arg . '" is not a valid input');
                }
            }
            $sender->sendMessage(Loader::PREFIX . $lang->translateString('command.flip.try', [implode("|", $args2)]));
            $session = SessionHelper::getUserSession($sender);
            if (is_null($session)) {
                throw new Exception($lang->translateString('error.nosession', [Loader::getInstance()->getName()]));
            }
            $clipboard = $session->getCurrentClipboard();
            if (is_null($clipboard)) {
                throw new Exception($lang->translateString('error.noclipboard'));
            }
            /** @noinspection PhpUndefinedMethodInspection */
            //$clipboard->flip($flags);
            $sender->sendMessage(Loader::PREFIX . $lang->translateString('command.flip.success'));
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
