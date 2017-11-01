<?php

namespace xenialdan\MagicWE2\task;

use pocketmine\block\Block;
use pocketmine\level\Position;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use xenialdan\MagicWE2\Selection;

class AsyncGetBlocksXYZTask extends AsyncTask{

	/** @var Selection */
	private $selection;
	/** @var Block[] */
	private $filterblocks;
	private $blocks;

	/**
	 * AsyncGetBlocksXYZTask constructor.
	 * @param Selection $selection
	 * @param Block[] $filterblocks
	 */
	public function __construct(Selection $selection, $filterblocks){
		$this->selection = $selection;
		$this->filterblocks = $filterblocks;
	}

	public function onRun(){
		for ($x = floor($this->selection->getAxisAlignedBB()->minX); $x <= floor($this->selection->getAxisAlignedBB()->maxX); $x++){
			for ($y = floor($this->selection->getAxisAlignedBB()->minY); $y <= floor($this->selection->getAxisAlignedBB()->maxY); $y++){
				for ($z = floor($this->selection->getAxisAlignedBB()->minZ); $z <= floor($this->selection->getAxisAlignedBB()->maxZ); $z++){
					$block = $this->selection->getLevel()->getBlock(new Position($x, $y, $z, $this->selection->getLevel()));
					if (empty($this->filterblocks)) $this->blocks[(int)$x][(int)$y][(int)$z] = $block;
					else{
						foreach ($this->filterblocks as $filterblock){
							if ($block->getId() === $filterblock->getId() && $block->getDamage() === $filterblock->getDamage())
								$this->blocks[(int)$x][(int)$y][(int)$z] = $block;
						}
					}
				}
			}
		}
	}

	public function onCompletion(Server $server){
		$this->setResult($this->blocks);
	}
}