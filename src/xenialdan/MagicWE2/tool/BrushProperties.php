<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\tool;

use InvalidArgumentException;
use JsonSerializable;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\biome\Biome;
use xenialdan\MagicWE2\exception\ActionNotFoundException;
use xenialdan\MagicWE2\exception\ShapeNotFoundException;
use xenialdan\MagicWE2\selection\shape\Shape;
use xenialdan\MagicWE2\selection\shape\ShapeRegistry;
use xenialdan\MagicWE2\selection\shape\Sphere;
use xenialdan\MagicWE2\task\action\ActionRegistry;
use xenialdan\MagicWE2\task\action\SetBlockAction;
use xenialdan\MagicWE2\task\action\TaskAction;

class BrushProperties implements JsonSerializable
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

    /**
     * @param array $json
     * @return BrushProperties
     * @throws InvalidArgumentException
     */
    public static function fromJson(array $json): BrushProperties
    {
        if (($json["version"] ?? 0) !== self::VERSION) throw new InvalidArgumentException("Version mismatch");
        $properties = new self;
        foreach ($json as $key => $value) {
            $properties->$key = $value;
        }
        return $properties;
    }

    public function getName(): string
    {
        $str = "";
        try {
            $str = trim(($this->hasCustomName() ? $this->customName : $this->getShapeName()) /*. " " . $this->action->getName() . */);
        } catch (ShapeNotFoundException $e) {
        }
        if (stripos(TF::clean($str), "brush") === false) {
            $str .= " Brush";
        }
        return $str;
    }

    /**
     * @return string
     * @throws ShapeNotFoundException
     */
    public function getShapeName(): string
    {
        return is_subclass_of($this->shape, Shape::class) ? ShapeRegistry::getShapeName($this->shape) : "";
    }

    /**
     * @return string
     * @throws ActionNotFoundException
     */
    public function getActionName(): string
    {
        return is_subclass_of($this->action, TaskAction::class) ? ActionRegistry::getActionName($this->action) : "";
    }

    public function hasCustomName(): bool
    {
        return !empty($this->customName);
    }

    /**
     * @param string $customName If empty, the name will be reset
     */
    public function setCustomName(string $customName = ""): void
    {
        $this->customName = $customName;
    }

    /**
	 * @return array
	 * @throws ActionNotFoundException
	 * @throws ShapeNotFoundException
	 * @noinspection NestedTernaryOperatorInspection
	 */
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
                //TF::GOLD . "UUID: {$this->uuid}",
            ]
        );
    }
}