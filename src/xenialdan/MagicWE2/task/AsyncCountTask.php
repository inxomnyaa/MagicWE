<?php

namespace xenialdan\MagicWE2\task;

use Exception;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\level\format\Chunk;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\UUID;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\selection\shape\Shape;
use xenialdan\MagicWE2\session\UserSession;

class AsyncCountTask extends MWEAsyncTask
{
    /** @var string */
    private $touchedChunks;
    /** @var string */
    private $selection;
    /** @var int */
    private $flags;
    /** @var string */
    private $newBlocks;

    /**
     * AsyncCountTask constructor.
     * @param Selection $selection
     * @param UUID $sessionUUID
     * @param string[] $touchedChunks serialized chunks
     * @param Block[] $newBlocks
     * @param int $flags
     * @throws Exception
     */
    public function __construct(UUID $sessionUUID, Selection $selection, array $touchedChunks, array $newBlocks, int $flags)
    {
        $this->start = microtime(true);
        $this->touchedChunks = serialize($touchedChunks);
        $this->sessionUUID = $sessionUUID->toString();
        $this->selection = serialize($selection);
        $this->newBlocks = serialize($newBlocks);
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
        $chunks = unserialize($this->touchedChunks);
        foreach ($chunks as $hash => $data) {
            $chunks[$hash] = Chunk::fastDeserialize($data);
        }
        /** @var Selection $selection */
        $selection = unserialize($this->selection);
        $manager = Shape::getChunkManager($chunks);
        unset($chunks);
        /** @var Block[] $newBlocks */
        $newBlocks = unserialize($this->newBlocks);
        $totalCount = $selection->getShape()->getTotalCount();
        $counts = $this->countBlocks($selection, $manager, $newBlocks);
        $this->setResult(compact("counts", "totalCount"));
    }

    /**
     * @param Selection $selection
     * @param AsyncChunkManager $manager
     * @param Block[] $newBlocks
     * @return array
     * @throws Exception
     */
    private function countBlocks(Selection $selection, AsyncChunkManager $manager, array $newBlocks): array
    {
        $blockCount = $selection->getShape()->getTotalCount();
        $changed = 0;
        $this->publishProgress([0, "Running, changed $changed blocks out of $blockCount"]);
        $lastchunkx = $lastchunkz = null;
        $lastprogress = 0;
        $counts = [];
        /** @var Block $block */
        foreach ($selection->getShape()->getBlocks($manager, $newBlocks, $this->flags) as $block) {
            if (is_null($lastchunkx) || $block->x >> 4 !== $lastchunkx && $block->z >> 4 !== $lastchunkz) {
                $lastchunkx = $block->x >> 4;
                $lastchunkz = $block->z >> 4;
                if (is_null($manager->getChunk($block->x >> 4, $block->z >> 4))) {
                    #print PHP_EOL . "Not found: " . strval($block->x >> 4) . ":" . strval($block->z >> 4) . PHP_EOL;
                    continue;
                }
            }
            if (!BlockFactory::isInit()) BlockFactory::init();
            $block1 = $manager->getBlockArrayAt($block->getFloorX(), $block->getFloorY(), $block->getFloorZ());
            $tostring = (BlockFactory::get($block1[0], $block1[1]))->getName() . " " . $block1[0] . ":" . $block1[1];
            if (!array_key_exists($tostring, $counts)) $counts[$tostring] = 0;
            $counts[$tostring]++;
            $changed++;
            $progress = floor($changed / $blockCount * 100);
            if ($lastprogress < $progress) {//this prevents spamming packets
                $this->publishProgress([$progress, "Running, counting $changed blocks out of $blockCount"]);
                $lastprogress = $progress;
            }
        }
        return $counts;
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
            $result = $this->getResult();
            $counts = $result["counts"];
            $totalCount = $result["totalCount"];
            $session->sendMessage(TF::GREEN . $session->getLanguage()->translateString('task.count.success', [$this->generateTookString()]));
            $session->sendMessage(TF::DARK_AQUA . $session->getLanguage()->translateString('task.count.result', [count($counts), $totalCount]));
            uasort($counts, function ($a, $b) {
                if ($a === $b) return 0;
                return ($a > $b) ? -1 : 1;
            });
            foreach ($counts as $block => $count) {
                $session->sendMessage(TF::AQUA . $count . "x | " . round($count / $totalCount * 100) . "% | " . $block);
            }
        } catch (SessionException $e) {
            Loader::getInstance()->getLogger()->logException($e);
        }
    }
}