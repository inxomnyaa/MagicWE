<?php

namespace xenialdan\MagicWE2\task;

use Exception;
use Generator;
use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use pocketmine\uuid\UUID;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use xenialdan\MagicWE2\clipboard\RevertClipboard;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\selection\shape\Shape;
use xenialdan\MagicWE2\session\UserSession;

class AsyncReplaceTask extends MWEAsyncTask
{
	/** @var string */
	private $touchedChunks;
	/** @var string */
	private $selection;
	/** @var int */
	private $flags;
	/** @var string */
	private $replaceBlocks;
	/** @var string */
	private $newBlocks;

	/**
	 * AsyncReplaceTask constructor.
	 * @param Selection $selection
	 * @param UUID $sessionUUID
	 * @param string[] $touchedChunks serialized chunks
     * @param Block[] $replaceBlocks
     * @param Block[] $newBlocks
     * @param int $flags
     * @throws Exception
     */
    public function __construct(UUID $sessionUUID, Selection $selection, array $touchedChunks, array $replaceBlocks, array $newBlocks, int $flags)
    {
        $this->start = microtime(true);
        $this->sessionUUID = $sessionUUID->toString();
        $this->selection = serialize($selection);
        $this->touchedChunks = serialize($touchedChunks);
        $this->replaceBlocks = serialize($replaceBlocks);
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

		$touchedChunks = array_map(function ($chunk) {
			return FastChunkSerializer::deserialize($chunk);
		}, unserialize($this->touchedChunks, ['allowed_classes' => false]));//TODO test pm4

		$manager = Shape::getChunkManager($touchedChunks);
		unset($touchedChunks);

		/** @var Selection $selection */
		$selection = unserialize($this->selection, ['allowed_classes' => [Selection::class]]);//TODO test pm4

		/** @var Block[] $replaceBlocks */
		$replaceBlocks = unserialize($this->replaceBlocks, ['allowed_classes' => [Block::class]]);//TODO test pm4
		/** @var Block[] $newBlocks */
		$newBlocks = unserialize($this->newBlocks, ['allowed_classes' => [Block::class]]);//TODO test pm4

		$oldBlocks = iterator_to_array($this->execute($selection, $manager, $replaceBlocks, $newBlocks, $changed));

		$resultChunks = $manager->getChunks();
		$resultChunks = array_filter($resultChunks, function (Chunk $chunk) {
			return $chunk->isDirty();
		});
		$this->setResult(compact("resultChunks", "oldBlocks", "changed"));
	}

    /**
     * @param Selection $selection
     * @param AsyncChunkManager $manager
     * @param array $replaceBlocks
     * @param Block[] $newBlocks
     * @param null|int $changed
     * @return Generator|Block[]
     * @throws Exception
     */
    private function execute(Selection $selection, AsyncChunkManager $manager, array $replaceBlocks, array $newBlocks, ?int &$changed): Generator
    {
        $blockCount = $selection->getShape()->getTotalCount();
        $lastchunkx = $lastchunkz = null;
        $lastprogress = 0;
        $i = 0;
        $changed = 0;
        $this->publishProgress([0, "Running, changed $changed blocks out of $blockCount"]);
        /** @var Block $block */
        foreach ($selection->getShape()->getBlocks($manager, $replaceBlocks, $this->flags) as $block) {
			if (is_null($lastchunkx) || ($block->getPos()->x >> 4 !== $lastchunkx && $block->getPos()->z >> 4 !== $lastchunkz)) {
				$lastchunkx = $block->getPos()->x >> 4;
				$lastchunkz = $block->getPos()->z >> 4;
				if (is_null(($c = $manager->getChunk($block->getPos()->x >> 4, $block->getPos()->z >> 4)))) {
					#print PHP_EOL . "Not found: " . strval($block->x >> 4) . ":" . strval($block->z >> 4) . PHP_EOL;
					continue;
				}
			}
			$new = clone $newBlocks[array_rand($newBlocks)];
			if ($new->getId() === $block->getId() && $new->getMeta() === $block->getMeta()) continue;//skip same blocks
			yield $manager->getBlockAt($block->getPos()->getFloorX(), $block->getPos()->getFloorY(), $block->getPos()->getFloorZ())/*->setComponents($block->x, $block->y, $block->z)*/
			;
			$manager->setBlockAt($block->getPos()->getFloorX(), $block->getPos()->getFloorY(), $block->getPos()->getFloorZ(), $new);
            if ($manager->getBlockArrayAt($block->getPos()->getFloorX(), $block->getPos()->getFloorY(), $block->getPos()->getFloorZ()) !== [$block->getId(), $block->getMeta()]) {
                $changed++;
            }
			///
			$i++;
			$progress = floor($i / $blockCount * 100);
			if ($lastprogress < $progress) {//this prevents spamming packets
				$this->publishProgress([$progress, "Running, changed $changed blocks out of $blockCount"]);
				$lastprogress = $progress;
			}
		}
	}

	/**
	 * @throws InvalidArgumentException
	 * @throws AssumptionFailedError
	 * @throws Exception
	 * @throws Exception
	 */
	public function onCompletion(): void
	{
		try {
			$session = SessionHelper::getSessionByUUID(UUID::fromString($this->sessionUUID));
			if ($session instanceof UserSession) $session->getBossBar()->hideFromAll();
		} catch (SessionException $e) {
			Loader::getInstance()->getLogger()->logException($e);
			$session = null;
		}
		$result = $this->getResult();
		/** @var Chunk[] $resultChunks */
		$resultChunks = $result["resultChunks"];
		$undoChunks = array_map(function ($chunk) {
			return FastChunkSerializer::deserialize($chunk);
		}, unserialize($this->touchedChunks, ['allowed_classes' => false]));//TODO test pm4
		$oldBlocks = $result["oldBlocks"];
		$changed = $result["changed"];
		/** @var Selection $selection */
		$selection = unserialize($this->selection, ['allowed_classes' => [Selection::class]]);//TODO test pm4
		$totalCount = $selection->getShape()->getTotalCount();
		$world = $selection->getWorld();
		foreach ($resultChunks as $hash => $chunk) {
			$world->setChunk($chunk->getX(), $chunk->getZ(), $chunk, false);
		}
		if (!is_null($session)) {
			$session->sendMessage(TF::GREEN . $session->getLanguage()->translateString('task.replace.success', [$this->generateTookString(), $changed, $totalCount]));
			$session->addRevert(new RevertClipboard($selection->worldId, $undoChunks, $oldBlocks));
		}
	}
}