<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\session\data;

use Ds\Map;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat as TF;
use xenialdan\libstructure\exception\StructureFileException;
use xenialdan\MagicWE2\helper\StructureStore;
use xenialdan\MagicWE2\Loader;

final class AssetCollection
{
	use SingletonTrait;

	/** @var Map<Asset> */
	public Map $assets;

	public function __construct()
	{
		$this->assets = new Map();
		$this->initFolders();
	}

	/** @return Asset[] */
	public function getAssets(): array
	{
		return $this->assets->values()->toArray();
	}

	/** @return Asset[] */
	public function getUnlockedAssets(): array
	{
		return $this->assets->filter(function (string $key, Asset $value) {
			return !$value->locked;
		})->values()->toArray();
	}

	/** @return Asset[] */
	public function getSharedAssets(): array
	{
		return $this->assets->filter(function (string $key, Asset $value) {
			return $value->shared;
		})->values()->toArray();
	}

	/**
	 * @param string|null $xuid If null, returns all player assets, if string, returns a player's assets
	 * @return Asset[]
	 */
	public function getPlayerAssets(?string $xuid = null): array
	{
		return $this->assets->filter(function (string $key, Asset $value) use ($xuid) {
			if ($xuid === null) return $value->ownerXuid !== null;
			else return $value->ownerXuid === $xuid;
		})->values()->toArray();
	}

	private function initFolders(): void
	{
		//Load mcstructure and schematic files and lock them to prevent editing
		$store = $this;
		$schematicFiles = array_merge(glob(Loader::getInstance()->getDataFolder() . 'assets' . DIRECTORY_SEPARATOR . "*.mcstructure"), glob(Loader::getInstance()->getDataFolder() . 'assets' . DIRECTORY_SEPARATOR . "*.schematic"));//glob might return false
		if ($schematicFiles !== false)
			foreach ($schematicFiles as $file) {
				['basename' => $basename, 'extension' => $extension] = pathinfo($file);
				Loader::getInstance()->getLogger()->debug(TF::GOLD . "Loading " . $basename);
				try {
					if ($extension === 'mcstructure') {
						$store->assets->put($basename, new Asset($basename, StructureStore::getInstance()->loadStructure($basename), true, null, true));
					} else if ($extension === 'schematic') {
						$store->assets->put($basename, new Asset($basename, StructureStore::getInstance()->loadSchematic($basename), true, null, true));
					}
				} catch (StructureFileException $e) {
					Loader::getInstance()->getLogger()->debug($e->getMessage());
				}
			}
	}
}