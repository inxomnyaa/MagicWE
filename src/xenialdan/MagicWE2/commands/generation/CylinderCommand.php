<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\generation;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\shape\ShapeRegistry;

class CylinderCommand extends BaseCommand
{
    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     * @throws \CortexPE\Commando\exception\ArgumentOrderException
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
            $blocks = strval($args["blocks"]);
            $diameter = intval($args["diameter"]);
            $height = intval($args["height"] ?? 1);
            foreach ($messages as $message) {
                $sender->sendMessage($message);
            }
            if (!$error) {
                $session = API::getSession($sender);
                if (is_null($session)) {
                    throw new \Exception("No session was created - probably no permission to use " . Loader::getInstance()->getName());
                }
                API::createBrush($sender->getLevel()->getBlock($sender->add(0, $height / 2 + 1)), new CompoundTag(API::TAG_MAGIC_WE, [
                    new IntTag("type", ShapeRegistry::TYPE_CYLINDER),
                    new StringTag("blocks", $blocks),
                    new FloatTag("diameter", $diameter),
                    new FloatTag("height", $height),
                    new IntTag("flags", API::flagParser(explode(" ", strval($args["flags"])))),
                ]), $session);
            } else {
                throw new \InvalidArgumentException("Could not fill with the selected blocks");
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
