<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\task\action;

use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\helper\Progress;
use xenialdan\MagicWE2\selection\Selection;

class CountAction extends TaskAction
{
    public $addRevert = false;
    public $completionString = '{%name} succeed, took {%took}, analyzed {%count} blocks';

    public function __construct()
    {
    }

    public static function getName(): string
    {
        return "Analyze";
    }

    /**
     * @param string $sessionUUID
     * @param Selection $selection
     * @param AsyncChunkManager $manager
     * @param null|int $changed
     * @param Block[] $newBlocks
     * @param Block[] $blockFilter
     * @param Block[] $oldBlocks blocks before the change
     * @return \Generator|Progress[]
     * @throws \Exception
     */
    public function execute(string $sessionUUID, Selection $selection, AsyncChunkManager $manager, ?int &$changed, array $newBlocks, array $blockFilter, array &$oldBlocks = []): \Generator
    {
        $changed = 0;
        $oldBlocks = [];
        $count = $selection->getShape()->getTotalCount();
        $lastProgress = new Progress(0, "");
        $counts = [];
        foreach ($selection->getShape()->getBlocks($manager, []) as $block) {
            if (!BlockFactory::isInit()) BlockFactory::init();
            $block1 = $manager->getBlockArrayAt($block->x, $block->y, $block->z);
            $this->completionMessages[] = $block->asVector3()->__toString();
            $tostring = (BlockFactory::get($block1[0], $block1[1]))->getName() . " " . $block1[0] . ":" . $block1[1];
            if (!array_key_exists($tostring, $counts)) $counts[$tostring] = 0;
            $counts[$tostring]++;
            $changed++;
            $progress = new Progress($changed / $count, "$changed blocks out of $count");
            if (floor($progress->progress * 100) > floor($lastProgress->progress * 100)) {
                yield $progress;
                $lastProgress = $progress;
            }
        }
        $this->completionMessages[] = TF::DARK_AQUA . count($counts) . " blocks found in a total of $count blocks";
        uasort($counts, function ($a, $b) {
            if ($a === $b) return 0;
            return ($a > $b) ? -1 : 1;
        });
        foreach ($counts as $block => $countb) {
            $this->completionMessages[] = TF::AQUA . $countb . "x | " . round($countb / $count * 100) . "% | " . $block;
        }
    }
}