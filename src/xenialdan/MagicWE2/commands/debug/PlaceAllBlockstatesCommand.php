<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\debug;

use CortexPE\Commando\BaseCommand;
use Exception;
use InvalidArgumentException;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\BlockStatesParser;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;

class PlaceAllBlockstatesCommand extends BaseCommand
{
	/**
	 * This is where all the arguments, permissions, sub-commands, etc would be registered
	 * @throws InvalidArgumentException
	 */
	protected function prepare(): void
	{
		$this->setPermission("we.command.test");
	}

	/**
	 * @param CommandSender $sender
	 * @param string $aliasUsed
	 * @param mixed[] $args
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
			BlockStatesParser::placeAllBlockstates($sender->getPosition());
		} catch (Exception $error) {
			$sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
			$sender->sendMessage($this->getUsage());
		}
	}
}
