<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\biome;

use CortexPE\Commando\BaseCommand;
use Exception;
use InvalidArgumentException;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\biome\Biome;
use pocketmine\world\biome\BiomeRegistry;
use ReflectionClass;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;

class BiomeListCommand extends BaseCommand
{

	/**
	 * This is where all the arguments, permissions, sub-commands, etc would be registered
	 * @throws InvalidArgumentException
	 */
	protected function prepare(): void
	{
		$this->setPermission("we.command.biome.list");
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
	{
		$lang = Loader::getInstance()->getLanguage();
		if ($sender instanceof Player && SessionHelper::hasSession($sender)) {
			try {
				$lang = SessionHelper::getUserSession($sender)->getLanguage();
				/** @var Player $sender */
				$session = SessionHelper::getUserSession($sender);
				if (is_null($session)) {
					throw new SessionException($lang->translateString('error.nosession', [Loader::getInstance()->getName()]));
				}
				$session->sendMessage(TF::DARK_AQUA . $lang->translateString('command.biomelist.title'));
				foreach ((new ReflectionClass(Biome::class))->getConstants() as $name => $value) {
					if ($value === Biome::MAX_BIOMES) continue;
					$name = BiomeRegistry::getInstance()->getBiome($value)->getName();
					$session->sendMessage(TF::AQUA . $lang->translateString('command.biomelist.result.line', [$value, $name]));
				}
			} catch (SessionException $e) {
			} catch (Exception $error) {
				$sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
				$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
				$sender->sendMessage($this->getUsage());
			}
		}
	}
}
