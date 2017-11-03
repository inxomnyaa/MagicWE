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
	private $selections;
	/** @var UUID|null */
	private $latestselection = null;
	/** @var Clipboard[] */
	private $clipboards;
	/** @var Clipboard[] */
	private $undo;
	/** @var Clipboard[] */
	private $redo;
	/** @var bool */
	private $wandEnabled = true;

	public function __construct(Player $player){
		$this->setPlayer($player);
		$this->setUUID($player->getUniqueId());
	}

	public function __destruct(){ // TODO clean up objects
		unset($this);
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
	 */
	public function addSelection(Selection $selection){
		$this->selections[$selection->getUUID()->toString()] = $selection;
		$this->setLatestSelectionUUID($selection->getUUID());
	}

	/**
	 * @param UUID $uuid
	 * @return null|Selection
	 */
	public function getSelectionByUUID(UUID $uuid){
		return $this->selections[$uuid->toString()] ?? null;
	}

	/**
	 * @param string $uuid
	 * @return null|Selection
	 */
	public function getSelectionByString(string $uuid){
		return $this->selections[$uuid] ?? null;
	}

	/**
	 * @return null|Selection
	 */
	public function getLatestSelection(){
		$latestSelectionUUID = $this->getLatestSelectionUUID();
		if(is_null($latestSelectionUUID)) return null;
		return $this->selections[$latestSelectionUUID->toString()] ?? null;
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
	 * @return Clipboard[]
	 */
	public function getClipboards(): array{
		return $this->clipboards;
	}

	/**
	 * @param Clipboard[] $clipboards
	 */
	public function setClipboards(array $clipboards){
		$this->clipboards = $clipboards;
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
		return Loader::$prefix . "The wand tool is now " . $wandEnabled ? TextFormat::GREEN . " enabled" : TextFormat::RED . " disabled" . TextFormat::RESET . "!";//TODO #translation
	}
}