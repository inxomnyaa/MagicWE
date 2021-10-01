<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\selection;

use CortexPE\Commando\BaseCommand;
use Error;
use Exception;
use InvalidArgumentException;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\Position;
use pocketmine\world\World;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\session\UserSession;

class ChunkCommand extends BaseCommand
{

	/**
	 * This is where all the arguments, permissions, sub-commands, etc would be registered
	 * @throws InvalidArgumentException
	 */
	protected function prepare(): void
	{
		$this->setPermission("we.command.selection.chunk");
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
			$selection = $session->getLatestSelection() ?? $session->addSelection(new Selection($session->getUUID(), $sender->getWorld())); // TODO check if the selection inside of the session updates
			if (is_null($selection)) {
				throw new Error("No selection created - Check the console for errors");
			}
			$chunk = $sender->getWorld()->getOrLoadChunkAtPosition($sender->getPosition());
			if (is_null($chunk)) {
				throw new Error("Could not find a chunk at your position");
			}
			$x = $sender->getPosition()->x >> 4;
			$z = $sender->getPosition()->x >> 4;
			$selection->setPos1(Position::fromObject(new Vector3($x * 16, 0, $z * 16), $sender->getWorld()));
			$selection->setPos2(Position::fromObject(new Vector3($x * 16 + 15, World::Y_MAX, $z * 16 + 15), $sender->getWorld()));
		} catch (Exception $error) {
			$sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
			$sender->sendMessage($this->getUsage());
		} catch (Error $error) {
			Loader::getInstance()->getLogger()->logException($error);
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
		}
	}
}
