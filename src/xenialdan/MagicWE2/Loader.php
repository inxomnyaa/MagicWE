<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2;

use pocketmine\lang\BaseLang;
use pocketmine\plugin\PluginBase;
use xenialdan\MagicWE2\commands\AsyncFillCommand;
use xenialdan\MagicWE2\commands\BrushCommand;
use xenialdan\MagicWE2\commands\CopyCommand;
use xenialdan\MagicWE2\commands\FillCommand;
use xenialdan\MagicWE2\commands\FlipCommand;
use xenialdan\MagicWE2\commands\PasteCommand;
use xenialdan\MagicWE2\commands\Pos1Command;
use xenialdan\MagicWE2\commands\Pos2Command;
use xenialdan\MagicWE2\commands\ReplaceCommand;
use xenialdan\MagicWE2\commands\SchematicCommand;
use xenialdan\MagicWE2\commands\TogglewandCommand;
use xenialdan\MagicWE2\commands\WandCommand;

class Loader extends PluginBase{
	public static $prefix = '[MagicWE by XenialDan] ';
	/** @var Loader */
	private static $instance = null;
	private $baseLang;
	public static $path = [];

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
		$this->getLogger()->info("Restoring Sessions");

		foreach ($this->getServer()->getOnlinePlayers() as $player){ // Restores on /reload for now
			if ($player->hasPermission("we.session")){
				if (is_null(($session = API::getSession($player)))){
					$session = API::addSession(new Session($player));
					Loader::getInstance()->getLogger()->debug("Created new session with UUID {" . $session->getUUID() . "} for player {" . $session->getPlayer()->getName() . "}");
				} else{
					$session->setPlayer($player);
					Loader::getInstance()->getLogger()->debug("Restored session with UUID {" . $session->getUUID() . "} for player {" . $session->getPlayer()->getName() . "}");
				}
			}
		}
		$this->getLogger()->info("Sessions successfully restored");
		self::$path['schematics'] = $this->getDataFolder() . 'schematics';
		@mkdir(self::$path['schematics']);
	}

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
		$this->getServer()->getCommandMap()->registerAll("we", [
			new Pos1Command($this),
			new Pos2Command($this),
			new FillCommand($this),
			new ReplaceCommand($this),
			new CopyCommand($this),
			new PasteCommand($this),
			new BrushCommand($this),
			new WandCommand($this),
			new AsyncFillCommand($this),
			new TogglewandCommand($this),
			new FlipCommand($this),
			new SchematicCommand($this)
		]);
	}

	public function onDisable(){
		$this->getLogger()->info("Destroying Sessions");
		foreach (API::getSessions() as $session){
			//TODO store sessions
			API::destroySession($session);
		}
		$this->getLogger()->info("Sessions successfully destroyed");
	}

	/**
	 * @api
	 * @return BaseLang
	 */
	public function getLanguage(): BaseLang{
		return $this->baseLang;
	}
}