<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use InvalidArgumentException;

class FlipHelper
{
    const AXIS_X = 0;
    const AXIS_Y = 1;
    const AXIS_Z = 2;

    private static $flippedMap = [

    ];

    private static function flipBlockState(BlockStatesEntry $entry, int $axis): BlockStatesEntry
    {
        $result = clone $entry;
        foreach ($result->blockStates as $blockState) {
            switch ($axis) {
                case self::AXIS_X:
                {

                    break;
                }
                case self::AXIS_Y:
                {
                    break;
                }
                case self::AXIS_Z:
                {
                    break;
                }
                default:
                    throw new InvalidArgumentException("Invalid axis $axis given");
            }
        }
        return $result;
    }
}