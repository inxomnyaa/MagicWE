<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\clipboard;

use CortexPE\Commando\args\BooleanArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use Exception;
use InvalidArgumentException;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\commands\args\RotateAngleArgument;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\task\action\RotateAction;
use xenialdan\MagicWE2\task\AsyncClipboardActionTask;

class RotateCommand extends BaseCommand
{
	/**
	 * This is where all the arguments, permissions, sub-commands, etc would be registered
	 * @throws ArgumentOrderException
	 * @throws InvalidArgumentException
	 */
	protected function prepare(): void
	{
		$this->registerArgument(0, new RotateAngleArgument("angle", false));
		$this->registerArgument(1, new BooleanArgument("aroundOrigin", true));
		$this->setPermission("we.command.clipboard.rotate");
		//$this->setUsage("//rotate <degrees: 1|2|3|-1|-2|-3>");
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
			$angle = (int)$args["angle"];
			//$aroundOrigin = $args["aroundOrigin"] ?? true;
			$sender->sendMessage(Loader::PREFIX . $lang->translateString('command.rotate.try', [$angle]));
			$session = SessionHelper::getUserSession($sender);
			if (is_null($session)) {
				throw new SessionException($lang->translateString('error.nosession', [Loader::getInstance()->getName()]));
			}
			$clipboard = $session->getCurrentClipboard();
			if (!$clipboard instanceof SingleClipboard) {
				throw new SessionException($lang->translateString('error.noclipboard'));
			}
			$action = new RotateAction($angle/*, $aroundOrigin*/);//TODO reenable origin support if error fixed: does not rotate. Let's see if PHPStan find it for me!
			#$offset = $selection->getShape()->getMinVec3()->subtract($session->getPlayer()->asVector3()->floor())->floor();
			#$action->setClipboardVector($offset);
			var_dump($action);
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
		}
	}
}
