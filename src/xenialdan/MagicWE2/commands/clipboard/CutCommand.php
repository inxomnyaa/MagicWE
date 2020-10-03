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
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockLegacyIds;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;

class CutCommand extends BaseCommand
{

    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     * @throws ArgumentOrderException
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new TextArgument("flags", true));
        $this->setPermission("we.command.clipboard.cut");
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
            if ($selection->getWorld() !== $sender->getWorld()) {
                $sender->sendMessage(Loader::PREFIX . TF::GOLD . $lang->translateString('warning.differentlevel'));
            }
            $hasFlags = isset($args["flags"]);
            //TODO Temp hack - add cutAsync - Update 9th Feb. 2020 LEAVE THAT ALONE! IT WORKS, DO NOT TOUCH IT!
			API::copyAsync($selection, $session, $hasFlags ? API::flagParser(explode(" ", strval($args["flags"]))) : API::FLAG_BASE);
			API::fillAsync($selection, $session, [BlockFactory::getInstance()->get(BlockLegacyIds::AIR)], $hasFlags ? API::flagParser(explode(" ", strval($args["flags"]))) : API::FLAG_BASE);
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
