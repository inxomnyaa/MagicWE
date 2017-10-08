<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2;

use pocketmine\lang\BaseLang;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use xenialdan\customui\API as UIAPI;
use xenialdan\customui\elements\Dropdown;
use xenialdan\customui\elements\Input;
use xenialdan\customui\elements\Label;
use xenialdan\customui\elements\Slider;
use xenialdan\customui\windows\CustomForm;
use xenialdan\MagicWE2\commands\BrushCommand;
use xenialdan\MagicWE2\commands\CopyCommand;
use xenialdan\MagicWE2\commands\FillCommand;
use xenialdan\MagicWE2\commands\PasteCommand;
use xenialdan\MagicWE2\commands\Pos1Command;
use xenialdan\MagicWE2\commands\Pos2Command;
use xenialdan\MagicWE2\commands\ReplaceCommand;

class Loader extends PluginBase{
	/** @var Selection[] */
	public static $selections = [];
	/** @var Clipboard[] */
	public static $clipboards = [];
	public static $prefix = '[MagicWE by XenialDan] ';
	/** @var Loader */
	private static $instance = null;
	public static $uis;
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
	}

	public function onEnable(){
		$this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new SendTask($this), 20 * 5, 20 * 5);
		$this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
		$this->getServer()->getCommandMap()->register(Pos1Command::class, new Pos1Command($this));
		$this->getServer()->getCommandMap()->register(Pos2Command::class, new Pos2Command($this));
		$this->getServer()->getCommandMap()->register(FillCommand::class, new FillCommand($this));
		$this->getServer()->getCommandMap()->register(ReplaceCommand::class, new ReplaceCommand($this));
		$this->getServer()->getCommandMap()->register(CopyCommand::class, new CopyCommand($this));
		$this->getServer()->getCommandMap()->register(PasteCommand::class, new PasteCommand($this));
		$this->getServer()->getCommandMap()->register(BrushCommand::class, new BrushCommand($this));
		$this->reloadUIs();
	}

	/**
	 * @api
	 * @return BaseLang
	 */
	public function getLanguage(): BaseLang{
		return $this->baseLang;
	}

	public function reloadUIs(){
		UIAPI::resetUIs($this);
		$lang = $this->getLanguage();
		$ui = new CustomForm(Loader::$prefix . TextFormat::BOLD . TextFormat::DARK_PURPLE . $lang->translateString('ui.brush.title'));
		$dropdown = new Dropdown($lang->translateString('ui.brush.options.type.tile'));
		$dropdown->addOption($lang->translateString('ui.brush.options.type.sphere'), true);
		$dropdown->addOption($lang->translateString('ui.brush.options.type.cylinder'));
		$dropdown->addOption($lang->translateString('ui.brush.options.type.square'));
		$ui->addElement($dropdown);
		$ui->addElement(new Slider('Diameter/Width', 1, 255, 1.0));
		$ui->addElement(new Slider('Height', 1, 255, 1.0));
		$ui->addElement(new Input('Blocks', 'Blocks separated by semicolons'));
		$ui->addElement(new Label('Click the "Submit" button to apply'));
		self::$uis['brushUI'] = UIAPI::addUI($this, $ui);
		/* ********* */
	}
}