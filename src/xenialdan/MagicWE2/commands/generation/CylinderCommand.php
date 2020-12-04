<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\generation;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use Exception;
use InvalidArgumentException;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\selection\shape\Cylinder;

class CylinderCommand extends BaseCommand
{
    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     * @throws ArgumentOrderException
     * @throws InvalidArgumentException
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new RawStringArgument("blocks", false));
        $this->registerArgument(1, new IntegerArgument("diameter", false));
        $this->registerArgument(2, new IntegerArgument("height", true));
        $this->registerArgument(3, new TextArgument("flags", true));
        $this->setPermission("we.command.generation.cyl");
    }

    /**
     * @param CommandSender $sender
     * @param string $aliasUsed
     * @param BaseArgument[] $args
     * @throws AssumptionFailedError
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
            $messages = [];
            $error = false;
            $blocks = (string)$args["blocks"];
            $diameter = (int)$args["diameter"];
            $height = (int)($args["height"] ?? 1);
            $newblocks = API::blockParser($blocks, $messages, $error);
            foreach ($messages as $message) {
                $sender->sendMessage($message);
            }
            if (!$error) {
                $session = SessionHelper::getUserSession($sender);
                if (is_null($session)) {
                    throw new SessionException($lang->translateString('error.nosession', [Loader::getInstance()->getName()]));
                }
                $cyl = new Cylinder($sender->getPosition()->asVector3()->floor(), $height, $diameter);
                $cylSelection = new Selection($session->getUUID(), $sender->getWorld());
                $cylSelection->setShape($cyl);
                $hasFlags = isset($args["flags"]);
                API::fillAsync($cylSelection, $session, $newblocks, $hasFlags ? API::flagParser(explode(" ", (string)$args["flags"])) : API::FLAG_BASE);
            } else {
                throw new InvalidArgumentException("Could not fill with the selected blocks");
            }
        } catch (Exception $error) {
            $sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        }
    }
}
