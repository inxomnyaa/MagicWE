<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\tool;

use pocketmine\level\biome\Biome;
use xenialdan\MagicWE2\selection\shape\Sphere;

class Brush extends WETool
{
    /** @var BrushProperties */
    public $properties;

    /**
     * Brush constructor.
     * @param BrushProperties $properties
     */
    public function __construct(BrushProperties $properties)
    {
        $this->properties = $properties;
    }

}

class BrushProperties implements \JsonSerializable
{

    /** @var null|string */
    public $customName;
    /** @var string */
    public $action = FillType::class;
    /** @var string */
    public $shape = Sphere::class;
    /** @var bool */
    public $hollow = false;
    /** @var string */
    public $blocks = "stone";
    /** @var string */
    public $filter = "air";
    /** @var int */
    public $biomeId = Biome::PLAINS;

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return (array)$this;
    }

    public function getName(): string
    {
        return $this->customName ?? $this->shape->getName() . " " . $this->action->getName() . " Brush";
    }

    /**
     * @param null|string $customName If null, the name will be reset
     */
    public function setCustomName(?string $customName = null): void
    {
        $this->customName = $customName;
    }
}