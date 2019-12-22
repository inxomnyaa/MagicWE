<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\task\action;

use Exception;
use Generator;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\helper\Progress;
use xenialdan\MagicWE2\selection\Selection;

class TestAction extends TaskAction
{
    public $addRevert = false;
    public $completionString = '{%name} succeed, took {%took}, tested {%changed} blocks';

    public function __construct()
    {
    }

    public static function getName(): string
    {
        return "Test";
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
        $oldBlocks = [];
        $count = $selection->getShape()->getTotalCount();
        $lastProgress = new Progress(0, "");
        if (!BlockFactory::isInit()) BlockFactory::init();
        foreach ($selection->getShape()->getBlocks($manager, []) as $block) {
            $changed++;
            $messages[] = $block->asVector3()->__toString() . " " . $block->getName();
            $progress = new Progress($changed / $count, "$changed/$count");
            if (floor($progress->progress * 100) > floor($lastProgress->progress * 100)) {
                yield $progress;
                $lastProgress = $progress;
            }
        }
    }
}