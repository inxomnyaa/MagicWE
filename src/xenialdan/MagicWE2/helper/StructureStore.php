<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use BlockHorizons\libschematic\Schematic;
use InvalidArgumentException;
use OutOfRangeException;
use pocketmine\nbt\NbtDataException;
use pocketmine\nbt\UnexpectedTagTypeException;
use pocketmine\utils\SingletonTrait;
use UnexpectedValueException;
use xenialdan\libstructure\exception\StructureFileException;
use xenialdan\libstructure\exception\StructureFormatException;
use xenialdan\libstructure\format\MCStructure;
use xenialdan\MagicWE2\Loader;

final class StructureStore
{
	use SingletonTrait;

	/**
	 * @var MCStructure[]
	 */
	private array $structures;
	/**
	 * @var Schematic[]
	 */
	private array $schematics;

	/** @noinspection MkdirRaceConditionInspection */
	public function __construct()
	{
		@mkdir(Loader::getInstance()->getDataFolder() . 'assets');
	}

	/**
	 * @param string $filename Filename without folder. Can have .mcstructure extension in the name
	 * @param bool   $override Use this if you want to reload the file
	 *
	 * @return MCStructure
	 * @throws InvalidArgumentException
	 * @throws NbtDataException
	 * @throws StructureFileException
	 * @throws StructureFormatException
	 * @throws UnexpectedTagTypeException
	 * @throws UnexpectedValueException
	 * @throws OutOfRangeException
	 */
	public function loadStructure(string $filename, bool $override = true): MCStructure
	{
		$id = pathinfo($filename, PATHINFO_FILENAME);
		if (!$override && array_key_exists($id, $this->structures)) throw new InvalidArgumentException("Can not override $id");
		$path = Loader::getInstance()->getDataFolder() . 'assets' . DIRECTORY_SEPARATOR . $id . '.mcstructure';
		$structure = new MCStructure();
		$structure->parse($path);
		$this->structures[$id] = $structure;
		return $this->structures[$id];
	}

	/**
	 * @param string $id
	 * @return MCStructure
	 * @throws InvalidArgumentException
	 */
	public function getStructure(string $id): MCStructure
	{
		$structure = $this->structures[$id] ?? null;
		if ($structure === null) {
			throw new InvalidArgumentException("Structure $id is not loaded");
		}
		return $structure;
	}

	/**
	 * @param string $filename Filename without folder. Can have .schematic extension in the name
	 * @param bool $override Use this if you want to reload the file
	 * @return Schematic
	 * @throws InvalidArgumentException
	 */
	public function loadSchematic(string $filename, bool $override = true): Schematic
	{
		$id = pathinfo($filename, PATHINFO_FILENAME);
		if (!$override && array_key_exists($id, $this->schematics)) throw new InvalidArgumentException("Can not override $id");
		$path = Loader::getInstance()->getDataFolder() . 'assets' . DIRECTORY_SEPARATOR . $id . '.schematic';
		$schematic = new Schematic();
		$schematic->parse($path);
		$this->schematics[$id] = $schematic;
		return $this->schematics[$id];
	}

	/**
	 * @param string $id
	 * @return Schematic
	 * @throws InvalidArgumentException
	 */
	public function getSchematic(string $id): Schematic
	{
		$schematic = $this->schematics[$id] ?? null;
		if ($schematic === null) {
			throw new InvalidArgumentException("Structure $id is not loaded");
		}
		return $schematic;
	}

}