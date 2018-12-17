<?php

namespace xenialdan\MagicWE2\task;

use pocketmine\block\Block;
use pocketmine\level\format\Chunk;
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

class AsyncCopyTask extends AsyncTask {

	private $start;
	private $chunks;
	private $playerUUID;
	private $selection;
	private $flags;
	private $clipboard;

	/**
	 * AsyncCopyTask constructor.
	 * @param Selection $selection
	 * @param UUID $playerUUID
	 * @param Chunk[] $chunks
	 * @param int $flags
	 * @throws \Exception
	 */
	public function __construct(Selection $selection, UUID $playerUUID, array $chunks, int $flags) {
		$this->start = microtime(true);
		$this->chunks = serialize($chunks);
		$this->playerUUID = serialize($playerUUID);
		$this->selection = serialize($selection);
		$this->flags = $flags;
		$this->clipboard = serialize(new Clipboard($selection->getLevel(), [], $selection->pos1->x, $selection->pos1->y, $selection->pos1->z, $selection->pos2->x, $selection->pos2->y, $selection->pos2->z));
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
		/** @var Clipboard $clipboard */
		$clipboard = unserialize($this->clipboard);
		$totalCount = $selection->getTotalCount();
		$copied = $this->copyBlocks($selection, $manager, $clipboard);
		$this->setResult(compact("clipboard", "copied", "totalCount"));
	}

	/**
	 * @param Selection $selection
	 * @param AsyncChunkManager $manager
	 * @param Clipboard $clipboard
	 * @return int
	 * @throws \Exception
	 */
	private function copyBlocks(Selection $selection, AsyncChunkManager $manager, Clipboard &$clipboard): int {
		$blockCount = $selection->getTotalCount();
		$i = 0;
		$this->publishProgress([0, "Running, copied $i blocks out of $blockCount 0%% done"]);
		/** @var Block $block */
		foreach ($selection->getBlocks($manager, [], $this->flags) as $block) {
			$clipboard->pushBlock($block);
			$i++;
			if (floor(($i - 1) / $blockCount) < floor($i / $blockCount)) {//this prevents spamming packets
				$this->publishProgress([round($i / $blockCount), "Running, copied $i blocks out of $blockCount" . round($i / $blockCount) . "%% done"]);
			}
		}
		return $i;
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
			$copied = $result["copied"];//todo use extract()
			$clipboard = $result["clipboard"];
			$totalCount = $result["totalCount"];
			$player->sendMessage(Loader::$prefix . TextFormat::GREEN . "Async Copy succeed, took " . date("i:s:", microtime(true) - $this->start) . strval(round(microtime(true) - $this->start, 1, PHP_ROUND_HALF_DOWN)) . ", copied $copied blocks out of $totalCount.");
			$session->setClipboards([0 => $clipboard]);
		}
	}
}