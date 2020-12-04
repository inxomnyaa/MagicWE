<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use BlockHorizons\libschematic\Schematic;
use InvalidArgumentException;
use pocketmine\utils\SingletonTrait;
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
	private $structures;
	/**
	 * @var Schematic[]
	 */
	private $schematics;

	public function __construct()
	{
		@mkdir(Loader::getInstance()->getDataFolder() . 'structures');
		@mkdir(Loader::getInstance()->getDataFolder() . 'schematics');
	}

	/**
	 * @param string $filename Filename without folder. Can have .mcstructure extension in the name
	 * @param bool $override Use this if you want to reload the file
	 * @return MCStructure
	 * @throws InvalidArgumentException
	 * @throws StructureFileException
	 * @throws StructureFormatException
	 */
	public function loadStructure(string $filename, bool $override = true): MCStructure
	{
		$id = pathinfo($filename, PATHINFO_FILENAME);
		if (!$override && array_key_exists($id, $this->structures)) throw new InvalidArgumentException("Can not override $id");
		$path = Loader::getInstance()->getDataFolder() . 'structures' . DIRECTORY_SEPARATOR . $id . '.mcstructure';
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
		$path = Loader::getInstance()->getDataFolder() . 'schematics' . DIRECTORY_SEPARATOR . $id . '.schematic';
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