<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\clipboard;

use ArgumentCountError;
use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use Error;
use Exception;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\commands\args\MirrorAxisArgument;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\task\action\FlipAction;
use xenialdan\MagicWE2\task\AsyncClipboardActionTask;

class FlipCommand extends BaseCommand
{

    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     * @throws ArgumentOrderException
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new MirrorAxisArgument("axis", false));
        $this->setPermission("we.command.clipboard.flip");
        //$this->setUsage("//flip <axis: X|Z|XZ>");
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
            $axis = strval($args["axis"]);
            $sender->sendMessage(Loader::PREFIX . $lang->translateString('command.flip.try', [$axis]));
            $session = SessionHelper::getUserSession($sender);
            if (is_null($session)) {
                throw new Exception($lang->translateString('error.nosession', [Loader::getInstance()->getName()]));
            }
            $clipboard = $session->getCurrentClipboard();
            if (!$clipboard instanceof SingleClipboard) {
                throw new Exception($lang->translateString('error.noclipboard'));
            }
            $action = new FlipAction($axis);
            #$offset = $selection->getShape()->getMinVec3()->subtract($session->getPlayer()->asVector3()->floor())->floor();
            #$action->setClipboardVector($offset);
            Server::getInstance()->getAsyncPool()->submitTask(
                new AsyncClipboardActionTask(
                    $session->getUUID(),
                    $clipboard->selection,
                    $action,
                    $clipboard
                )
            );
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
