<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\BaseCommand;
use pocketmine\block\Block;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\session\PluginSession;
use xenialdan\MagicWE2\task\action\TestAction;
use xenialdan\MagicWE2\task\AsyncActionTask;

class TestCommand extends BaseCommand
{

    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     */
    protected function prepare(): void
    {
        $this->setPermission("we.command.test");
    }

    /**
     * @param CommandSender $sender
     * @param string $aliasUsed
     * @param BaseArgument[] $args
     */
    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        /** @var Player $sender */
        try {
            //TODO REMOVE DEBUG
            $pluginSession = new PluginSession(Loader::getInstance());
            API::addSession($pluginSession);
            $selection = new Selection($pluginSession->getUUID(), Server::getInstance()->getDefaultLevel(), 0, 0, 0, 0, 0, 0);
            $pluginSession->addSelection($selection);
            Server::getInstance()->getAsyncPool()->submitTask(
                new AsyncActionTask(
                    $pluginSession->getUUID(),
                    $selection,
                    new TestAction(),
                    $selection->getShape()->getTouchedChunks($selection->getLevel()),
                    [Block::get(Block::SNOW)],
                    [Block::get(Block::TNT)]
                )
            );
            $selection = new Selection($pluginSession->getUUID(), Server::getInstance()->getDefaultLevel(), 0, 0, 0, 1, 1, 1);
            Server::getInstance()->getAsyncPool()->submitTask(
                new AsyncActionTask(
                    $pluginSession->getUUID(),
                    $selection,
                    new TestAction(),
                    $selection->getShape()->getTouchedChunks($selection->getLevel()),
                    [Block::get(Block::SNOW)],
                    [Block::get(Block::TNT)]
                )
            );
            $selection = new Selection($pluginSession->getUUID(), Server::getInstance()->getDefaultLevel(), 0, 0, 0, 2, 2, 2);
            Server::getInstance()->getAsyncPool()->submitTask(
                new AsyncActionTask(
                    $pluginSession->getUUID(),
                    $selection,
                    new TestAction(),
                    $selection->getShape()->getTouchedChunks($selection->getLevel()),
                    [Block::get(Block::SNOW)],
                    [Block::get(Block::TNT)]
                )
            );
            $selection = new Selection($pluginSession->getUUID(), Server::getInstance()->getDefaultLevel(), 0, 0, 0, 1, 2, 3);
            Server::getInstance()->getAsyncPool()->submitTask(
                new AsyncActionTask(
                    $pluginSession->getUUID(),
                    $selection,
                    new TestAction(),
                    $selection->getShape()->getTouchedChunks($selection->getLevel()),
                    [Block::get(Block::SNOW)],
                    [Block::get(Block::TNT)]
                )
            );
        } catch (\Exception $error) {
            Loader::getInstance()->getLogger()->logException($error);
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
