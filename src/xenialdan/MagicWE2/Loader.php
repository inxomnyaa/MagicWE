<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2;

use pocketmine\lang\BaseLang;
use pocketmine\plugin\PluginBase;
use xenialdan\MagicWE2\commands\AsyncFillCommand;
use xenialdan\MagicWE2\commands\BrushCommand;
use xenialdan\MagicWE2\commands\CopyCommand;
use xenialdan\MagicWE2\commands\FillCommand;
use xenialdan\MagicWE2\commands\PasteCommand;
use xenialdan\MagicWE2\commands\Pos1Command;
use xenialdan\MagicWE2\commands\Pos2Command;
use xenialdan\MagicWE2\commands\ReplaceCommand;
use xenialdan\MagicWE2\commands\TogglewandCommand;
use xenialdan\MagicWE2\commands\WandCommand;

class Loader extends PluginBase{
	/** @var Selection[] */
	public static $selections = [];
	/** @var Clipboard[] */
	public static $clipboards = [];
	public static $prefix = '[MagicWE by XenialDan] ';
	/** @var Loader */
	private static $instance = null;
	private $baseLang;

	/**
	 * Returns an instance of the plugin
	 * @return Loader
	 */
	public static function getInstance(){
		return self::$instance;
	}

	public function onLoad(){
		self::$instance = $this;
		self::$prefix = $this->getDescription()->getPrefix();
		$this->saveDefaultConfig();
		$this->reloadConfig();
		$lang = $this->getConfig()->get("language", BaseLang::FALLBACK_LANGUAGE);
		$this->baseLang = new BaseLang((string)$lang, $this->getFile() . "resources/");
		// TODO restore sessions
	}

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
		$this->getServer()->getCommandMap()->register(Pos1Command::class, new Pos1Command($this));
		$this->getServer()->getCommandMap()->register(Pos2Command::class, new Pos2Command($this));
		$this->getServer()->getCommandMap()->register(FillCommand::class, new FillCommand($this));
		$this->getServer()->getCommandMap()->register(ReplaceCommand::class, new ReplaceCommand($this));
		$this->getServer()->getCommandMap()->register(CopyCommand::class, new CopyCommand($this));
		$this->getServer()->getCommandMap()->register(PasteCommand::class, new PasteCommand($this));
		$this->getServer()->getCommandMap()->register(BrushCommand::class, new BrushCommand($this));
		$this->getServer()->getCommandMap()->register(BrushCommand::class, new WandCommand($this));
		$this->getServer()->getCommandMap()->register(FillCommand::class, new AsyncFillCommand($this));
		$this->getServer()->getCommandMap()->register(TogglewandCommand::class, new TogglewandCommand($this));
	}

	public function onDisable(){
		$this->getServer()->getLogger()->debug("Destroying Sessions");
		foreach (API::getSessions() as $session){
			//TODO store sessions
			API::destroySession($session);
		}
		$this->getServer()->getLogger()->debug("Sessions successfully destroyed");
	}

	/**
	 * @api
	 * @return BaseLang
	 */
	public function getLanguage(): BaseLang{
		return $this->baseLang;
	}
}