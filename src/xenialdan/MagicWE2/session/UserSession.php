<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\session;

use Ds\Deque;
use Exception;
use InvalidArgumentException;
use JsonException;
use JsonSerializable;
use pocketmine\item\Item;
use pocketmine\lang\Language;
use pocketmine\lang\LanguageNotFoundException;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use pocketmine\uuid\UUID;
use TypeError;
use xenialdan\apibossbar\BossBar;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\exception\ActionNotFoundException;
use xenialdan\MagicWE2\exception\BrushException;
use xenialdan\MagicWE2\exception\ShapeNotFoundException;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\tool\Brush;
use xenialdan\MagicWE2\tool\BrushProperties;

class UserSession extends Session implements JsonSerializable
{
	/** @var Player|null */
	private $player;
	/** @var BossBar */
	private $bossBar;
	/** @var bool */
	private $wandEnabled = true;
	/** @var bool */
	private $debugToolEnabled = true;
	/** @var bool */
	private $wailaEnabled = true;
	/** @var Brush[] */
	private $brushes = [];
	/** @var Language|null */
	private $lang;

	public function __construct(Player $player)
	{
		$this->setPlayer($player);
		$this->cleanupInventory();
		$this->setUUID($player->getUniqueId());
		$this->bossBar = (new BossBar())->addPlayer($player);
		$this->bossBar->hideFrom([$player]);
		$this->undoHistory = new Deque();
		$this->redoHistory = new Deque();
		if (is_null($this->lang)) $this->setLanguage(Language::FALLBACK_LANGUAGE);
		Loader::getInstance()->getLogger()->debug("Created new session for player {$player->getName()}");
	}

	public function __destruct()
	{
		Loader::getInstance()->getLogger()->debug("Destructing session {$this->getUUID()} for user " . $this->getPlayer()->getName());
		$this->bossBar->removeAllPlayers();
	}

	/**
	 * @return Language
	 */
	public function getLanguage(): Language
	{
		return $this->lang;
	}

	/**
	 * Set the language for the user. Uses iso639-2 language code
	 * @param string $langShort iso639-2 conform language code (3 letter)
	 * @throws LanguageNotFoundException
	 */
	public function setLanguage(string $langShort): void
	{
		$langShort = strtolower($langShort);
		if (isset(Loader::getInstance()->getLanguageList()[$langShort])) {
			$this->lang = new Language($langShort, Loader::getInstance()->getLanguageFolder());
			$this->sendMessage(TF::GREEN . $this->getLanguage()->translateString("session.language.set", [$this->getLanguage()->getName()]));
		} else {
			$this->lang = new Language(Language::FALLBACK_LANGUAGE, Loader::getInstance()->getLanguageFolder());
			$this->sendMessage(TF::RED . $this->getLanguage()->translateString("session.language.notfound", [$langShort]));
		}
	}

	/**
	 * @param null|Player $player
	 */
	public function setPlayer($player): void
	{
		$this->player = $player;
	}

	/**
	 * @return null|Player
	 */
	public function getPlayer(): ?Player
	{
		return $this->player;
	}

	/**
	 * @return bool
	 */
	public function isWandEnabled(): bool
	{
		return $this->wandEnabled;
	}

	/**
	 * @param bool $wandEnabled
	 * @return string
	 */
	public function setWandEnabled(bool $wandEnabled): string
	{
		$this->wandEnabled = $wandEnabled;
		return Loader::PREFIX . $this->getLanguage()->translateString('tool.wand.setenabled', [($wandEnabled ? TF::GREEN . $this->getLanguage()->translateString('enabled') : TF::RED . $this->getLanguage()->translateString('disabled'))]) . TF::RESET . "!";
	}

	/**
	 * @return bool
	 */
	public function isDebugToolEnabled(): bool
	{
		return $this->debugToolEnabled;
	}

	/**
	 * @param bool $debugToolEnabled
	 * @return string
	 */
	public function setDebugToolEnabled(bool $debugToolEnabled): string
	{
		$this->debugToolEnabled = $debugToolEnabled;
		return Loader::PREFIX . $this->getLanguage()->translateString('tool.debug.setenabled', [($debugToolEnabled ? TF::GREEN . $this->getLanguage()->translateString('enabled') : TF::RED . $this->getLanguage()->translateString('disabled'))]) . TF::RESET . "!";
	}

	/**
	 * @return bool
	 */
	public function isWailaEnabled(): bool
	{
		return $this->wailaEnabled;
	}

	/**
	 * @param bool $wailaEnabled
	 * @return string
	 */
	public function setWailaEnabled(bool $wailaEnabled): string
	{
		$player = $this->getPlayer();
		if (!$player instanceof Player) return TF::RED . "Session has no player";
		$this->wailaEnabled = $wailaEnabled;
		if ($wailaEnabled) {
			Loader::getInstance()->wailaBossBar->showTo([$player]);
		} else {
			Loader::getInstance()->wailaBossBar->hideFrom([$player]);
		}
		return Loader::PREFIX . $this->getLanguage()->translateString('tool.waila.setenabled', [($wailaEnabled ? TF::GREEN . $this->getLanguage()->translateString('enabled') : TF::RED . $this->getLanguage()->translateString('disabled'))]) . TF::RESET . "!";
	}

	/**
	 * @return BossBar
	 */
	public function getBossBar(): BossBar
	{
		return $this->bossBar;
	}

