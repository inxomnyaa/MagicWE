<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\tool;

use pocketmine\level\biome\Biome;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\selection\shape\Shape;
use xenialdan\MagicWE2\selection\shape\ShapeRegistry;
use xenialdan\MagicWE2\selection\shape\Sphere;
use xenialdan\MagicWE2\task\action\ActionRegistry;
use xenialdan\MagicWE2\task\action\SetBlockAction;
use xenialdan\MagicWE2\task\action\TaskAction;

class BrushProperties implements \JsonSerializable
{

    public const VERSION = 1;
    /** @var int */
    public $version = self::VERSION;
    /** @var string */
    public $customName = "";
    /** @var string */
    public $shape = Sphere::class;
    /** @var array */
    public $shapeProperties = [];
    /** @var string */
    public $action = SetBlockAction::class;
    /** @var array */
    public $actionProperties = [];
    /** @var bool */
    public $hollow = false;//TODO consider moving into shape properties
    /** @var string */
    public $blocks = "stone";
    /** @var string */
    public $filter = "";
    /** @var int */
    public $biomeId = Biome::PLAINS;
    /** @var string */
    public $uuid;

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
        $str = trim(($this->hasCustomName() ? $this->customName : $this->getShapeName()) /*. " " . $this->action->getName() . */);
        if (stripos(TF::clean($str), "brush") === false) {
            $str .= " Brush";
        }
        return $str;
    }

    public function getShapeName(): string
    {
        return is_subclass_of($this->shape, Shape::class) ? ShapeRegistry::getShapeName($this->shape) : "";
    }

    public function getActionName(): string
    {
        return is_subclass_of($this->action, TaskAction::class) ? ActionRegistry::getActionName($this->action) : "";
    }

    public function hasCustomName(): bool
    {
        return !empty($this->customName);
    }

    /**
     * @param null|string $customName If null, the name will be reset
     */
    public function setCustomName(string $customName = ""): void
    {
        $this->customName = $customName;
    }

    public function generateLore(): array
    {
        $shapeProperties = array_map(function ($k, $v): string {
            return TF::GOLD . "  " . ucfirst($k) . " = " . (is_bool($v) ? ($v ? "Yes" : "No") : $v);
        }, array_keys($this->shapeProperties), $this->shapeProperties);
        $actionProperties = array_map(function ($k, $v): string {
            return TF::GOLD . "  " . ucfirst($k) . " = " . (is_bool($v) ? ($v ? "Yes" : "No") : $v);
        }, array_keys($this->actionProperties), $this->actionProperties);
        return array_merge(
            [
                TF::GOLD . "Shape: {$this->getShapeName()}",
            ],
            $shapeProperties,
            [
                TF::GOLD . "Action: {$this->getActionName()}",
            ],
            $actionProperties,
            [
                TF::GOLD . "Blocks: {$this->blocks}",
                TF::GOLD . "Filter: {$this->filter}",
                TF::GOLD . "Biome: {$this->biomeId}",
                TF::GOLD . "Hollow: " . ($this->hollow ? "Yes" : "No"),
                TF::GOLD . "UUID: {$this->uuid}",
            ]
        );
    }
}