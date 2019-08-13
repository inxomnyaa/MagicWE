<?php

namespace xenialdan\MagicWE2\selection\shape;

use pocketmine\level\Level;
use pocketmine\Server;

/**
 * Class ShapeRegistry
 * @package xenialdan\MagicWE2\shape
 */
class ShapeRegistry
{
    const TYPE_SPHERE = 0;
    const TYPE_CONE = 1;
    const TYPE_CUBOID = 2;
    const TYPE_CYLINDER = 3;
    const TYPE_CUSTOM = 4;
    const TYPE_FLOOD = 5;
    const TYPE_PYRAMID = 6;
    const TYPE_CUBE = 7;

    /**
     * @param Level $level
     * @param int $shape
     * @param $options
     * @return null|Shape
     */
    public static function getShape(Level $level, int $shape, $options): ?Shape
    {
        switch ($shape) {
            case self::TYPE_SPHERE:
                {
                    return new Sphere($level, $options);
                    break;
                }
            case self::TYPE_CUBOID:
                {
                    return new Cuboid($level, $options);
                    break;
                }
            case self::TYPE_CUBE:
                {
                    return new Cube($level, $options);
                    break;
                }
            case self::TYPE_CYLINDER:
                {
                    return new Cylinder($level, $options);
                    break;
                }
            case self::TYPE_CUSTOM:
                {
                    return new Custom($level, $options);
                    break;
                }
            case self::TYPE_FLOOD:
                {
                    return new Flood($level, $options);
                    break;
                }
            default:
                Server::getInstance()->broadcastMessage("Not implemented yet");
        }
        return null;
    }

}