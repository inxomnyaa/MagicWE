<?php

namespace xenialdan\MagicWE2\shape;

use pocketmine\level\Level;
use pocketmine\Server;

class ShapeGenerator{
	const TYPE_SPHERE = 0;
	const TYPE_CONE = 1;
	const TYPE_SQUARE = 2;//TODO find the proper name
	const TYPE_CYLINDER = 3;

	public static function getShape(Level $level, int $shape = self::TYPE_SQUARE, $options):?Shape{
		switch ($shape){
			case self::TYPE_SQUARE: {
				return new Square($level, $options);
				break;
			}
			case self::TYPE_SPHERE: {
				return new Sphere($level, $options);
				break;
			}
			default:
				Server::getInstance()->broadcastMessage("Not implemented yet");
		}
		return null;
	}

}