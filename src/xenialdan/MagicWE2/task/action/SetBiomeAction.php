<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\task\action;

use pocketmine\block\Block;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\selection\Selection;

class SetBiomeAction extends TaskAction
{
    public $addRevert = false;
    private $biomeId;

    public function __construct(int $biomeId)
    {
        $this->biomeId = $biomeId;
    }

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
        foreach ($selection->getShape()->getLayer($manager) as $vec2) {
            $manager->getChunk($vec2->x >> 4, $vec2->y >> 4)->setBiomeId($vec2->x % 16, $vec2->y % 16, $this->biomeId);
        }
        yield;
    }

    public function getName(): string
    {
        return "Set biome";
    }
}