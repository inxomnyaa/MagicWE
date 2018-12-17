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

class AsyncReplaceTask extends AsyncTask {

	private $start;
	private $chunks;
	private $playerUUID;
	private $selection;
	private $flags;
	private $oldBlocks;
	private $newBlocks;
	private $undoClipboard;

	/**
	 * AsyncReplaceTask constructor.
	 * @param Selection $selection
	 * @param UUID $playerUUID
	 * @param Chunk[] $chunks
	 * @param Block[] $oldBlocks
	 * @param Block[] $newBlocks
	 * @param int $flags
	 * @throws \Exception
	 */
	public function __construct(Selection $selection, UUID $playerUUID, array $chunks, array $oldBlocks, array $newBlocks, int $flags) {
		$this->start = microtime(true);
		$this->chunks = serialize($chunks);
		$this->playerUUID = serialize($playerUUID);
		$this->selection = serialize($selection);
		$this->oldBlocks = serialize($oldBlocks);
		$this->newBlocks = serialize($newBlocks);
		$this->flags = $flags;
		$this->undoClipboard = serialize(new Clipboard($selection->getLevel(), [], $selection->pos1->x, $selection->pos1->y, $selection->pos1->z, $selection->pos2->x, $selection->pos2->y, $selection->pos2->z));
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
		/** @var Selection $selection */
		$selection = unserialize($this->selection);
		$manager = Selection::getChunkManager($chunks);
		unset($chunks);
		/** @var Block[] $oldBlocks */
		$oldBlocks = unserialize($this->oldBlocks);
		/** @var Block[] $newBlocks */
		$newBlocks = unserialize($this->newBlocks);
		$totalCount = $selection->getTotalCount();
		/** @var Clipboard $undoClipboard */
		$undoClipboard = unserialize($this->undoClipboard);
		$changed = $this->editBlocks($selection, $manager, $oldBlocks, $newBlocks, $undoClipboard);
		$chunks = $manager->getChunks();
		$this->setResult(compact("chunks", "changed", "totalCount", "undoClipboard"));
	}

	/**
	 * @param Selection $selection
	 * @param AsyncChunkManager $manager
	 * @param array $oldBlocks
	 * @param Block[] $newBlocks
	 * @param Clipboard &$undoClipboard
	 * @return int
	 * @throws \Exception
	 */
	private function editBlocks(Selection $selection, AsyncChunkManager $manager, array $oldBlocks, array $newBlocks, Clipboard &$undoClipboard): int {
		$blockCount = $selection->getTotalCount();
		$i = 0;
		$changed = 0;
		$this->publishProgress([0, "Running, changed $changed blocks out of $blockCount 0%% done"]);
		$lastchunkx = -1;
		$lastchunkz = -1;
		/** @var Block $block */
		foreach ($selection->getBlocks($manager, $oldBlocks, $this->flags) as $block) {
			if ($block->x >> 4 !== $lastchunkx && $block->z >> 4 !== $lastchunkz) {
				$lastchunkx = $block->x >> 4;
				$lastchunkz = $block->z >> 4;
				if (is_null(($c = $manager->getChunk($block->x >> 4, $block->z >> 4)))) {
					print PHP_EOL . "Not found: " . strval($block->x >> 4) . ":" . strval($block->z >> 4) . PHP_EOL;
					continue;
				}
			}
			/** @var Block $new */
			if (count($newBlocks) === 1)
				$new = clone $newBlocks[0];
			else
				$new = clone $newBlocks[array_rand($newBlocks, 1)];
			if ($new->getId() === $block->getId() && $new->getDamage() === $block->getDamage()) continue;//skip same blocks
			$manager->setBlockAt($block->x, $block->y, $block->z, $new);
			if ($manager->getBlockArrayAt($block->x, $block->y, $block->z) !== [$block->getId(), $block->getDamage()]) {
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

	public function onCompletion(Server $server) {
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
			$player->sendMessage(Loader::$prefix . TextFormat::GREEN . "Async Replace succeed, took " . date("i:s:", microtime(true) - $this->start) . strval(round(microtime(true) - $this->start, 1, PHP_ROUND_HALF_DOWN)) . ", $changed blocks out of $totalCount changed.");
			if ($result["undoClipboard"] instanceof Clipboard)
				$session->addUndo($result["undoClipboard"]);
			else $player->sendMessage(TextFormat::RED . "undoClipboard is not a clipboard");//TODO prettify
		}
		/** @var Chunk[] $chunks */
		$chunks = $result["chunks"];
		print "onCompletion chunks count: " . count($chunks);
		var_dump(count($chunks));
		/** @var Selection $selection */
		$selection = unserialize($this->selection);
		if ($selection instanceof Selection) {
			/** @var Level $level */
			$level = $selection->getLevel();
			foreach ($chunks as $hash => $chunk) {
				$level->setChunk($chunk->getX(), $chunk->getZ(), $chunk, false);
			}
		} else throw new \Error("Not a selection");
	}
}