<?php

namespace xenialdan\MagicWE2\shape;

use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use xenialdan\MagicWE2\Selection;

abstract class Shape extends Selection
{
    public $options = [];
    public $center = null;

    public function __construct(Level $level, array $options)
    {
        $this->options = $options;
        parent::__construct($level);
    }

    public function getCenter()
    {
        return $this->center ?? new Vector3
            (
                round(($this->getMinVec3()->x + $this->getMaxVec3()->x) / 2),
                round(($this->getMinVec3()->y + $this->getMaxVec3()->y) / 2),
                round(($this->getMinVec3()->z + $this->getMaxVec3()->z) / 2)
            );
    }

    public function setCenter(Vector3 $center)
    {
        $this->center = $center;
        try {
            $this->setPos1(new Position($center->getX(), $center->getY(), $center->getZ(), $this->getLevel()));
            $this->setPos2(new Position($center->getX(), $center->getY(), $center->getZ(), $this->getLevel()));
        } catch (\Exception $e) {
        }
    }

    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize()
    {
        return serialize([
            $this->levelid,
            $this->pos1,
            $this->pos2,
            $this->uuid,
            $this->options,
            $this->center
        ]);
    }

    /**
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     * @return void
     * @since 5.1.0
     */
    public function unserialize($serialized)
    {
        /** @var Vector3 $pos1 , $pos2 */
        [
            $this->levelid,
            $this->pos1,
            $this->pos2,
            $this->uuid,
            $this->options,
            $this->center
        ] = unserialize($serialized);
    }
}