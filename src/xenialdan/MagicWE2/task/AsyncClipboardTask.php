<?php

namespace xenialdan\MagicWE2\task;

use Exception;
use Generator;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\UUID;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\clipboard\Clipboard;
use xenialdan\MagicWE2\clipboard\CopyClipboard;
use xenialdan\MagicWE2\clipboard\RevertClipboard;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\session\UserSession;

class AsyncClipboardTask extends MWEAsyncTask
{

    const TYPE_PASTE = 0;
    const TYPE_SCHEMATIC = 1;
    const TYPE_STRUCTURE = 2;

    /** @var string */
    private $touchedChunks;
    /** @var string */
    private $clipboard;
    /** @var int */
    private $type;
    /** @var int */
    private $flags;

    /**
     * AsyncClipboardTask constructor.
     * @param CopyClipboard $clipboard
     * @param UUID $sessionUUID
     * @param string[] $touchedChunks serialized chunks
     * @param int $type The type of clipboard pasting.
     * @param int $flags
     */
    public function __construct(UUID $sessionUUID, CopyClipboard $clipboard, array $touchedChunks, $type = self::TYPE_PASTE, int $flags = API::FLAG_BASE)
    {
        $this->start = microtime(true);
        #$clipboard->pasteChunks = $touchedChunks;
        $clipboard->pasteChunks = array_map(function ($chunk) {
            return Chunk::fastDeserialize($chunk);
        }, $touchedChunks);
        $this->touchedChunks = serialize($touchedChunks);
        $this->sessionUUID = $sessionUUID->toString();
        $this->clipboard = serialize($clipboard);
        $this->flags = $flags;
        $this->type = $type;
    }

    /**
     * Actions to execute when run
     *
     * @return void
     * @throws Exception
     */
    public function onRun()
    {
        $this->publishProgress([0, "Start"]);

        /** @var CopyClipboard $clipboard */
        $clipboard = unserialize($this->clipboard);
        #$clipboard->pasteChunks = array_map(function ($chunk) {
        #    return Chunk::fastDeserialize($chunk);
        #}, $clipboard->pasteChunks);
        $pasteChunkManager = Clipboard::getChunkManager($clipboard->pasteChunks);

        $oldBlocks = iterator_to_array($this->execute($clipboard, $pasteChunkManager, $changed));

        $resultChunks = $pasteChunkManager->getChunks();
        $resultChunks = array_filter($resultChunks, function (Chunk $chunk) {
            return $chunk->hasChanged();
        });
        $this->setResult(compact("resultChunks", "oldBlocks", "changed"));
    }

    /**
     * @param CopyClipboard $clipboard
     * @param AsyncChunkManager $pasteChunkManager
     * @param null|int $changed
     * @return Generator|Block[] blocks before the change
     * @throws Exception
     */
    private function execute(CopyClipboard $clipboard, AsyncChunkManager $pasteChunkManager, ?int &$changed): Generator
    {
        $blockCount = $clipboard->getShape()->getTotalCount();
        $chunkManager = Clipboard::getChunkManager($clipboard->chunks);
        $lastprogress = 0;
        $changed = 0;
        $this->publishProgress([0, "Running, changed $changed blocks out of $blockCount"]);
        if (!BlockFactory::isInit()) BlockFactory::init();
        /** @var Block $block */
        foreach ($clipboard->getShape()->getBlocks($chunkManager, [], $this->flags) as $block) {
            var_dump("Block clipboard used", $block);
            $block1 = $pasteChunkManager->getBlockAt($block->x, $block->y, $block->z)->setComponents($block->x, $block->y, $block->z);
            var_dump("Block pastechunk had", $block);
            yield $block1;//TODO check
            $pasteChunkManager->setBlockAt($block->x, $block->y, $block->z, $block);
            var_dump("Block clipboard pasted", $block);
            $changed++;
            $progress = floor($changed / $blockCount * 100);
            if ($lastprogress < $progress) {//this prevents spamming packets
                $this->publishProgress([$progress, "Running, changed $changed blocks out of $blockCount"]);
                $lastprogress = $progress;
            }
        }
    }

    /**
     * @param Server $server
     * @throws Exception
     */
    public function onCompletion(Server $server): void
    {
        try {
            $session = SessionHelper::getSessionByUUID(UUID::fromString($this->sessionUUID));
            if ($session instanceof UserSession) $session->getBossBar()->hideFromAll();
        } catch (SessionException $e) {
            Loader::getInstance()->getLogger()->logException($e);
            $session = null;
        }
        $result = $this->getResult();
        $undoChunks = array_map(function ($chunk) {
            return Chunk::fastDeserialize($chunk);
        }, unserialize($this->touchedChunks));
        $oldBlocks = $result["oldBlocks"];
        $changed = $result["changed"];
        /** @var Chunk[] $resultChunks */
        $resultChunks = $result["resultChunks"];
        /** @var CopyClipboard $clipboard */
        $clipboard = unserialize($this->clipboard);
        $totalCount = $clipboard->getShape()->getTotalCount();
        /** @var Level $level */
        $level = $clipboard->getLevel();
        foreach ($resultChunks as $hash => $chunk) {
            $level->setChunk($chunk->getX(), $chunk->getZ(), $chunk, false);
        }
        if (is_null($session)) return;
        switch ($this->type) {
            case self::TYPE_PASTE:
            {//TODO translation
                $session->sendMessage(TF::GREEN . "Async " . (API::hasFlag($this->flags, API::FLAG_POSITION_RELATIVE) ? "relative" : "absolute") . " Clipboard pasting succeed, took " . $this->generateTookString() . ", $changed blocks out of $totalCount changed.");
                $session->addRevert(new RevertClipboard($clipboard->levelid, $undoChunks, $oldBlocks));
                break;
            }
        }
    }
}