<?php

namespace xenialdan\MagicWE2\task;

use pocketmine\block\Block;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\Player;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\utils\UUID;
use xenialdan\BossBarAPI\API as BossBarAPI;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\AsyncChunkManager;
use xenialdan\MagicWE2\Clipboard;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\Selection;

class AsyncClipboardTask extends AsyncTask {

	const TYPE_UNDO = 0;
	const TYPE_REDO = 1;
	const TYPE_SET = 2;

	private $start;
	private $chunks;
	private $playerUUID;
	private $clipboard;
	private $type;
	private $undoClipboard;
    private $flags;

	/**
	 * AsyncFillTask constructor.
	 * @param Clipboard $clipboard
	 * @param UUID $playerUUID
	 * @param Chunk[] $chunks
     * @param int $type The type of clipboard pasting.
     * @param int $flags
	 * @throws \Exception
	 */
    public function __construct(Clipboard $clipboard, UUID $playerUUID, array $chunks, $type = self::TYPE_SET, int $flags = API::FLAG_BASE)
    {
		$this->start = microtime(true);
		$this->chunks = serialize($chunks);
		$this->playerUUID = serialize($playerUUID);
		$this->clipboard = serialize($clipboard);
        $this->flags = $flags;
		$this->type = $type;
        $this->undoClipboard = serialize(new Clipboard($clipboard->getLevel()));
	}

	/**
	 * Actions to execute when run
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function onRun() {
		$this->publishProgress([0, "Start"]);
		$chunks = unserialize($this->chunks, ["allowed_classes" => [Chunk::class]]);
		foreach ($chunks as $hash => $data) {
			$chunks[$hash] = Chunk::fastDeserialize($data);
		}
		/** @var Clipboard $clipboard */
		$clipboard = unserialize($this->clipboard);
		$manager = Selection::getChunkManager($chunks);
		unset($chunks);
		$totalCount = $clipboard->getTotalCount();
		/** @var Clipboard $undoClipboard */
		$undoClipboard = unserialize($this->undoClipboard);
		$changed = $this->editBlocks($clipboard, $manager, $undoClipboard);
		$chunks = $manager->getChunks();
		$this->setResult(compact("chunks", "changed", "totalCount", "undoClipboard"));
	}

	/**
	 * @param Clipboard $clipboard
	 * @param AsyncChunkManager $manager
	 * @param Clipboard &$undoClipboard
	 * @return int
	 * @throws \Exception
	 */
	private function editBlocks(Clipboard $clipboard, AsyncChunkManager $manager, Clipboard &$undoClipboard): int {
		$blockCount = $clipboard->getTotalCount();
		$i = 0;
		$changed = 0;
		$this->publishProgress([0, "Running, changed $changed blocks out of $blockCount 0%% done"]);
		$lastchunkx = -1;
		$lastchunkz = -1;
		/** @var Block $block */
        foreach ($clipboard->getBlocks($manager, [], $this->flags) as $block) {
			if ($block->x >> 4 !== $lastchunkx && $block->z >> 4 !== $lastchunkz) {
                var_dump($block);
				$lastchunkx = $block->x >> 4;
				$lastchunkz = $block->z >> 4;
				if (is_null(($c = $manager->getChunk($block->x >> 4, $block->z >> 4)))) {
					print PHP_EOL . "Not found: " . strval($block->x >> 4) . ":" . strval($block->z >> 4) . PHP_EOL;
					continue;
				}
			}
			$manager->setBlockAt($block->x, $block->y, $block->z, $block);
			if ($manager->getBlockArrayAt($block->x, $block->y, $block->z) === [$block->getId(), $block->getDamage()]) {
				$undoClipboard->pushBlock($block);
				$changed++;
			}
			///
			$i++;
			if (floor(($i - 1) / $blockCount) < floor($i / $blockCount)) {//this prevents spamming packets
				$this->publishProgress([round($i / $blockCount), "Running, changed $changed blocks out of $blockCount" . round($i / $blockCount) . "%% done"]);
			}
		}
		return $changed;
	}

	public function onProgressUpdate(Server $server, $progress) {
		[$percentage, $title] = $progress;
		$player = $server->getPlayerByUUID(unserialize($this->playerUUID));
		if (is_null($player)) return;
		$session = API::getSession($player);
		if (is_null($session)) return;
		BossBarAPI::setPercentage(intval($percentage), $session->getBossBarId(), [$player]);
		BossBarAPI::setTitle($title, $session->getBossBarId(), [$player]);
	}

    /**
     * @param Server $server
     * @throws \Exception
     */
    public function onCompletion(Server $server)
    {
		$result = $this->getResult();
		$player = $server->getPlayerByUUID(unserialize($this->playerUUID));
		if ($player instanceof Player) {
			$session = API::getSession($player);
			if (is_null($session)) return;
			$bpk = new BossEventPacket();
			$bpk->bossEid = $session->getBossBarId();
			$bpk->eventType = BossEventPacket::TYPE_HIDE;
			$player->dataPacket($bpk);
			$changed = $result["changed"];//todo use extract()
			$totalCount = $result["totalCount"];
			switch ($this->type) {
				case self::TYPE_SET:
					{
						$player->sendMessage(Loader::$prefix . TextFormat::GREEN . "Async Clipboard pasting succeed, took " . date("i:s:", microtime(true) - $this->start) . strval(round(microtime(true) - $this->start, 1, PHP_ROUND_HALF_DOWN)) . ", $changed blocks out of $totalCount changed.");
						if ($result["undoClipboard"] instanceof Clipboard)
							$session->addUndo($result["undoClipboard"]);
						else $player->sendMessage(TextFormat::RED . "undoClipboard is not a clipboard");//TODO prettify or fail safe
						break;
					}
				case self::TYPE_UNDO:
					{
						$player->sendMessage(Loader::$prefix . TextFormat::GREEN . "Async Undo succeed, took " . date("i:s:", microtime(true) - $this->start) . strval(round(microtime(true) - $this->start, 1, PHP_ROUND_HALF_DOWN)) . ", $changed blocks out of $totalCount changed.");
						if ($result["undoClipboard"] instanceof Clipboard)
							$session->addRedo($result["undoClipboard"]);
						else $player->sendMessage(TextFormat::RED . "undoClipboard is not a clipboard");//TODO prettify or fail safe
						break;
					}
				case self::TYPE_REDO:
					{
						$player->sendMessage(Loader::$prefix . TextFormat::GREEN . "Async Redo succeed, took " . date("i:s:", microtime(true) - $this->start) . strval(round(microtime(true) - $this->start, 1, PHP_ROUND_HALF_DOWN)) . ", $changed blocks out of $totalCount changed.");
						if ($result["undoClipboard"] instanceof Clipboard)
							$session->addUndo($result["undoClipboard"]);
						else $player->sendMessage(TextFormat::RED . "undoClipboard is not a clipboard");//TODO prettify or fail safe
						break;
					}
			}
		}
		/** @var Chunk[] $chunks */
		$chunks = $result["chunks"];
		print "onCompletion chunks count: " . count($chunks);
		var_dump(count($chunks));
		/** @var Selection $selection */
		$clipboard = unserialize($this->clipboard);
		if ($clipboard instanceof Clipboard) {
			/** @var Level $level */
			$level = $clipboard->getLevel();
			foreach ($chunks as $hash => $chunk) {
				$level->setChunk($chunk->getX(), $chunk->getZ(), $chunk, false);
			}
		} else throw new \Error("Not a clipboard");
	}
}