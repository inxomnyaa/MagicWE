<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\biome;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;

class SetBiomeCommand extends BaseCommand
{
    const FLAG_P = "p";

    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     * @throws \CortexPE\Commando\exception\ArgumentOrderException
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new IntegerArgument("biome", false));
        //TODO flags
        $this->setPermission("we.command.biome.set");
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
            $sender->sendMessage(TF::RED . $lang->translateString('error.runingame'));
            return;
        }
        /** @var Player $sender */
        try {
            $session = SessionHelper::getUserSession($sender);
            if (is_null($session)) {
                throw new \Exception(Loader::getInstance()->getLanguage()->translateString('error.nosession', [Loader::getInstance()->getName()]));
            }
            $selection = $session->getLatestSelection();
            if (is_null($selection)) {
                throw new \Exception(Loader::getInstance()->getLanguage()->translateString('error.noselection'));
            }
            if (!$selection->isValid()) {
                throw new \Exception(Loader::getInstance()->getLanguage()->translateString('error.selectioninvalid'));
            }
            if ($selection->getLevel() !== $sender->getLevel()) {
                $sender->sendMessage(Loader::PREFIX . TF::GOLD . Loader::getInstance()->getLanguage()->translateString('warning.differentlevel'));
            }
            $biomeId = intval($args["biome"]);
            API::setBiomeAsync($selection, $session, $biomeId);
        } catch (\Exception $error) {
            $sender->sendMessage(Loader::PREFIX . TF::RED . Loader::getInstance()->getLanguage()->translateString('error.command-error'));
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (\ArgumentCountError $error) {
            $sender->sendMessage(Loader::PREFIX . TF::RED . Loader::getInstance()->getLanguage()->translateString('error.command-error'));
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (\Error $error) {
            Loader::getInstance()->getLogger()->logException($error);
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
        }
    }
}
