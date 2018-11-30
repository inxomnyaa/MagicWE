<?php

namespace xenialdan\MagicWE2\shape;

use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;

class Flood extends Shape
{
    private $limit = 10000;
    /** @var Block[] */
    private $walked = [];
    /** @var Block[] */
    private $nextToCheck = [];
    /** @var int */
    private $y;
    private $foundBlocks = [];

    /**
     * Square constructor.
     * @param Level $level
     * @param array $options
     */
    public function __construct(Level $level, array $options)
    {
        parent::__construct($level, $options);
        $this->limit = $options["limit"] ?? $this->limit;
    }

    /**
     * @param int $flags
     * @param Block ...$filterblocks
     * @return array
     * @throws \Exception
     */
    public function getBlocks(int $flags, Block ...$filterblocks)
    {//TODO use filterblocks
        $this->y = $this->getCenter()->getY();
        $this->walked[] = $this->getLevel()->getBlock($this->getCenter());
        $this->foundBlocks[] = $this->getLevel()->getBlock($this->getCenter());
        $this->nextToCheck[] = $this->getLevel()->getBlock($this->getCenter());
        return $this->walk();
    }

    private function walk()
    {
        /** @var Block[] $walkTo */
        $walkTo = [];
        foreach ($this->nextToCheck as $next) {
            $sides = $next->getHorizontalSides();
            $walkTo = array_merge($walkTo, array_filter($sides, function (Block $side) use ($walkTo) {
                return $side->getId() === 0 && !in_array($side, $walkTo) && !in_array($side, $this->walked) && !in_array($side, $this->nextToCheck) && $side->distanceSquared($this->getCenter()) <= ($this->limit / pi());
            }));
        }
        $this->walked = array_merge($this->walked, $walkTo);
        $this->nextToCheck = $walkTo;
        if (!empty($this->nextToCheck)) $this->walk();
        return $this->walked;
    }

    public function setCenter(Vector3 $center)
    {
        $this->center = $center;
        try {
            $this->setPos1(new Position($center->getX(), $center->getY(), $center->getZ(), $this->getLevel()));
        } catch (\Exception $e) {
        }
        try {
            $this->setPos2(new Position($center->getX(), $center->getY(), $center->getZ(), $this->getLevel()));
        } catch (\Exception $e) {
        }
    }

    public function getTotalCount()
    {
        return $this->limit;
    }
}