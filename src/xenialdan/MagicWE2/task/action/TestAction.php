<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\task\action;

use pocketmine\block\Block;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\selection\Selection;

class TestAction extends TaskAction
{
    public $addRevert = false;

    /**
     * @param string $sessionUUID
     * @param Selection $selection
     * @param AsyncChunkManager $manager
     * @param null|int $changed
     * @param Block[] $newBlocks
     * @param Block[] $blockFilter
     * @return \Generator|Block[] blocks before the change
     * @throws \Exception
     */
    public function execute(string $sessionUUID, Selection $selection, AsyncChunkManager $manager, ?int &$changed, array $newBlocks, array $blockFilter): \Generator
    {
        $changed = 0;
        foreach ($selection->getShape()->getBlocks($manager, []) as $block) {
            $changed++;
            yield $block;
        }
    }

    public function getName(): string
    {
        return "TEsT aCTiOn";
    }
}