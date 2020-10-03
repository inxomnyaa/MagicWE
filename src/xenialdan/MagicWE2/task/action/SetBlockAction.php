<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\task\action;

use Exception;
use Generator;
use pocketmine\block\Block;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\helper\BlockEntry;
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
     * @param Block[] $newBlocks
     * @param Block[] $blockFilter
     * @param SingleClipboard $oldBlocksSingleClipboard blocks before the change
     * @param string[] $messages
     * @return Generator|Progress[]
     * @throws Exception
     */
    public function execute(string $sessionUUID, Selection $selection, AsyncChunkManager $manager, ?int &$changed, array $newBlocks, array $blockFilter, SingleClipboard $oldBlocksSingleClipboard, array &$messages = []): Generator
	{
		$changed = 0;
		$i = 0;
		#$oldBlocks = [];
		$count = $selection->getShape()->getTotalCount();
		$lastProgress = new Progress(0, "");
		foreach ($selection->getShape()->getBlocks($manager, $blockFilter) as $block) {
			$new = clone $newBlocks[array_rand($newBlocks)];
			if ($new->getId() === $block->getId() && $new->getMeta() === $block->getMeta()) continue;//skip same blocks
            #$oldBlocks[] = $manager->getBlockAt($block->getPos()->getFloorX(), $block->getPos()->getFloorY(), $block->getPos()->getFloorZ())->setComponents($block->x, $block->y, $block->z);
            $oldBlocksSingleClipboard->addEntry($block->getPos()->getFloorX(), $block->getPos()->getFloorY(), $block->getPos()->getFloorZ(), BlockEntry::fromBlock($block));
            $manager->setBlockAt($block->getPos()->getFloorX(), $block->getPos()->getFloorY(), $block->getPos()->getFloorZ(), $new);
            if ($manager->getBlockArrayAt($block->getPos()->getFloorX(), $block->getPos()->getFloorY(), $block->getPos()->getFloorZ()) !== [$block->getId(), $block->getMeta()]) {
                $changed++;
            }
            $i++;
            $progress = new Progress($i / $count, "Changed {$changed} blocks out of {$count}");
            if (floor($progress->progress * 100) > floor($lastProgress->progress * 100)) {
                yield $progress;
                $lastProgress = $progress;
            }
        }
    }
}