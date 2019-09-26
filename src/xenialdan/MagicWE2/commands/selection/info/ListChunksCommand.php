<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\selection\info;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\level\format\Chunk;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;

class ListChunksCommand extends BaseCommand
{

    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     */
    protected function prepare(): void
    {
        $this->setPermission("we.command.selection.info.listchunks");
    }

    /**
     * @param CommandSender $sender
     * @param string $aliasUsed
     * @param BaseArgument[] $args
     */
    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $lang = Loader::getInstance()->getLanguage();
        if (!$sender instanceof Player) {
            $sender->sendMessage(TF::RED . $lang->translateString('error.runingame'));
            return;
        }
        /** @var Player $sender */
        try {
            $session = SessionHelper::getUserSession($sender);
            if (is_null($session)) {
                throw new \Exception(Loader::getInstance()->getLanguage()->translateString('error.nosession', [Loader::getInstance()->getName()]));
            }
            $selection = $session->getLatestSelection();
            if (is_null($selection)) {
                throw new \Exception(Loader::getInstance()->getLanguage()->translateString('error.noselection'));
            }
            if (!$selection->isValid()) {
                throw new \Exception(Loader::getInstance()->getLanguage()->translateString('error.selectioninvalid'));
            }
            if ($selection->getLevel() !== $sender->getLevel()) {
                $sender->sendMessage(Loader::PREFIX . TF::GOLD . Loader::getInstance()->getLanguage()->translateString('warning.differentlevel'));
            }
            $touchedChunks = $selection->getShape()->getTouchedChunks($selection->getLevel());
            $session->sendMessage(TF::DARK_AQUA . Loader::getInstance()->getLanguage()->translateString('command.listchunks.found', [count($touchedChunks)]));
            foreach ($touchedChunks as $chunkHash => $touchedChunk) {
                $chunk = Chunk::fastDeserialize($touchedChunk);
                $biomes = [];
                for ($x = 0; $x < 16; $x++)
                    for ($z = 0; $z < 16; $z++)
                        $biomes[] = (Chunk::fastDeserialize($touchedChunk)->getBiomeId($x, $z));
                $biomes = array_unique($biomes);
                $biomecount = count($biomes);
                $biomes = implode(", ", $biomes);
                $session->sendMessage(TF::AQUA . "ID: {$chunkHash} | X: {$chunk->getX()} Z: {$chunk->getZ()} | Subchunks: {$chunk->getHeight()} | Biomes: ($biomecount) $biomes");
            }
        } catch (\Exception $error) {
            $sender->sendMessage(Loader::PREFIX . TF::RED . Loader::getInstance()->getLanguage()->translateString('error.command-error'));
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (\ArgumentCountError $error) {
            $sender->sendMessage(Loader::PREFIX . TF::RED . Loader::getInstance()->getLanguage()->translateString('error.command-error'));
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
            $sender->sendMessage($this->getUsage());
        } catch (\Error $error) {
            Loader::getInstance()->getLogger()->logException($error);
            $sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
        }
    }
}
