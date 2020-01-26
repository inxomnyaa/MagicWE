<?php

namespace xenialdan\MagicWE2\task;

use Exception;
use pocketmine\block\Block;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\UUID;
use xenialdan\MagicWE2\clipboard\CopyClipboard;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\selection\shape\Shape;
use xenialdan\MagicWE2\session\UserSession;

class AsyncCopyTask extends MWEAsyncTask
{

    /** @var string */
    private $chunks;
    /** @var string */
    private $selection;
    /** @var string */
    private $offset;
    /** @var int */
    private $flags;

    /**
     * AsyncCopyTask constructor.
     * @param Selection $selection
     * @param Vector3 $offset
     * @param UUID $sessionUUID
     * @param string[] $chunks serialized chunks
     * @param int $flags
     * @throws Exception
     */
    public function __construct(UUID $sessionUUID, Selection $selection, Vector3 $offset, array $chunks, int $flags)
    {
        $this->start = microtime(true);
        $this->chunks = serialize($chunks);
        $this->sessionUUID = $sessionUUID->toString();
        $this->selection = serialize($selection);
        $this->offset = serialize($offset->asVector3());
        $this->flags = $flags;
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
        $chunks = array_map(function ($chunk) {
            return Chunk::fastDeserialize($chunk);
        }, unserialize($this->chunks));
        /** @var Selection $selection */
        $selection = unserialize($this->selection);
        var_dump("shape", $selection->getShape());
        $manager = Shape::getChunkManager($chunks);
        unset($chunks);
        $clipboard = new CopyClipboard($selection->levelid);
        $clipboard->setCenter(unserialize($this->offset));
        $totalCount = $selection->getShape()->getTotalCount();
        $copied = $this->copyBlocks($selection, $manager, $clipboard);
        $clipboard->setShape($selection->getShape());
        $clipboard->chunks = $manager->getChunks();
        $this->setResult(compact("clipboard", "copied", "totalCount"));
    }

    /**
     * @param Selection $selection
     * @param AsyncChunkManager $manager
     * @param CopyClipboard $clipboard
     * @return int
     * @throws Exception
     */
    private function copyBlocks(Selection $selection, AsyncChunkManager $manager, CopyClipboard &$clipboard): int
    {
        $blockCount = $selection->getShape()->getTotalCount();
        $i = 0;
        $lastprogress = 0;
        $this->publishProgress([0, "Running, copied $i blocks out of $blockCount"]);
        /** @var Block $block */
        foreach ($selection->getShape()->getBlocks($manager, [], $this->flags) as $block) {
            $chunk = $clipboard->chunks[Level::chunkHash($block->x >> 4, $block->z >> 4)] ?? null;
            if ($chunk === null) {
                $chunk = $manager->getChunk($block->x >> 4, $block->z >> 4);
                $clipboard->chunks[Level::chunkHash($block->x >> 4, $block->z >> 4)] = $chunk;
            }
            $manager->setBlockAt($block->getFloorX(), $block->getFloorY(), $block->getFloorZ(), $block);
            var_dump("copied manager block", $manager->getBlockAt($block->getFloorX(), $block->getFloorY(), $block->getFloorZ()));
            $i++;
            $progress = floor($i / $blockCount * 100);
            if ($lastprogress < $progress) {//this prevents spamming packets
                $this->publishProgress([$progress, "Running, copied $i blocks out of $blockCount"]);
                $lastprogress = $progress;
            }
        }
        return $i;
    }

    public function onCompletion(Server $server): void
    {
        try {
            $session = SessionHelper::getSessionByUUID(UUID::fromString($this->sessionUUID));
            if ($session instanceof UserSession) $session->getBossBar()->hideFromAll();
            $result = $this->getResult();
            $copied = $result["copied"];
            /** @var CopyClipboard $clipboard */
            $clipboard = $result["clipboard"];
            $totalCount = $result["totalCount"];
            $session->sendMessage(TF::GREEN . $session->getLanguage()->translateString('task.copy.success', [$this->generateTookString(), $copied, $totalCount]));
            $session->addClipboard($clipboard);
            var_dump("clipboard shape blocks", $clipboard->getBlocks(CopyClipboard::getChunkManager($clipboard->chunks)));
        } catch (SessionException $e) {
            Loader::getInstance()->getLogger()->logException($e);
        }
    }
}