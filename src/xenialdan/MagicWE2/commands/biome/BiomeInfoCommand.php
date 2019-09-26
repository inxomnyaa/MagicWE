<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\biome;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\level\biome\Biome;
use pocketmine\level\format\Chunk;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;

class BiomeInfoCommand extends BaseCommand
{
    const FLAG_T = "t";
    const FLAG_P = "p";

    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     * @throws \CortexPE\Commando\exception\ArgumentOrderException
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new TextArgument("flags", true));
        $this->setPermission("we.command.biome.info");
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
        if (!$sender instanceof Player) {
            $sender->sendMessage(TF::RED . $lang->translateString('error.runingame'));
            return;
        }
        /** @var Player $sender */
        try {
            $session = SessionHelper::getUserSession($sender);
            if (is_null($session)) {
                throw new \Exception($lang->translateString('error.nosession', [Loader::getInstance()->getName()]));
            }
            $biomeNames = (new \ReflectionClass(Biome::class))->getConstants();
            $biomeNames = array_flip($biomeNames);
            unset($biomeNames[Biome::MAX_BIOMES]);
            array_walk($biomeNames, function (&$value, $key) {
                $value = Biome::getBiome($key)->getName();
            });
            if (!empty(($flags = ltrim(strval($args["flags"] ?? ""), "-")))) {
                $flagArray = str_split($flags);
                if (in_array(self::FLAG_T, $flagArray)) {
                    $target = $sender->getTargetBlock(Loader::getInstance()->getToolDistance());
                    if ($target === null) {
                        $sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.notarget'));
                        return;
                    }
                    $biomeId = $target->getLevel()->getChunkAtPosition($target)->getBiomeId($target->getX() % 16, $target->getZ() % 16);
                    $session->sendMessage(TF::DARK_AQUA . $lang->translateString('command.biomeinfo.attarget'));
                    $session->sendMessage(TF::AQUA . "ID: $biomeId Name: " . $biomeNames[$biomeId]);
                }
                if (in_array(self::FLAG_P, $flagArray)) {
                    $biomeId = $sender->getLevel()->getChunkAtPosition($sender)->getBiomeId($sender->getX() % 16, $sender->getZ() % 16);
                    $session->sendMessage(TF::DARK_AQUA . $lang->translateString('command.biomeinfo.atposition'));
                    $session->sendMessage(TF::AQUA . "ID: $biomeId Name: " . $biomeNames[$biomeId]);
                }
                return;
            }
            $selection = $session->getLatestSelection();
            if (is_null($selection)) {
                throw new \Exception($lang->translateString('error.noselection'));
            }
            if (!$selection->isValid()) {
                throw new \Exception($lang->translateString('error.selectioninvalid'));
            }
            if ($selection->getLevel() !== $sender->getLevel()) {
                $sender->sendMessage(Loader::PREFIX . TF::GOLD . $lang->translateString('warning.differentlevel'));
            }
            $touchedChunks = $selection->getShape()->getTouchedChunks($selection->getLevel());
            $biomes = [];
            foreach ($touchedChunks as $touchedChunk) {
                for ($x = 0; $x < 16; $x++)
                    for ($z = 0; $z < 16; $z++)
                        $biomes[] = (Chunk::fastDeserialize($touchedChunk)->getBiomeId($x, $z));
            }
            $biomes = array_unique($biomes);
            $session->sendMessage(TF::DARK_AQUA . $lang->translateString('command.biomeinfo.result', [count($biomes)]));
            foreach ($biomes as $biomeId) {
                $session->sendMessage(TF::AQUA . $lang->translateString('command.biomeinfo.result.line', [$biomeId, $biomeNames[$biomeId]]));
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
