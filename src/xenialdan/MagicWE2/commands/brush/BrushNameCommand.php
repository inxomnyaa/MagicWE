<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\brush;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use Exception;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use TypeError;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\session\UserSession;
use xenialdan\MagicWE2\tool\Brush;

class BrushNameCommand extends BaseSubCommand
{
	/**
	 * This is where all the arguments, permissions, sub-commands, etc would be registered
	 * @throws ArgumentOrderException
	 */
	protected function prepare(): void
	{
		$this->registerArgument(0, new RawStringArgument("name", true));
		$this->setPermission("we.command.brush.name");
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
			if (!$session instanceof UserSession) {
				throw new SessionException($lang->translateString('error.nosession', [Loader::getInstance()->getName()]));
			}
			$brush = $session->getBrushes()->getBrushFromItem($sender->getInventory()->getItemInHand());
			if ($brush instanceof Brush) {
				if (empty($args["name"])) {
					$sender->sendMessage($brush->getName());
					return;
				}
				$brush->properties->setCustomName((string)$args["name"]);
				$session->sendMessage(TF::GREEN . $lang->translateString('command.brushname.set', [$brush->getName()]));
				$session->getBrushes()->replaceBrush($brush);
			}
		} catch (Exception | TypeError $error) {
			$sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
			$sender->sendMessage($this->getUsageMessage());
		}
	}
}