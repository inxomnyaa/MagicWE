<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2;

use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\utils\UUID;

class Session{

	/** @var UUID */
	private $uuid;
	/** @var Player|null */
	private $player = null;
	/** @var Selection[] */
	private $selections = [];
	/** @var UUID|null */
	private $latestselection = null;
	/** @var Clipboard[] */
	private $clipboards = [];
	/** @var Clipboard[] */
	private $undo = [];
	/** @var Clipboard[] */
	private $redo = [];
	/** @var bool */
	private $wandEnabled = true;
	/** @var bool */
	private $debugStickEnabled = true;

	public function __construct(Player $player){
		$this->setPlayer($player);
		$this->setUUID($player->getUniqueId());
	}

	public function __destruct(){
		if (!is_null($this->uuid)) Loader::getInstance()->getLogger()->debug("Destructing session " . $this->getUUID()->__toString());
		foreach ($this as &$value){
			$value = null;
			unset($value);
		}
	}

	/**
	 * @param null|Player $player
	 */
	public function setPlayer($player){
		$this->player = $player;
	}

	/**
	 * @return null|Player
	 */
	public function getPlayer(){
		return $this->player;
	}

	/**
	 * @return UUID
	 */
	public function getUUID(): UUID{
		return $this->uuid;
	}

	/**
	 * @param UUID $uuid
	 */
	public function setUUID(UUID $uuid){
		$this->uuid = $uuid;
	}

	/**
	 * @param Selection $selection
	 * @return null|Selection
	 */
	public function &addSelection(Selection $selection){
		$this->selections[$selection->getUUID()->toString()] = $selection;
		$this->setLatestSelectionUUID($selection->getUUID());
		$selection = $this->getLatestSelection();
		return $selection;
	}

	/**
	 * @param UUID $uuid
	 * @return null|Selection
	 */
	public function &getSelectionByUUID(UUID $uuid){
		$selection = $this->selections[$uuid->toString()] ?? null;
		return $selection;
	}

	/**
	 * @param string $uuid
	 * @return null|Selection
	 */
	public function &getSelectionByString(string $uuid){
		$selection = $this->selections[$uuid] ?? null;
		return $selection;
	}

	/**
	 * @return null|Selection
	 */
	public function &getLatestSelection(){
		$latestSelectionUUID = $this->getLatestSelectionUUID();
		if (is_null($latestSelectionUUID)){
			$selection = null;
			return $selection;
		}
		$selection = $this->selections[$latestSelectionUUID->toString()] ?? null;
		return $selection;
	}

	/**
	 * @return Selection[]
	 */
	public function getSelections(){
		return $this->selections;
	}

	/**
	 * @param mixed $selections
	 */
	public function setSelections($selections){
		$this->selections = $selections;
	}

	/**
	 * @return UUID|null
	 */
	public function getLatestSelectionUUID(): ?UUID{
		return $this->latestselection;
	}

	/**
	 * @param UUID $latestselection
	 */
	public function setLatestSelectionUUID(UUID $latestselection){
		$this->latestselection = $latestselection;
	}

	/**
	 * TODO
	 * @return Clipboard[]
	 */
	public function getClipboards(): array{
		return $this->clipboards;
	}

	/**
	 * TODO
	 * @param Clipboard[] $clipboards
     * @return bool
	 */
	public function setClipboards(array $clipboards){
		$this->clipboards = $clipboards;
        return true;
	}

	/**
	 * @return bool
	 */
	public function isWandEnabled(): bool{
		return $this->wandEnabled;
	}

	/**
	 * @param bool $wandEnabled
	 * @return string
	 */
	public function setWandEnabled(bool $wandEnabled){
		$this->wandEnabled = $wandEnabled;
		return Loader::$prefix . "The wand tool is now " . ($wandEnabled ? TextFormat::GREEN . "enabled" : TextFormat::RED . "disabled") . TextFormat::RESET . "!";//TODO #translation
	}

	/**
	 * @return bool
	 */
	public function isDebugStickEnabled(): bool{
		return $this->debugStickEnabled;
	}

	/**
	 * @param bool $debugStick
	 * @return string
	 */
	public function setDebugStickEnabled(bool $debugStick){
		$this->debugStickEnabled = $debugStick;
		return Loader::$prefix . "The debug stick is now " . ($debugStick ? TextFormat::GREEN . "enabled" : TextFormat::RED . "disabled") . TextFormat::RESET . "!";//TODO #translation
	}

	/**
	 * @return Clipboard[]
	 */
	public function getUndos(): array{
		return $this->undo;
	}

	/**
	 * @param Clipboard[] $undo
	 */
	private function setUndos(array $undo){
		$this->undo = $undo;
	}

	/**
	 * @param Clipboard $clipboard
	 */
	public function addUndo(Clipboard $clipboard){
		array_push($this->undo, $clipboard);
	}

	/**
	 * @return null|Clipboard
	 */
	public function getLatestUndo(): ?Clipboard{
		$clipboards = $this->getUndos();
		$return = array_pop($clipboards);
		$this->setUndos($clipboards);
		return $return;
	}

	/**
	 * @return Clipboard[]
	 */
	public function getRedos(): array{
		return $this->redo;
	}

	/**
	 * @param Clipboard[] $redo
	 */
	private function setRedos(array $redo){
		$this->redo = $redo;
	}

	/**
	 * @param Clipboard $clipboard
	 */
	public function addRedo(Clipboard $clipboard){
		array_push($this->redo, $clipboard);
	}

	/**
	 * @return null|Clipboard
	 */
	public function getLatestRedo(): ?Clipboard{
		$clipboards = $this->getRedos();
		$return = array_pop($clipboards);
		$this->setRedos($clipboards);
		return $return;
	}

	/*
	 * TODO list:
	 * session storing/recovering from file/cleanup if too old
	 * session items
	 * session brushes
	 * recover session items + commands to get back already created/configured items/tools/brushes
	 * proper multi-selection-usage
	 * setState/getState on big actions, status bar/boss bar/texts/titles/popups
	 * inspect other player's sessions
	 * destroy session if owning player lost permission/gets banned
	 * optimise destroySession/__destruct of sessions
	 * undo/redo clipboards
	 * clipboard selection (renaming?)
	 *
	 * ask users what else they want
	 */
}