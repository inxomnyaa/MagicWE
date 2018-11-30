<?php

namespace xenialdan\MagicWE2\shape;

use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;

class Cylinder extends Shape
{
    private $diameter = 10;
    private $height = 1;
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
        $this->diameter = $options["diameter"] ?? $this->diameter;
        $this->height = $options["height"] ?? $this->height;
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
        $walked = $this->walk();
        if (false) {
            $circleBlocks = array_filter($walked, function (Block $block) {
                return $block->floor()->distanceSquared($this->getCenter()) == floor((($this->diameter / 2) ** 2));
            });
        } else {
            $circleBlocks = $walked;
        }
        if ($this->height > 1) {
            $blocks = $circleBlocks;
            for ($y = $this->getCenter()->getY(); $y < ($this->getCenter()->getY() + $this->height - 1) && $y < Level::Y_MAX; $y++) {
                $circleBlocks = array_merge($circleBlocks, array_map(function (Block $value) use ($y) {
                    return (clone $value)->setComponents($value->getX(), $y, $value->getZ());
                }, $blocks));
            }
        }
        return $circleBlocks;
    }

    private function walk()
    {
        /** @var Block[] $walkTo */
        $walkTo = [];
        foreach ($this->nextToCheck as $next) {
            $sides = $next->getHorizontalSides();
            $walkTo = array_merge($walkTo, array_filter($sides, function (Block $side) use ($walkTo) {
                return !in_array($side, $walkTo) && !in_array($side, $this->walked) && !in_array($side, $this->nextToCheck) && $side->distanceSquared($this->getCenter()) <= ($this->diameter / 2) ** 2;
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
        return ceil((pi() * ($this->diameter ** 2)) / 4) * $this->height;
    }
}