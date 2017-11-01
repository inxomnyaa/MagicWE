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
use xenialdan\MagicWE2\Selection;
use xenialdan\MagicWE2\WEException;

class AsyncFillTask extends AsyncTask{

	private $sender;
	private $selection;
	private $chunks;
	private $blocks;
	private $flags;
	private $changed = 0;
	private $time;
	private $xyz;

	/**
	 * AsyncGetBlocksXYZTask constructor.
	 * @param CommandSender $sender
	 * @param Selection $selection
	 * @param array $chunks
	 * @param array $blocks
	 * @param $flags
	 */
	public function __construct($sender, $selection, $chunks, $blocks = [], $flags){
		var_dump(__LINE__);
		$this->time = microtime(TRUE);
		var_dump(__LINE__);
		$this->sender = serialize($sender->getName());
		var_dump(__LINE__);
		$this->xyz = serialize($selection->getBlocks());
		var_dump(__LINE__);
		$this->chunks = serialize($chunks);
		var_dump(__LINE__);
		$this->blocks = serialize($blocks);
		var_dump(__LINE__);
		$this->flags = $flags;
		var_dump(__LINE__);
	}

	public function onRun(){
		var_dump(__LINE__);
		$chunks = unserialize($this->chunks);//TODO add undo
		foreach ($chunks as $hash => $data){
			$chunks[$hash] = Chunk::fastDeserialize($data);
		}
		/** @var Chunk[] $chunks */
		#$selection = unserialize($this->selection);
		$xyz = unserialize($this->xyz);
		/** @var Block[] $blocks */
		$blocks = unserialize($this->blocks);
		try{
			foreach ($xyz as $x){
				foreach ($x as $y){
					foreach ($y as $block){
						if ($block->y >= Level::Y_MAX || $block->y < 0) continue;
						if (API::hasFlag($this->flags, API::FLAG_HOLLOW) && ($block->x > $selection->getMinVec3()->getX() && $block->x < $selection->getMaxVec3()->getX()) && ($block->y > $selection->getMinVec3()->getY() && $block->y < $selection->getMaxVec3()->getY()) && ($block->z > $selection->getMinVec3()->getZ() && $block->z < $selection->getMaxVec3()->getZ())) continue;
						$newblock = $blocks[array_rand($blocks, 1)];
						/** @var Chunk $chunk */
						$chunk = $chunks[Level::chunkHash($block->x >> 4, $block->z >> 4)];
						if (API::hasFlag($this->flags, API::FLAG_KEEP_BLOCKS)){
							if ($chunk->getBlockId($block->x & 0x0f, $block->y, $block->z & 0x0f) !== Block::AIR) continue;
						}
						if (API::hasFlag($this->flags, API::FLAG_KEEP_AIR)){
							if ($chunk->getBlockId($block->x & 0x0f, $block->y, $block->z & 0x0f) === Block::AIR) continue;
						}
						var_dump(__LINE__);
						if ($chunk->setBlock($block->x & 0x0f, $block->y, $block->z & 0x0f, $newblock->getId(), $newblock->getDamage())) $this->changed++;
					}
				}
			}
		} catch (WEException $exception){
			var_dump(__LINE__);
			$message = Loader::$prefix . TextFormat::RED . $exception->getMessage();
			$this->setResult([
				"chunks" => [],
				"message" => $message
			]);
			return;
		}
		var_dump(__LINE__);
		$message = Loader::$prefix . TextFormat::GREEN . "Fill succeed, took " . round((microtime(TRUE) - $this->time), 2) . "s, " . $this->changed . " blocks out of " . $selection->getTotalCount() . " changed.";

		$this->setResult([
			"chunks" => $chunks,
			"message" => $message
		]);
		var_dump(__LINE__);
	}

	public function onCompletion(Server $server){
		var_dump(__LINE__);
		$result = $this->getResult();
		/** @var Chunk[] $chunks */
		$chunks = $result["chunks"];
		$level = $server->getLevel(unserialize($this->selection)->getLevel());
		var_dump(__LINE__);
		if ($level instanceof Level){
			var_dump(__LINE__);
			foreach ($chunks as $hash => $chunk){
				$level->setChunk($chunk->getX(), $chunk->getZ(), $chunk);
			}
		}
		var_dump(__LINE__);
		Server::getInstance()->broadcastMessage($result["message"], [unserialize($this->sender)]);
	}
}