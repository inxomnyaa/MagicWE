<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\task\action;

use Generator;
use InvalidArgumentException;
use pocketmine\block\Block;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\helper\BlockEntry;
use xenialdan\MagicWE2\helper\BlockPalette;
use xenialdan\MagicWE2\helper\Progress;
use xenialdan\MagicWE2\selection\Selection;

class SetBlockAction extends TaskAction
{

	public function __construct()
	{
	}

	public static function getName(): string
	{
		return "Set block";
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
		$lastProgress = new Progress(0, "");
		foreach ($selection->getShape()->getBlocks($manager, $blockFilter) as $block) {//TODO merge iterator
			/** @var Block $new */
			$new = $newBlocks->blocks()->current();//TODO merge iterator
			if ($new->getId() === $block->getId() && $new->getMeta() === $block->getMeta()) continue;//skip same blocks
			#$oldBlocks[] = API::setComponents($manager->getBlockAt($block->getPosition()->getFloorX(), $block->getPosition()->getFloorY(), $block->getPosition()->getFloorZ()),$block->x, $block->y, $block->z);
			$oldBlocksSingleClipboard->addEntry($block->getPosition()->getFloorX(), $block->getPosition()->getFloorY(), $block->getPosition()->getFloorZ(), BlockEntry::fromBlock($block));
			$manager->setBlockAt($block->getPosition()->getFloorX(), $block->getPosition()->getFloorY(), $block->getPosition()->getFloorZ(), $new);
			/** @noinspection PhpInternalEntityUsedInspection */
			if ($manager->getBlockFullIdAt($block->getPosition()->getFloorX(), $block->getPosition()->getFloorY(), $block->getPosition()->getFloorZ()) !== $block->getFullId()) {
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