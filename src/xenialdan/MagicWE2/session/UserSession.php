<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\session;

use jackmd\scorefactory\ScoreFactory;
use jackmd\scorefactory\ScoreFactoryException;
use JsonSerializable;
use pocketmine\lang\Language;
use pocketmine\lang\LanguageNotFoundException;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use SplDoublyLinkedList;
use xenialdan\apibossbar\BossBar;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\Scoreboard;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\session\data\AssetCollection;
use xenialdan\MagicWE2\session\data\BrushCollection;
use xenialdan\MagicWE2\session\data\Outline;
use xenialdan\MagicWE2\session\data\PaletteCollection;
use xenialdan\MagicWE2\tool\Debug;
use function mkdir;
use function var_dump;

class UserSession extends Session implements JsonSerializable //TODO use JsonMapper
{
	private ?Player $player = null;
	private BossBar $bossBar;
	public ?Scoreboard $sidebar = null;
	private bool $wandEnabled = true;
	private bool $debugToolEnabled = true;
	private bool $wailaEnabled = true;
	private bool $sidebarEnabled = true;//TODO settings/commands
	private bool $outlineEnabled = true;
	private ?Outline $outline = null;
	private BrushCollection $brushes;
	private AssetCollection $assets;
	private PaletteCollection $palettes;
	private ?Language $lang = null;
	public ?Debug $debug = null;

	public function __construct(Player $player)
	{
		$this->setPlayer($player);
		$this->cleanupInventory();
		$this->setUUID($player->getUniqueId());
		$this->bossBar = (new BossBar())->addPlayer($player);
		$this->bossBar->hideFrom([$player]);
		if (Loader::hasScoreboard()) {
			$this->sidebar = new Scoreboard();
		}
		$this->undoHistory = new SplDoublyLinkedList();
		$this->redoHistory = new SplDoublyLinkedList();
		$this->brushes = new BrushCollection($this);
		$this->assets = new AssetCollection($this);
		$this->palettes = new PaletteCollection($this);
		try{
			if(is_null($this->lang))
				$this->lang = new Language(Language::FALLBACK_LANGUAGE, Loader::getInstance()->getLanguageFolder());
		}catch(LanguageNotFoundException){
		}
		Loader::getInstance()->getLogger()->debug("Created new session for player {$player->getName()}");
	}

	public function __destruct(){
		Loader::getInstance()->getLogger()->debug("Destructing session {$this->getUUID()} for user " . $this->getPlayer()->getName());
		$this->bossBar->removeAllPlayers();
		if(Loader::hasScoreboard() && $this->sidebar !== null){
			try{
				ScoreFactory::removeObjective($this->getPlayer(), true);
			}catch(ScoreFactoryException){
			}
		}
	}

	public function getLanguage() : Language
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

	public function setPlayer(?Player $player): void
	{
		$this->player = $player;
	}

	public function getPlayer(): ?Player
	{
		return $this->player;
	}

	public function isWandEnabled(): bool
	{
		return $this->wandEnabled;
	}

	public function setWandEnabled(bool $wandEnabled): string
	{
		$this->wandEnabled = $wandEnabled;
		$this->sidebar?->handleScoreboard($this);
		return Loader::PREFIX . $this->getLanguage()->translateString('tool.wand.setenabled', [($wandEnabled ? TF::GREEN . $this->getLanguage()->translateString('enabled') : TF::RED . $this->getLanguage()->translateString('disabled'))]);
	}

	public function isDebugToolEnabled(): bool
	{
		return $this->debugToolEnabled;
	}

	public function setDebugToolEnabled(bool $debugToolEnabled): string
	{
		$this->debugToolEnabled = $debugToolEnabled;
		$this->sidebar?->handleScoreboard($this);
		return Loader::PREFIX . $this->getLanguage()->translateString('tool.debug.setenabled', [($debugToolEnabled ? TF::GREEN . $this->getLanguage()->translateString('enabled') : TF::RED . $this->getLanguage()->translateString('disabled'))]);
	}

	public function isSidebarEnabled(): bool
	{
		return $this->sidebarEnabled;
	}

	public function setSidebarEnabled(bool $sidebarEnabled): string
	{
		$player = $this->getPlayer();
		if (!$player instanceof Player) return TF::RED . "Session has no player";
		$this->sidebarEnabled = $sidebarEnabled;
		if ($sidebarEnabled) {
			$this->sidebar->handleScoreboard($this);
		} else {
			ScoreFactory::removeObjective($player);
		}
		return Loader::PREFIX . $this->getLanguage()->translateString('tool.sidebar.setenabled', [($sidebarEnabled ? TF::GREEN . $this->getLanguage()->translateString('enabled') : TF::RED . $this->getLanguage()->translateString('disabled'))]);
	}

