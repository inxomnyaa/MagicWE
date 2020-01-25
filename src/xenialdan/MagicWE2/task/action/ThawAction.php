<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\task\action;

use Exception;
use Generator;
use pocketmine\block\Block;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\helper\Progress;
use xenialdan\MagicWE2\selection\Selection;

class ThawAction extends SetBlockAction
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
     * @param Block[] $newBlocks
     * @param Block[] $blockFilter
     * @param Block[] $oldBlocks blocks before the change
     * @param string[] $messages
     * @return Generator|Progress[]
     * @throws Exception
     */
    public function execute(string $sessionUUID, Selection $selection, AsyncChunkManager $manager, ?int &$changed, array $newBlocks, array $blockFilter, array &$oldBlocks = [], array &$messages = []): Generator
    {
        $changed = 0;
        $i = 0;
        $oldBlocks = [];
        $count = $selection->getShape()->getTotalCount();
        $lastProgress = new Progress(0, "");

        $m = [];
        $e = false;
        $blockFilter = API::blockParser("snow_block,snow_layer,ice", $m, $e);
        $newBlocks = API::blockParser("air,air,water", $m, $e);
        foreach ($blockFilter as $ib => $blockF) {
            foreach ($selection->getShape()->getBlocks($manager, [$blockF]) as $block) {
                /** @var Block $new */
                $new = clone $newBlocks[$ib];
                $oldBlocks[] = $manager->getBlockAt($block->x, $block->y, $block->z)->setComponents($block->x, $block->y, $block->z);
                $manager->setBlockAt($block->x, $block->y, $block->z, $new);
                if ($manager->getBlockIdAt($block->x, $block->y, $block->z) !== $block->getId()) {
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
}