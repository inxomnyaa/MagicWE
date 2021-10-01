<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\task\action;

use Generator;
use InvalidArgumentException;
use pocketmine\block\VanillaBlocks;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\helper\BlockEntry;
use xenialdan\MagicWE2\helper\BlockPalette;
use xenialdan\MagicWE2\helper\Progress;
use xenialdan\MagicWE2\selection\Selection;

class ThawAction extends TaskAction
{

	public function __construct()
	{
	}

	public static function getName(): string
	{
		return "Thaw";
	}

	/**
	 * @param string $sessionUUID
	 * @param Selection $selection
	 * @param AsyncChunkManager $manager
	 * @param null|int $changed
	 * @param BlockPalette $newBlocks
	 * @param BlockPalette $blockFilter
	 * @param SingleClipboard $oldBlocksSingleClipboard blocks before the change
	 * @param string[] $messages
	 * @return Generator
	 * @throws InvalidArgumentException
	 */
	public function execute(string $sessionUUID, Selection $selection, AsyncChunkManager $manager, ?int &$changed, BlockPalette $newBlocks, BlockPalette $blockFilter, SingleClipboard $oldBlocksSingleClipboard, array &$messages = []): Generator
	{
		$changed = 0;
		$i = 0;
		#$oldBlocks = [];
		$count = $selection->getShape()->getTotalCount();
		$lastProgress = new Progress(0, "Thaw action is still under TODO");

		$blockFilterA = [VanillaBlocks::SNOW_LAYER(), VanillaBlocks::SNOW(), VanillaBlocks::ICE()];
		$newBlocksA = [VanillaBlocks::AIR(), VanillaBlocks::AIR(), VanillaBlocks::WATER()];
		foreach ($blockFilterA as $ib => $blockF) {
			foreach ($selection->getShape()->getBlocks($manager, BlockPalette::CREATE()) as $block) {//TODO merged generator iterating blocks and newblocks
				$new = clone $newBlocksA[$ib];
				#$oldBlocks[] = API::setComponents($manager->getBlockAt($block->getPosition()->getFloorX(), $block->getPosition()->getFloorY(), $block->getPosition()->getFloorZ()),$block->x, $block->y, $block->z);
				$oldBlocksSingleClipboard->addEntry($block->getPosition()->getFloorX(), $block->getPosition()->getFloorY(), $block->getPosition()->getFloorZ(), BlockEntry::fromBlock($block));
				$manager->setBlockAt($block->getPosition()->getFloorX(), $block->getPosition()->getFloorY(), $block->getPosition()->getFloorZ(), $new);
				if ($manager->getBlockAt($block->getPosition()->getFloorX(), $block->getPosition()->getFloorY(), $block->getPosition()->getFloorZ())->getId() !== $block->getId()) {
					$changed++;
				}
				$i++;
				$progress = new Progress($i / $count, "Changed $changed blocks out of $count");
				if (floor($progress->progress * 100) > floor($lastProgress->progress * 100)) {
					yield $progress;
					$lastProgress = $progress;
				}
			}
		}
	}
}