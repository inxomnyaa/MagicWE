<?php

namespace xenialdan\MagicWE2\task;

use pocketmine\block\Block;
use pocketmine\command\CommandSender;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\Loader;

class AsyncFillTask extends AsyncTask{

	private $sender;
	private $selection;
	private $chunks;
	private $flags;
	private $changed = 0;
	private $time;
	private $blocks;
	private $newblocks;

	/**
	 * AsyncFillTask constructor.
	 * @param CommandSender $sender
	 * @param array $selection
	 * @param array $chunks
	 * @param array $blocks
	 * @param array $newblocks
	 * @param $flags
	 */
	public function __construct($sender, $selection = [], $chunks, $blocks = [], $newblocks = [], $flags){
		$this->time = microtime(TRUE);
		$this->sender = serialize($sender->getName());
		$this->chunks = serialize($chunks);
		$this->blocks = serialize($blocks);
		$this->newblocks = serialize($newblocks);
		$this->flags = $flags;
		$this->selection = serialize($selection);
	}

	public function onRun(){
		$chunks = unserialize($this->chunks);//TODO add undo
		foreach ($chunks as $hash => $data){
			$chunks[$hash] = Chunk::fastDeserialize($data);
		}
		/** @var Chunk[] $chunks */
		$selectionarray = unserialize($this->selection);
		/** @var Block[] $blocks */
		$blocks = unserialize($this->blocks);
		/** @var Block[] $newblocks */
		$newblocks = unserialize($this->newblocks);
		try{
			foreach ($blocks as $block){
				if ($block->y >= Level::Y_MAX || $block->y < 0) continue;
				if (API::hasFlag($this->flags, API::FLAG_HOLLOW) && ($block->x > $selectionarray["minx"] && $block->x < $selectionarray["maxx"]) && ($block->y > $selectionarray["miny"] && $block->y < $selectionarray["maxy"]) && ($block->z > $selectionarray["minz"] && $block->z < $selectionarray["maxz"])) continue;
				$newblock = $newblocks[array_rand($newblocks, 1)];
				/** @var Chunk $chunk */
				$chunk = $chunks[Level::chunkHash($block->x >> 4, $block->z >> 4)];
				if (API::hasFlag($this->flags, API::FLAG_KEEP_BLOCKS)){
					if ($chunk->getBlockId($block->x & 0x0f, $block->y, $block->z & 0x0f) !== Block::AIR) continue;
				}
				if (API::hasFlag($this->flags, API::FLAG_KEEP_AIR)){
					if ($chunk->getBlockId($block->x & 0x0f, $block->y, $block->z & 0x0f) === Block::AIR) continue;
				}
				if ($chunk->setBlock($block->x & 0x0f, $block->y, $block->z & 0x0f, $newblock->getId(), $newblock->getDamage())) $this->changed++;
			}
		} catch (\Exception $exception){
			$message = Loader::$prefix . TextFormat::RED . $exception->getMessage();
			$this->setResult([
				"chunks" => [],
				"message" => $message
			]);
			return;
		}
		$message = Loader::$prefix . TextFormat::GREEN . "Fill succeed, took " . round((microtime(TRUE) - $this->time), 2) . "s, " . $this->changed . " blocks out of " . $selectionarray["totalcount"] . " changed.";

		$this->setResult([
			"chunks" => $chunks,
			"message" => $message
		]);
	}

	public function onCompletion(Server $server){
		$result = $this->getResult();
		/** @var Chunk[] $chunks */
		$chunks = $result["chunks"];
		$selectionarray = unserialize($this->selection);
		$level = $server->getLevelByName($selectionarray["levelname"]);
		if ($level instanceof Level){
			foreach ($chunks as $hash => $chunk){
				$level->setChunk($chunk->getX(), $chunk->getZ(), $chunk);
			}
		}
		$player = $server->getPlayer(unserialize($this->sender));
		if (!is_null($player)){
			$server->broadcastMessage($result["message"], [$player]);
		}
	}
}