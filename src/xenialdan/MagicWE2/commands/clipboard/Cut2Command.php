<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\clipboard;

use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use Exception;
use InvalidArgumentException;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\exception\SelectionException;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\BlockPalette;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\task\action\CutAction;
use xenialdan\MagicWE2\task\AsyncActionTask;

class Cut2Command extends BaseCommand
{

	/**
	 * This is where all the arguments, permissions, sub-commands, etc would be registered
	 * @throws ArgumentOrderException
	 * @throws InvalidArgumentException
	 */
	protected function prepare(): void
	{
		//TODO //cut [filter] [set]
		$this->registerArgument(0, new TextArgument("flags", true));
		$this->setPermission("we.command.clipboard.cut");
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
				$sender->sendMessage(Loader::PREFIX . TF::GOLD . $lang->translateString('warning.differentworld'));
			}
			#$hasFlags = isset($args["flags"]);
			$action = new CutAction();
			$offset = $selection->getShape()->getMinVec3()->subtractVector($session->getPlayer()->getPosition()->asVector3()->floor())->floor();
			$action->setClipboardVector($offset);
			Server::getInstance()->getAsyncPool()->submitTask(
				new AsyncActionTask(
					$session->getUUID(),
					$selection,
					$action,
					$selection->getShape()->getTouchedChunks($selection->getWorld()),
					BlockPalette::fromString("air"),//TODO option
					BlockPalette::CREATE()
				)
			);
		} catch (Exception $error) {
			$sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
			$sender->sendMessage($this->getUsage());
		}
	}
}
