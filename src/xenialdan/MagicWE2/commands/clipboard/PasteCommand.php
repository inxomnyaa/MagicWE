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
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\clipboard\CopyClipboard;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;

class PasteCommand extends BaseCommand
{

    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     * @throws ArgumentOrderException
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new TextArgument("flags", true));
        $this->setPermission("we.command.clipboard.paste");
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
            $clipboard = $session->getCurrentClipboard();
            if (is_null($clipboard)) {
                throw new Exception($lang->translateString('error.noclipboard'));
            }
            if (!$clipboard instanceof CopyClipboard) {//TODO check if i want to pass ANY clipboard instead
                throw new Exception($lang->translateString('error.noclipboard'));
            }
            /*if (!API::hasFlag(API::flagParser(explode(" ", strval($args["flags"]))), API::FLAG_POSITION_RELATIVE)) {
                $clipboard->setOffset(new Vector3());//TODO fix? Move to API
            }*/
            $hasFlags = isset($args["flags"]);
            API::pasteAsync($clipboard, $session, $sender->asPosition(), $hasFlags ? API::flagParser(explode(" ", strval($args["flags"]))) : API::FLAG_BASE);
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
