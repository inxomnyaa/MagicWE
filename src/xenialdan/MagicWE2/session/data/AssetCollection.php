<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\session\data;

use pocketmine\utils\TextFormat as TF;
use xenialdan\libstructure\exception\StructureFileException;
use xenialdan\MagicWE2\helper\StructureStore;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\session\Session;
use function array_filter;

final class AssetCollection
{
	/** @var array<string, Asset> */
	public array $assets = [];
	private Session $session;

	public function __construct(Session $session)
	{
		$this->session = $session;
		$this->initFolders();
	}

	/**
	 * @return Session
	 */
	public function getSession(): Session
	{
		return $this->session;
	}

	/** @return Asset[] */
	public function getAll(): array
	{
		return $this->assets;
	}

	/** @return Asset[] */
	public function getUnlockedAssets(): array
	{
		return array_filter($this->assets, function (Asset $value) {
			return !$value->locked;
		});
	}

	/** @return Asset[] */
	public function getSharedAssets(): array
	{
		//TODO remove
		return array_filter($this->assets, function (Asset $value) {
			return $value->shared;
		});
	}

	/**
	 * @param string|null $xuid If null, returns all player assets, if string, returns a player's assets
	 * @return Asset[]
	 */
	public function getPlayerAssets(?string $xuid = null): array
	{
		//TODO remove
		return array_filter($this->assets, function (string $key, Asset $value) use ($xuid) {
			if ($xuid === null) return $value->ownerXuid !== null;
			else return $value->ownerXuid === $xuid;
		});
	}

	private function initFolders(): void
	{
		//Load mcstructure and schematic files and lock them to prevent editing
		$store = $this;
		$globStructure = glob(Loader::getInstance()->getDataFolder() . 'assets' . DIRECTORY_SEPARATOR . "*.mcstructure");
		$globSchematic =  glob(Loader::getInstance()->getDataFolder() . 'assets' . DIRECTORY_SEPARATOR . "*.schematic");
		if($globStructure && $globSchematic) {
			$schematicFiles = array_merge($globStructure, $globSchematic);
			if ($schematicFiles !== false)
				foreach ($schematicFiles as $file) {
					['basename' => $basename, 'extension' => $extension] = pathinfo($file);
					Loader::getInstance()->getLogger()->debug(TF::GOLD . "Loading " . $basename);
					try {
						if ($extension === 'mcstructure') {
							$store->assets[$basename] = new Asset($basename, StructureStore::getInstance()->loadStructure($basename), true, null, true);
						} else if ($extension === 'schematic') {
							$store->assets[$basename] = new Asset($basename, StructureStore::getInstance()->loadSchematic($basename), true, null, true);
						}
					} catch (StructureFileException $e) {
						Loader::getInstance()->getLogger()->debug($e->getMessage());
					}
				}
		}
	}
}