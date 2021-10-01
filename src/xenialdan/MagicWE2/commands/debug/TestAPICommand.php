<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\debug;

use CortexPE\Commando\BaseCommand;
use Exception;
use InvalidArgumentException;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\BlockPalette;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\task\action\TestAction;
use xenialdan\MagicWE2\task\AsyncActionTask;

class TestAPICommand extends BaseCommand
{

	/**
	 * This is where all the arguments, permissions, sub-commands, etc would be registered
	 * @throws InvalidArgumentException
	 */
	protected function prepare(): void
	{
		$this->setPermission("we.command.debug");
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
	{
		$lang = Loader::getInstance()->getLanguage();
		if ($sender instanceof Player && SessionHelper::hasSession($sender)) {
			try {
				$lang = SessionHelper::getUserSession($sender)->getLanguage();
			} catch (SessionException $e) {
			}
		}
		try {
			//TODO REMOVE DEBUG
			$pluginSession = SessionHelper::createPluginSession(Loader::getInstance());
			$selection = new Selection($pluginSession->getUUID(), Server::getInstance()->getWorldManager()->getDefaultWorld(), 0, 0, 0, 0, 0, 0);
			$pluginSession->addSelection($selection);
			Server::getInstance()->getAsyncPool()->submitTask(
				new AsyncActionTask(
					$pluginSession->getUUID(),
					$selection,
					new TestAction(),
					$selection->getShape()->getTouchedChunks($selection->getWorld()),
					BlockPalette::fromString("minecraft:snow_block"),
					BlockPalette::fromString("minecraft:tnt")
				)
			);
			$selection = new Selection($pluginSession->getUUID(), Server::getInstance()->getWorldManager()->getDefaultWorld(), 0, 0, 0, 1, 1, 1);
			Server::getInstance()->getAsyncPool()->submitTask(
				new AsyncActionTask(
					$pluginSession->getUUID(),
					$selection,
					new TestAction(),
					$selection->getShape()->getTouchedChunks($selection->getWorld()),
					BlockPalette::fromString("minecraft:snow_block"),
					BlockPalette::fromString("minecraft:tnt")
				)
			);
			$selection = new Selection($pluginSession->getUUID(), Server::getInstance()->getWorldManager()->getDefaultWorld(), 0, 0, 0, 2, 2, 2);
			Server::getInstance()->getAsyncPool()->submitTask(
				new AsyncActionTask(
					$pluginSession->getUUID(),
					$selection,
					new TestAction(),
					$selection->getShape()->getTouchedChunks($selection->getWorld()),
					BlockPalette::fromString("minecraft:snow_block"),
					BlockPalette::fromString("minecraft:tnt")
				)
			);
			$selection = new Selection($pluginSession->getUUID(), Server::getInstance()->getWorldManager()->getDefaultWorld(), 0, 0, 0, 1, 2, 3);
			Server::getInstance()->getAsyncPool()->submitTask(
				new AsyncActionTask(
					$pluginSession->getUUID(),
					$selection,
					new TestAction(),
					$selection->getShape()->getTouchedChunks($selection->getWorld()),
					BlockPalette::fromString("minecraft:snow_block"),
					BlockPalette::fromString("minecraft:tnt")
				)
			);
		} catch (Exception $error) {
			Loader::getInstance()->getLogger()->logException($error);
			$sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
			$sender->sendMessage($this->getUsage());
		}
	}
}
