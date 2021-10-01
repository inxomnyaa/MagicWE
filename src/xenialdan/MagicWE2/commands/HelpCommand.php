<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use Exception;
use InvalidArgumentException;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;

class HelpCommand extends BaseCommand
{
	/**
	 * This is where all the arguments, permissions, sub-commands, etc would be registered
	 * @throws ArgumentOrderException
	 * @throws InvalidArgumentException
	 */
	protected function prepare(): void
	{
		$this->registerArgument(0, new RawStringArgument("command", true));
		$this->setPermission("we.command.help");
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
			$cmds = [];
			if (empty($args["command"])) {
				foreach (array_filter(Loader::getInstance()->getServer()->getCommandMap()->getCommands(), static function (Command $command) use ($sender) {
					return str_contains($command->getName(), "/") && $command->testPermissionSilent($sender);
				}) as $cmd) {
					/** @var Command $cmd */
					$cmds[$cmd->getName()] = $cmd;
				}
			} else if (($cmd = Loader::getInstance()->getServer()->getCommandMap()->getCommand("/" . str_replace("/", "", TF::clean((string)$args["command"])))) instanceof Command) {
				/** @var Command $cmd */
				$cmds[$cmd->getName()] = $cmd;
			} else {
				$sender->sendMessage(TF::RED . str_replace("/", "//", $lang->translateString("%commands.generic.notFound")));
				return;
			}
			foreach ($cmds as $command) {
				$message = TF::RESET . TF::BOLD . TF::GOLD . "/" . $command->getName();
				if (!empty(($aliases = $command->getAliases()))) {
					foreach ($aliases as $i => $alias) {
						$aliases[$i] = "/" . $alias;
					}
					$message .= TF::RESET . TF::LIGHT_PURPLE . " [" . implode(",", $aliases) . "]";
				}
				$message .= TF::RESET . TF::WHITE . " " . $command->getDescription() . TF::EOL . " » " . $command->getUsage();
				$sender->sendMessage($message);
			}
		} catch (Exception $error) {
			$sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
			$sender->sendMessage($this->getUsage());
		}
	}
}