	public function isWailaEnabled(): bool
	{
		return $this->wailaEnabled;
	}

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
		$this->sidebar?->handleScoreboard($this);
		return Loader::PREFIX . $this->getLanguage()->translateString('tool.waila.setenabled', [($wailaEnabled ? TF::GREEN . $this->getLanguage()->translateString('enabled') : TF::RED . $this->getLanguage()->translateString('disabled'))]);
	}

	public function isOutlineEnabled(): bool
	{
		return $this->outlineEnabled;
	}

	public function setOutlineEnabled(bool $outlineEnabled): string
	{
		$player = $this->getPlayer();
		if (!$player instanceof Player) return TF::RED . "Session has no player";
		$this->outlineEnabled = $outlineEnabled;
		if ($outlineEnabled) {
			$selection = $this->getLatestSelection();
			if ($selection instanceof Selection && $selection->isValid()) $this->outline = $this->createOrUpdateOutline($selection);
		} else {
			if ($this->outline instanceof Outline) {
				$this->outline->remove();
			}
		}
		$this->sidebar?->handleScoreboard($this);
		return Loader::PREFIX . $this->getLanguage()->translateString('tool.outline.setenabled', [($outlineEnabled ? TF::GREEN . $this->getLanguage()->translateString('enabled') : TF::RED . $this->getLanguage()->translateString('disabled'))]);
	}

	public function getOutline(): ?Outline
	{
		return $this->outline;
	}

	public function createOrUpdateOutline(Selection $selection): Outline
	{
		return $this->outline?->setSelection($selection) ?? new Outline($selection, $this->getPlayer());
	}

	public function getBossBar(): BossBar
	{
		return $this->bossBar;
	}

	public function getBrushes(): BrushCollection
	{
		return $this->brushes;
	}

	public function getAssets(): AssetCollection
	{
		return $this->assets;
	}

	public function getPalettes(): PaletteCollection
	{
		return $this->palettes;
	}

	public function cleanupInventory(): void
	{
		foreach ($this->getPlayer()->getInventory()->getContents() as $slot => $item) {
			if (!is_null(($item->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE_BRUSH)))) {
				$this->getPlayer()->getInventory()->clear($slot);
			}
			if (!is_null(($item->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE)))) {
				$this->getPlayer()->getInventory()->clear($slot);
			}
			if (!is_null(($item->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE_ASSET)))) {
				$this->getPlayer()->getInventory()->clear($slot);
			}
			if (!is_null(($item->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE_PALETTE)))) {
				$this->getPlayer()->getInventory()->clear($slot);
			}
		}
	}

	public function __toString()
	{
		//TODO translations
		return __CLASS__ .
			" UUID: " . $this->getUUID()->__toString() .
			" Player: " . $this->getPlayer()->getName() .
			" Wand tool enabled: " . ($this->isWandEnabled() ? "enabled" : "disabled") .
			" Debug stick enabled: " . ($this->isDebugToolEnabled() ? "enabled" : "disabled") .
			" WAILA enabled: " . ($this->isWailaEnabled() ? "enabled" : "disabled") .
			" Sidebar enabled: " . ($this->sidebarEnabled ? "enabled" : "disabled") .
			" Outline enabled: " . ($this->outlineEnabled ? "enabled" : "disabled") .
			" BossBar: " . $this->getBossBar()->actorId .
			" Selections: " . count($this->getSelections()) .
			" Latest: " . $this->getLatestSelectionUUID() .
			" Clipboards: " . count($this->getClipboards()) .
			" Current: " . $this->getCurrentClipboardIndex() .
			" Undos: " . count($this->undoHistory) .
			" Redos: " . count($this->redoHistory) .
			" Brushes: " . count($this->brushes->brushes) .
			" Assets: " . count($this->assets->assets) .
			" Palettes: " . count($this->palettes->palettes);
	}

	public function sendMessage(string $message): void
	{
		$this->player->sendMessage(Loader::PREFIX . $message);
	}

	//TODO use libmarshal to serialize this
	public function jsonSerialize(): array
	{
		$return = [
			"uuid" => $this->getUUID()->toString(),
			"wandEnabled" => $this->wandEnabled,
			"debugToolEnabled" => $this->debugToolEnabled,
			"wailaEnabled" => $this->wailaEnabled,
			"sidebarEnabled" => $this->sidebarEnabled,
			"outlineEnabled" => $this->outlineEnabled,
			"brushes" => $this->brushes->brushes,
			//todo assets, palettes
			"latestSelection" => $this->getLatestSelection(),
			"currentClipboard" => $this->getCurrentClipboard(),
			"language" => $this->getLanguage()->getLang(),
		];
		if($this->debug !== null) $return["debug"] = $this->debug->jsonSerialize();
		var_dump($return);
		return $return;
	}

	//TODO use libmarshal to serialize this
	public function save(): void
	{
		@mkdir(Loader::getInstance()->getDataFolder() . "sessions", 0777, true);
		file_put_contents(Loader::getInstance()->getDataFolder() . "sessions" . DIRECTORY_SEPARATOR .
			$this->getPlayer()->getName() . ".json",
			json_encode($this, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
		);
	}
}