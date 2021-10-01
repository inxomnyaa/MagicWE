<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\debug;

use CortexPE\Commando\BaseCommand;
use Exception;
use InvalidArgumentException;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\Loader;
use function array_filter;
use function count;
use function file_put_contents;
use function implode;
use function substr;
use const LOCK_EX;

class GenerateCommandsMDCommand extends BaseCommand
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
		try {
			$cmds = [];
			foreach (array_filter(Loader::getInstance()->getServer()->getCommandMap()->getCommands(), static function (Command $command) use ($sender) {
				return str_contains($command->getName(), "/");
			}) as $cmd) {
				/** @var Command $cmd */
				$cmds[$cmd->getName()] = $cmd;
			}
			$lines = [
				'| Command | Description | Usage | Alias |',
				'|---|---|---|---|'
			];
			/** @var BaseCommand $command */
			foreach ($cmds as $command) {
				$aliasStr = '';
				if (!empty(($aliases = $command->getAliases()))) {
					foreach ($aliases as $i => $alias) {
						$aliases[$i] = "/" . $alias;
					}
					$aliasStr = '`' . implode(", ", $aliases) . '`';
				}
				$usage = $command->getUsage();
				//subcommand hack
				$subCommands = $command->getSubCommands();
				if (count($subCommands) > 0) {
					$pos = stripos($usage, " \n");
					if ($pos !== false) $usage = substr($usage, 0, $pos);
				}
				$lines[] = "| `/{$command->getName()}` | {$command->getDescription()} | `$usage` | $aliasStr |";
				foreach ($subCommands as $subCommand) {
					$aliasStr = '';
					if (!empty(($aliases = $subCommand->getAliases()))) {
						foreach ($aliases as $i => $alias) {
							$aliases[$i] = "/" . $alias;
						}
						$aliasStr = '`' . implode(", ", $aliases) . '`';
					}
					$lines[] = "| `/{$command->getName()} {$subCommand->getName()}` | {$subCommand->getDescription()} | `{$command->getName()} {$subCommand->getUsageMessage()}` | $aliasStr |";
				}
			}
			$path = Loader::getInstance()->getDataFolder() . 'COMMANDS.MD';
			file_put_contents($path, '# Commands
This list is automatically generated. If you have noticed an error, please create an issue.

' . implode('
', $lines), LOCK_EX);
		} catch (Exception $error) {
			Loader::getInstance()->getLogger()->logException($error);
			$sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
			$sender->sendMessage($this->getUsage());
		}
	}
}
