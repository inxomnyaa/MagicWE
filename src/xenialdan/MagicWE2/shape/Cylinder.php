<?php

namespace xenialdan\MagicWE2\shape;

use pocketmine\block\Block;
use pocketmine\level\ChunkManager;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\AsyncChunkManager;

class Cylinder extends Shape
{

    /**
     * Cylinder constructor.
     * @param Level $level
     * @param array $options
     */
    public function __construct(Level $level, array $options)
    {
        parent::__construct($level, $options);
    }

    public function getPos1()//TODO check if loop
    {
        try {
            return parent::getPos1();
        } catch (\Exception $e) {
            $this->setCenter($this->getCenter());
            return $this->pos1;
        }
    }

    public function getPos2()//TODO check if loop
    {
        try {
            return parent::getPos2();
        } catch (\Exception $e) {
            $this->setCenter($this->getCenter());
            return $this->pos2;
        }
    }

    /**
     * Returns the blocks by their actual position
     * @param Level|AsyncChunkManager|ChunkManager $manager The level or AsyncChunkManager
     * @param Block[] $filterblocks If not empty, applying a filter on the block list
     * @param int $flags
     * @return \Generator|Block
     * @throws \Exception
     */
    public function getBlocks(ChunkManager $manager, array $filterblocks = [], int $flags = API::FLAG_BASE): \Generator
    {
        $this->validateChunkManager($manager);
        $centerVec2 = new Vector2($this->getCenter()->getX(), $this->getCenter()->getZ());
        for ($x = intval(floor($this->getMinVec3()->x)), $rx = 0; $x <= floor($this->getMaxVec3()->x); $x++, $rx++) {
            for ($y = intval(floor($this->getMinVec3()->y)), $ry = 0; $y <= floor($this->getMaxVec3()->y); $y++, $ry++) {
                for ($z = intval(floor($this->getMinVec3()->z)), $rz = 0; $z <= floor($this->getMaxVec3()->z); $z++, $rz++) {
                    if (API::hasFlag($flags, API::FLAG_POSITION_RELATIVE)) {//TODO check if correct
                        $vec2 = new Vector2($rx, $rz);
                        $vec3 = new Vector3($rx, $ry, $rz);
                    } else {
                        $vec2 = new Vector2($x, $z);
                        $vec3 = new Vector3($x, $y, $z);
                    }
                    if ($vec2->distanceSquared($centerVec2) > (($this->options['diameter'] / 2) ** 2) || (API::hasFlag($flags, API::FLAG_HOLLOW_CLOSED) && ($ry !== 0 && $ry !== $this->getSizeY() - 1) && $vec2->distanceSquared($centerVec2) <= ((($this->options['diameter'] / 2) - 1) ** 2)) || ((API::hasFlag($flags, API::FLAG_HOLLOW) && $vec2->distanceSquared($centerVec2) <= ((($this->options['diameter'] / 2) - 1) ** 2))))
                        continue;
                    $block = $manager->getBlockAt($vec3->x, $vec3->y, $vec3->z);
                    if (API::hasFlag($flags, API::FLAG_KEEP_BLOCKS) && $block->getId() !== Block::AIR) continue;
                    if (API::hasFlag($flags, API::FLAG_KEEP_AIR) && $block->getId() === Block::AIR) continue;

                    $block->setComponents($vec3->x, $vec3->y, $vec3->z);

                    if ($block->y >= Level::Y_MAX || $block->y < 0) continue;
                    if (empty($filterblocks)) yield $block;
                    else {
                        foreach ($filterblocks as $filterblock) {
                            if (($block->getId() === $filterblock->getId()) && ((API::hasFlag($flags, API::FLAG_VARIANT) && $block->getVariant() === $filterblock->getVariant()) || (!API::hasFlag($flags, API::FLAG_VARIANT) && ($block->getDamage() === $filterblock->getDamage() || API::hasFlag($flags, API::FLAG_KEEP_META)))))
                                yield $block;
                        }
                    }
                }
            }
        }
    }

    /**
     * @param Vector3 $center
     * @throws \Exception
     */
    public function setCenter(Vector3 $center)
    {
        $this->center = $center;
        $this->setPos1(new Position($center->getX() - $this->options["diameter"] / 2, $center->getY() - $this->options["height"] / 2, $center->getZ() - $this->options["diameter"] / 2, $this->getLevel()));
        $this->setPos2(new Position($center->getX() + $this->options["diameter"] / 2, floor($center->getY() + $this->options["height"] / 2) - 1, $center->getZ() + $this->options["diameter"] / 2, $this->getLevel()));
    }

    public function getTotalCount()
    {
        return ceil((pi() * ($this->options["diameter"] ** 2)) / 4) * $this->options["height"];
    }
}