	/**
	 * TODO exception for not a brush
	 * @param Item $item
	 * @return Brush
	 * @throws Exception
	 */
	public function getBrushFromItem(Item $item): Brush
	{
		if ((($entry = $item->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE_BRUSH))) instanceof CompoundTag) {
			$version = $entry->getInt("version", 0);
			if ($version !== BrushProperties::VERSION) {
				throw new BrushException("Brush can not be restored - version mismatch");
			}
			/** @var BrushProperties $properties */
			$properties = json_decode($entry->getString("properties"), true, 512, JSON_THROW_ON_ERROR);
			$uuid = UUID::fromString($properties->uuid);
			$brush = $this->getBrush($uuid);
			if ($brush instanceof Brush) {
				return $brush;
			}
			$brush = new Brush($properties);
			$this->addBrush($brush);
			return $brush;
		}
		throw new BrushException("The item is not a valid brush!");
	}

	/**
	 * TODO exception for not a brush
	 * @param UUID $uuid
	 * @return null|Brush
	 */
	public function getBrush(UUID $uuid): ?Brush
	{
		return $this->brushes[$uuid->toString()] ?? null;
	}

	/**
	 * TODO exception for not a brush
	 * @param Brush $brush UUID will be set automatically
	 * @return void
	 */
	public function addBrush(Brush $brush): void
	{
		$this->brushes[$brush->properties->uuid] = $brush;
		$this->sendMessage($this->getLanguage()->translateString('session.brush.added', [$brush->getName()]));
	}

	/**
	 * @param Brush $brush UUID will be set automatically
	 * @param bool $delete If true, it will be removed from the session brushes
	 * @return void
	 */
	public function removeBrush(Brush $brush, bool $delete = false): void
	{
		if ($delete) unset($this->brushes[$brush->properties->uuid]);
		foreach ($this->getPlayer()->getInventory()->getContents() as $slot => $item) {
			if (($entry = $item->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE_BRUSH)) instanceof CompoundTag) {
				if ($entry->getString("id") === $brush->properties->uuid) {
					$this->getPlayer()->getInventory()->clear($slot);
				}
			}
		}
		if ($delete) $this->sendMessage($this->getLanguage()->translateString('session.brush.deleted', [$brush->getName(), $brush->properties->uuid]));
		else $this->sendMessage($this->getLanguage()->translateString('session.brush.removed', [$brush->getName(), $brush->properties->uuid]));
	}

	/**
	 * TODO exception for not a brush
	 * @param Brush $brush UUID will be set automatically
	 * @return void
	 * @throws ActionNotFoundException
	 * @throws InvalidArgumentException
	 * @throws ShapeNotFoundException
	 * @throws JsonException
	 * @throws TypeError
	 */
	public function replaceBrush(Brush $brush): void
	{
		$this->brushes[$brush->properties->uuid] = $brush;
		$new = $brush->toItem();
		foreach ($this->getPlayer()->getInventory()->getContents() as $slot => $item) {
			if (($entry = $item->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE_BRUSH)) instanceof CompoundTag) {
				if ($entry->getString("id") === $brush->properties->uuid) {
					$this->getPlayer()->getInventory()->setItem($slot, $new);
				}
			}
		}
	}

	/**
	 * @return Brush[]
	 */
	public function getBrushes(): array
	{
		return $this->brushes;
	}

	public function cleanupInventory(): void
	{
		foreach ($this->getPlayer()->getInventory()->getContents() as $slot => $item) {
			/** @var CompoundTag $entry */
			if (!is_null(($entry = $item->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE_BRUSH)))) {
				$this->getPlayer()->getInventory()->clear($slot);
			}
			if (!is_null(($entry = $item->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE)))) {
				$this->getPlayer()->getInventory()->clear($slot);
			}
		}
	}

	public function __toString()
	{
		return __CLASS__ .
			" UUID: " . $this->getUUID()->__toString() .
			" Player: " . $this->getPlayer()->getName() .
			" Wand tool enabled: " . ($this->isWandEnabled() ? "enabled" : "disabled") .
			" Debug tool enabled: " . ($this->isDebugToolEnabled() ? "enabled" : "disabled") .
			" BossBar: " . $this->getBossBar()->entityId .
			" Selections: " . count($this->getSelections()) .
			" Latest: " . $this->getLatestSelectionUUID() .
			" Clipboards: " . count($this->getClipboards()) .
			" Current: " . $this->getCurrentClipboardIndex() .
			" Undos: " . count($this->undoHistory) .
			" Redos: " . count($this->redoHistory) .
			" Brushes: " . count($this->brushes);
	}

	public function sendMessage(string $message): void
	{
		$this->player->sendMessage(Loader::PREFIX . $message);
	}

	/**
	 * Specify data which should be serialized to JSON
	 * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
	 * @return mixed data which can be serialized by <b>json_encode</b>,
	 * which is a value of any type other than a resource.
	 * @since 5.4.0
	 */
	public function jsonSerialize()
	{
		return [
			"uuid" => $this->getUUID()->toString(),
			"wandEnabled" => $this->wandEnabled,
			"debugToolEnabled" => $this->debugToolEnabled,
			"brushes" => $this->brushes,
			"latestSelection" => $this->getLatestSelection(),
			"currentClipboard" => $this->getCurrentClipboard(),
			"language" => $this->getLanguage()->getLang()
		];
	}

	public function save(): void
	{
		file_put_contents(Loader::getInstance()->getDataFolder() . "sessions" . DIRECTORY_SEPARATOR .
			$this->getPlayer()->getName() . ".json",
			json_encode($this, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
		);
	}
}