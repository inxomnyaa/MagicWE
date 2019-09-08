<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\task\action;

use pocketmine\block\Block;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\helper\Progress;
use xenialdan\MagicWE2\selection\Selection;

class SetBlockAction extends TaskAction
{
    public $addRevert = false;

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
     * @param Block[] $oldBlocks blocks before the change
     * @param string[] $messages
     * @return \Generator|Progress
     * @throws \Exception
     */
    public function execute(string $sessionUUID, Selection $selection, AsyncChunkManager $manager, ?int &$changed, array $newBlocks, array $blockFilter, array &$oldBlocks = [], array &$messages = []): \Generator
    {
        $changed = 0;
        $i = 0;
        $oldBlocks = [];
        $count = $selection->getShape()->getTotalCount();
        $lastProgress = new Progress(0, "");
        foreach ($selection->getShape()->getBlocks($manager) as $block) {
            /** @var Block $new */
            if (count($newBlocks) === 1)
                $new = clone $newBlocks[0];
            else
                $new = clone $newBlocks[array_rand($newBlocks, 1)];
            if ($new->getId() === $block->getId() && $new->getDamage() === $block->getDamage()) continue;//skip same blocks
            $oldBlocks[] = $manager->getBlockAt($block->x, $block->y, $block->z)->setComponents($block->x, $block->y, $block->z);
            $manager->setBlockAt($block->x, $block->y, $block->z, $new);
            if ($manager->getBlockArrayAt($block->x, $block->y, $block->z) !== [$block->getId(), $block->getDamage()]) {
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