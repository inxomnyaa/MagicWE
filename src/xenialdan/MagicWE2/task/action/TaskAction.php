<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\task\action;

use Generator;
use pocketmine\block\Block;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\helper\Progress;
use xenialdan\MagicWE2\selection\Selection;

abstract class TaskAction
{

    public $prefix = "";
    public $addRevert = true;
    public $completionString = '{%name} succeed, took {%took}, {%changed} blocks out of {%total} changed.';

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
     */
    public abstract function execute(string $sessionUUID, Selection $selection, AsyncChunkManager $manager, ?int &$changed, array $newBlocks, array $blockFilter, array &$oldBlocks = [], array &$messages = []): Generator;

    public static abstract function getName(): string;
}