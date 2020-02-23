<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\task\action;

use Exception;
use Generator;
use InvalidArgumentException;
use pocketmine\block\BlockFactory;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\helper\BlockEntry;
use xenialdan\MagicWE2\helper\BlockStatesParser;
use xenialdan\MagicWE2\helper\Progress;
use xenialdan\MagicWE2\selection\Selection;

class RotateAction extends ClipboardAction
{
    const ROTATE_90 = 90;
    const ROTATE_180 = 180;
    const ROTATE_270 = 270;
    /** @var bool */
    public $addClipboard = true;
    /** @var string */
    public $completionString = '{%name} succeed, took {%took}, rotated {%changed} blocks out of {%total}';
    /** @var int */
    private $rotation;
    /** @var bool */
    public $aroundOrigin;

    public function __construct(int $rotation, bool $aroundOrigin = true)
    {
        if ($rotation !== self::ROTATE_90 && $rotation !== self::ROTATE_180 && $rotation !== self::ROTATE_270) throw new InvalidArgumentException("Invalid rotation $rotation given");
        $this->rotation = $rotation;
        $this->addClipboard = $aroundOrigin;
    }

    public static function getName(): string
    {
        return "Rotate";
    }

    /**
     * @param string $sessionUUID
     * @param Selection $selection
     * @param null|int $changed
     * @param SingleClipboard $clipboard
     * @param string[] $messages
     * @return Generator|Progress[]
     * @throws Exception
     */
    public function execute(string $sessionUUID, Selection $selection, ?int &$changed, SingleClipboard &$clipboard, array &$messages = []): Generator
    {
        //TODO modify position. For now, just flip the blocks around their own axis
        $changed = 0;
        #$oldBlocks = [];
        $count = $selection->getShape()->getTotalCount();
        $lastProgress = new Progress(0, "");
        if (!BlockFactory::isInit()) {
            BlockFactory::init();
        }
        if (!BlockStatesParser::isInit()) {
            var_dump("reinit BlockStatesParser AGAIN");
            BlockStatesParser::init();
        }
        $clonedClipboard = clone $clipboard;
        $clonedClipboard->clear();
        $x = $y = $z = null;
        $maxX = $clipboard->selection->getSizeX() - 1;
        //$maxY = $clipboard->selection->getSizeY();//TODO enable when upside down flip is implemented
        $maxZ = $clipboard->selection->getSizeZ() - 1;
        foreach ($clipboard->iterateEntries($x, $y, $z) as $blockEntry) {
            var_dump("$x $y $z");
            $newX = $x;
            $newZ = $z;
            if ($this->rotation === self::ROTATE_90) {
                $newX = -$z;
                $newZ = $x;
                if ($this->aroundOrigin) {
                    $newX += $maxZ;
                }
            }
            if ($this->rotation === self::ROTATE_180) {
                $newX = -$x;
                $newZ = -$z;
                if ($this->aroundOrigin) {
                    $newX += $maxX;
                    $newZ += $maxZ;
                }
            }
            if ($this->rotation === self::ROTATE_270) {
                $newX = $z;
                $newZ = -$x;
                if ($this->aroundOrigin) {
                    $newZ += $maxX;
                }
            }
            var_dump("$newX $y $newZ");
            $block1 = $blockEntry->toBlock();
            $blockStatesEntry = BlockStatesParser::getStateByBlock($block1);
            $rotated = $blockStatesEntry->rotate($this->rotation);
            $block = $rotated->toBlock();
            $entry = BlockEntry::fromBlock($block);
            var_dump($blockStatesEntry->__toString(), $rotated->__toString(), $entry);
            /** @var int $y */
            $clonedClipboard->addEntry($newX, $y, $newZ, $entry);
            $changed++;
            $progress = new Progress($changed / $count, "$changed/$count");
            if (floor($progress->progress * 100) > floor($lastProgress->progress * 100)) {
                yield $progress;
                $lastProgress = $progress;
            }
        }
        $clipboard = $clonedClipboard;
    }
}