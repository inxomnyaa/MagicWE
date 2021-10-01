<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\selection\info;

use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use Exception;
use InvalidArgumentException;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\commands\args\BlocksArgument;
use xenialdan\MagicWE2\exception\SelectionException;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\BlockPalette;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\task\action\CountAction;
use xenialdan\MagicWE2\task\AsyncActionTask;

class CountCommand extends BaseCommand
{

	/**
	 * This is where all the arguments, permissions, sub-commands, etc would be registered
	 * @throws ArgumentOrderException
	 * @throws InvalidArgumentException
	 */
	protected function prepare(): void
	{
		$this->registerArgument(0, new BlocksArgument("blocks", true));
		$this->registerArgument(1, new TextArgument("flags", true));
		$this->setPermission("we.command.selection.info.count");
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
		if (!$sender instanceof Player) {
			$sender->sendMessage(TF::RED . $lang->translateString('error.runingame'));
			return;
		}
		/** @var Player $sender */
		try {
			$filterBlocks = $args["blocks"] ?? BlockPalette::CREATE();
			$session = SessionHelper::getUserSession($sender);
			if (is_null($session)) {
				throw new SessionException($lang->translateString('error.nosession', [Loader::getInstance()->getName()]));
			}
			$selection = $session->getLatestSelection();
			if (is_null($selection)) {
				throw new SelectionException($lang->translateString('error.noselection'));
			}
			if (!$selection->isValid()) {
				throw new SelectionException($lang->translateString('error.selectioninvalid'));
			}
			if ($selection->getWorld() !== $sender->getWorld()) {
				$session->sendMessage(TF::GOLD . $lang->translateString('warning.differentworld'));
			}
			Server::getInstance()->getAsyncPool()->submitTask(
				new AsyncActionTask(
					$session->getUUID(),
					$selection,
					new CountAction(),
					$selection->getShape()->getTouchedChunks($selection->getWorld()),
					BlockPalette::CREATE(),
					$filterBlocks
				)
			);
		} catch (Exception $error) {
			$sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
			$sender->sendMessage($this->getUsage());
		}
	}
}
