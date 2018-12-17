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
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\Selection;

class AsyncFillTask extends AsyncTask {

	private $start;
	private $chunks;
	private $playerUUID;
	private $selection;
	private $flags;
	private $newBlocks;

	/**
	 * AsyncFillTask constructor.
	 * @param Selection $selection
	 * @param UUID $playerUUID
	 * @param Chunk[] $chunks
	 * @param Block[] $newBlocks
	 * @param int $flags
	 */
	public function __construct(Selection $selection, UUID $playerUUID, array $chunks, array $newBlocks, int $flags) {
		$this->start = microtime(true);
		$this->chunks = serialize($chunks);
		$this->playerUUID = serialize($playerUUID);
		$this->selection = serialize($selection);
		$this->newBlocks = serialize($newBlocks);
		$this->flags = $flags;
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
		print "onRun chunks count 1: " . count($chunks);
		foreach ($chunks as $hash => $data) {
			$chunks[$hash] = Chunk::fastDeserialize($data);
		}
		print "onRun chunks count 2: " . count($chunks);
		/** @var Selection $selection */
		$selection = unserialize($this->selection);
		$manager = Selection::getChunkManager($chunks);
		unset($chunks);
		/** @var Block[] $newBlocks */
		$newBlocks = unserialize($this->newBlocks);
		$totalCount = $selection->getTotalCount();
		$changed = $this->editBlocks($selection, $manager, $newBlocks);
		$chunks = $manager->getChunks();
		print "onRun chunks count 3: " . count($chunks);
		$this->setResult(compact("chunks", "changed", "totalCount"));
	}

	/**
	 * @param Selection $selection
	 * @param AsyncChunkManager $manager
	 * @param Block[] $newBlocks
	 * @return int
	 * @throws \Exception
	 */
	private function editBlocks(Selection $selection, AsyncChunkManager $manager, array $newBlocks): int {
		$blockCount = $selection->getTotalCount();
		$i = 0;
		$changed = 0;
		$lastchunkx = -1;
		$lastchunkz = -1;
		/** @var Block $block */
		foreach ($selection->getBlocks($manager, [], $this->flags) as $block) {
			if ($block->x >> 4 !== $lastchunkx && $block->z >> 4 !== $lastchunkz) {
				$lastchunkx = $block->x >> 4;
				$lastchunkz = $block->z >> 4;
				if (is_null(($c = $manager->getChunk($block->x >> 4, $block->z >> 4))))
					print PHP_EOL . "Not found: " . strval($block->x >> 4) . ":" . strval($block->z >> 4) . PHP_EOL;
			}
			#var_dump($block->asVector3());
			#var_dump($block->__toString());
			#var_dump($manager->getBlockArrayAt($block->x,$block->y,$block->z));
			/** @var Block $new */
			if (count($newBlocks) === 1)
				$new = clone $newBlocks[0];
			else
				$new = clone $newBlocks[array_rand($newBlocks, 1)];
			if ($new->getId() === $block->getId() && $new->getDamage() === $block->getDamage()) continue;//skip same blocks
			$manager->setBlockAt($block->x, $block->y, $block->z, $new);
			if ($manager->getBlockArrayAt($block->x, $block->y, $block->z) !== [$block->getId(), $block->getDamage()])
				$changed++;
			///
			$i++;
			if (round(($i - 1) / $blockCount) < round($i / $blockCount)) {//this prevents spamming packets
				$this->publishProgress([(int)ceil($i / $blockCount), "Running, changed $changed blocks out of $blockCount" . round($i / $blockCount) . "%% done"]);
			}
		}
		$manager->setBlockIdAt($selection->getMinVec3()->x, $selection->getMinVec3()->y, $selection->getMinVec3()->z, Block::REDSTONE_BLOCK);
		$manager->setBlockIdAt($selection->getMaxVec3()->x, $selection->getMaxVec3()->y, $selection->getMaxVec3()->z, Block::DIAMOND_BLOCK);
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
			$player->sendMessage(Loader::$prefix . TextFormat::GREEN . "Async Fill succeed, took " . date("i:s:", microtime(true) - $this->start) . strval(round(microtime(true) - $this->start)) . ", $changed blocks out of $totalCount changed.");
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