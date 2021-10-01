<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\tool;

use InvalidArgumentException;
use JsonSerializable;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\utils\TextFormat as TF;
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
	public int $version = self::VERSION;
	/** @var string */
	public string $customName = "";
	/** @var string */
	public string $shape = Sphere::class;
	/** @var array */
	public array $shapeProperties = [];
	/** @var string */
	public string $action = SetBlockAction::class;
	/** @var array */
	public array $actionProperties = [];
	/** @var bool */
	public bool $hollow = false;//TODO consider moving into shape properties
	/** @var string */
	public string $blocks = "stone";
	/** @var string */
	public string $filter = "";
	/** @var int */
	public int $biomeId = BiomeIds::PLAINS;
	/** @var string */
	public string $uuid;

	/**
	 * Specify data which should be serialized to JSON
	 * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
	 * @return array data which can be serialized by <b>json_encode</b>,
	 * which is a value of any type other than a resource.
	 * @since 5.4.0
	 */
	public function jsonSerialize(): array
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
		$shapeProperties = array_map(static function ($k, $v): string {
			return TF::RESET . TF::AQUA . "  " . ucfirst($k) . ": " . TF::RESET . (is_bool($v) ? ($v ? TF::GREEN . "Yes" : TF::RED . "No") : $v);
		}, array_keys($this->shapeProperties), $this->shapeProperties);
		$actionProperties = array_map(static function ($k, $v): string {
			return TF::RESET . TF::AQUA . "  " . ucfirst($k) . ": " . TF::RESET . (is_bool($v) ? ($v ? TF::GREEN . "Yes" : TF::RED . "No") : $v);
		}, array_keys($this->actionProperties), $this->actionProperties);
		return array_merge(
			[
				TF::RESET . TF::BOLD . TF::GOLD . "Shape: " . TF::RESET . $this->getShapeName(),
			],
			$shapeProperties,
			[
				TF::RESET . TF::BOLD . TF::GOLD . "Action: " . TF::RESET . $this->getActionName(),
			],
			$actionProperties,
			[
				TF::RESET . TF::BOLD . TF::GOLD . "Blocks: " . TF::RESET . $this->blocks,
				TF::RESET . TF::BOLD . TF::GOLD . "Filter: " . TF::RESET . $this->filter,
				TF::RESET . TF::BOLD . TF::GOLD . "Biome: " . TF::RESET . $this->biomeId,
				TF::RESET . TF::BOLD . TF::GOLD . "Hollow: " . TF::RESET . ($this->hollow ? TF::GREEN . "Yes" : TF::RED . "No"),
				//TF::GOLD . "UuidInterface: {$this->uuid}",
			]
		);
	}
}