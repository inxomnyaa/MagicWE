<?php

namespace xenialdan\MagicWE2\task;

use Exception;
use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use pocketmine\uuid\UUID;
use pocketmine\world\format\io\FastChunkSerializer;
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
    public function onRun(): void
    {
        $this->publishProgress([0, "Start"]);
        $chunks = unserialize($this->touchedChunks/*, ['allowed_classes' => [false]]*/);//TODO test pm4
        foreach ($chunks as $hash => $data) {
            $chunks[$hash] = FastChunkSerializer::deserialize($data);
        }
        /** @var Selection $selection */
        $selection = unserialize($this->selection/*, ['allowed_classes' => [Selection::class]]*/);//TODO test pm4
        $manager = Shape::getChunkManager($chunks);
        unset($chunks);
        /** @var Block[] $newBlocks */
        $newBlocks = unserialize($this->newBlocks/*, ['allowed_classes' => [Block::class]]*/);//TODO test pm4
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
            if (is_null($lastchunkx) || ($block->getPos()->x >> 4 !== $lastchunkx && $block->getPos()->z >> 4 !== $lastchunkz)) {
                $lastchunkx = $block->getPos()->x >> 4;
                $lastchunkz = $block->getPos()->z >> 4;
                if (is_null($manager->getChunk($block->getPos()->x >> 4, $block->getPos()->z >> 4))) {
                    #print PHP_EOL . "Not found: " . strval($block->x >> 4) . ":" . strval($block->z >> 4) . PHP_EOL;
                    continue;
                }
            }
            BlockFactory::getInstance();
            $block1 = $manager->getBlockArrayAt($block->getPos()->getFloorX(), $block->getPos()->getFloorY(), $block->getPos()->getFloorZ());
            $tostring = (BlockFactory::getInstance()->get($block1[0], $block1[1]))->getName() . " " . $block1[0] . ":" . $block1[1];
            if (!array_key_exists($tostring, $counts)) {
                $counts[$tostring] = 0;
            }
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
     * @throws InvalidArgumentException
     * @throws AssumptionFailedError
     */
    public function onCompletion(): void
    {
        try {
            $session = SessionHelper::getSessionByUUID(UUID::fromString($this->sessionUUID));
            if ($session instanceof UserSession) {
                $session->getBossBar()->hideFromAll();
            }
            $result = $this->getResult();
            $counts = $result["counts"];
            $totalCount = $result["totalCount"];
            $session->sendMessage(TF::GREEN . $session->getLanguage()->translateString('task.count.success', [$this->generateTookString()]));
            $session->sendMessage(TF::DARK_AQUA . $session->getLanguage()->translateString('task.count.result', [count($counts), $totalCount]));
            uasort($counts, static function ($a, $b) {
                if ($a === $b) {
                    return 0;
                }
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
