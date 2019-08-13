<?php

namespace xenialdan\MagicWE2\selection\shape;

use pocketmine\level\Level;
use pocketmine\math\Vector3;

class Custom extends Shape
{

    /**
     * Square constructor.
     * @param Level $level
     * @param array $options
     */
    public function __construct(Level $level, array $options)
    {
        parent::__construct($level, $options);
    }

    public function setCenter(Vector3 $center)
    {
        $this->center = $center;
    }
}