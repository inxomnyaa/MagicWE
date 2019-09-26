<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\biome;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\level\biome\Biome;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;

class BiomeListCommand extends BaseCommand
{

    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     */
    protected function prepare(): void
    {
        $this->setPermission("we.command.biome.list");
    }

    /**
     * @param CommandSender $sender
     * @param string $aliasUsed
     * @param BaseArgument[] $args
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
        /** @var Player $sender */
        try {
            $session = SessionHelper::getUserSession($sender);
            if (is_null($session)) {
                throw new \Exception($lang->translateString('error.nosession', [Loader::getInstance()->getName()]));
            }
            $session->sendMessage(TF::DARK_AQUA . $lang->translateString('command.biomelist.title'));
            foreach ((new \ReflectionClass(Biome::class))->getConstants() as $name => $value) {
                if ($value === Biome::MAX_BIOMES) continue;
                $name = Biome::getBiome($value)->getName();
                $session->sendMessage(TF::AQUA . $lang->translateString('command.biomelist.result.line', [$value, $name]));
            }
        } catch (\Exception $error) {
            $sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (\ArgumentCountError $error) {
            $sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (\Error $error) {
            Loader::getInstance()->getLogger()->logException($error);
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
        }
    }
}
