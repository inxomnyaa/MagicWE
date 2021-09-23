<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2;

use InvalidArgumentException;
use jackmd\scorefactory\ScoreFactory;
use JsonException;
use muqsit\invmenu\InvMenuHandler;
use pocketmine\block\Block;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\ItemFlags;
use pocketmine\lang\Language;
use pocketmine\lang\LanguageNotFoundException;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginException;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use RuntimeException;
use xenialdan\apibossbar\DiverseBossBar;
use xenialdan\customui\API as CustomUIAPI;
use xenialdan\libstructure\PacketListener;
use xenialdan\MagicWE2\commands\biome\BiomeInfoCommand;
use xenialdan\MagicWE2\commands\biome\BiomeListCommand;
use xenialdan\MagicWE2\commands\biome\SetBiomeCommand;
use xenialdan\MagicWE2\commands\brush\BrushCommand;
use xenialdan\MagicWE2\commands\clipboard\ClearClipboardCommand;
use xenialdan\MagicWE2\commands\clipboard\CopyCommand;
use xenialdan\MagicWE2\commands\clipboard\Cut2Command;
use xenialdan\MagicWE2\commands\clipboard\CutCommand;
use xenialdan\MagicWE2\commands\clipboard\FlipCommand;
use xenialdan\MagicWE2\commands\clipboard\PasteCommand;
use xenialdan\MagicWE2\commands\clipboard\RotateCommand;
use xenialdan\MagicWE2\commands\debug\GenerateCommandsMDCommand;
use xenialdan\MagicWE2\commands\debug\PlaceAllBlockstatesCommand;
use xenialdan\MagicWE2\commands\debug\TestAPICommand;
use xenialdan\MagicWE2\commands\DonateCommand;
use xenialdan\MagicWE2\commands\generation\CylinderCommand;
use xenialdan\MagicWE2\commands\HelpCommand;
use xenialdan\MagicWE2\commands\history\ClearhistoryCommand;
use xenialdan\MagicWE2\commands\history\RedoCommand;
use xenialdan\MagicWE2\commands\history\UndoCommand;
use xenialdan\MagicWE2\commands\InfoCommand;
use xenialdan\MagicWE2\commands\LanguageCommand;
use xenialdan\MagicWE2\commands\LimitCommand;
use xenialdan\MagicWE2\commands\palette\PaletteCommand;
use xenialdan\MagicWE2\commands\region\ReplaceCommand;
use xenialdan\MagicWE2\commands\region\SetCommand;
use xenialdan\MagicWE2\commands\ReportCommand;
use xenialdan\MagicWE2\commands\selection\ChunkCommand;
use xenialdan\MagicWE2\commands\selection\HPos1Command;
use xenialdan\MagicWE2\commands\selection\HPos2Command;
use xenialdan\MagicWE2\commands\selection\info\CountCommand;
use xenialdan\MagicWE2\commands\selection\info\ListChunksCommand;
use xenialdan\MagicWE2\commands\selection\info\SizeCommand;
use xenialdan\MagicWE2\commands\selection\Pos1Command;
use xenialdan\MagicWE2\commands\selection\Pos2Command;
use xenialdan\MagicWE2\commands\SetRangeCommand;
use xenialdan\MagicWE2\commands\tool\DebugCommand;
use xenialdan\MagicWE2\commands\tool\FloodCommand;
use xenialdan\MagicWE2\commands\tool\ToggledebugCommand;
use xenialdan\MagicWE2\commands\tool\TogglewandCommand;
use xenialdan\MagicWE2\commands\tool\WandCommand;
use xenialdan\MagicWE2\commands\utility\CalculateCommand;
use xenialdan\MagicWE2\commands\utility\ToggleSidebarCommand;
use xenialdan\MagicWE2\commands\utility\ToggleWailaCommand;
use xenialdan\MagicWE2\commands\VersionCommand;
use xenialdan\MagicWE2\exception\ActionRegistryException;
use xenialdan\MagicWE2\exception\ShapeRegistryException;
use xenialdan\MagicWE2\helper\BlockStatesEntry;
use xenialdan\MagicWE2\helper\BlockStatesParser;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\selection\shape\ShapeRegistry;
use xenialdan\MagicWE2\session\data\AssetCollection;
use xenialdan\MagicWE2\session\PluginSession;
use xenialdan\MagicWE2\session\UserSession;
use xenialdan\MagicWE2\task\action\ActionRegistry;

class Loader extends PluginBase
{
	public const FAKE_ENCH_ID = 201;
	public const PREFIX = TF::RESET . TF::BOLD . TF::GOLD . "[MagicWE2]" . TF::RESET . " ";
	public const PREFIX_ASSETS = TF::RESET . TF::BOLD . TF::GOLD . "[Asset]" . TF::RESET . " ";
	public const PREFIX_BRUSH = TF::RESET . TF::BOLD . TF::GOLD . "[Brush]" . TF::RESET . " ";
	public const PREFIX_PALETTE = TF::RESET . TF::BOLD . TF::GOLD . "[Palette]" . TF::RESET . " ";
	public const PREFIX_FORM = TF::RESET . TF::BOLD . TF::DARK_PURPLE . "[MWE2]" . TF::RESET . " ";
	/** @var Loader|null */
	private static ?Loader $instance;
	/** @var null|ShapeRegistry */
	public static ?ShapeRegistry $shapeRegistry;
	/** @var null|ActionRegistry */
	public static ?ActionRegistry $actionRegistry;
	/** @var Enchantment */
	public static Enchantment $ench;
	/** @var Language */
	private Language $baseLang;
	/** @var string[] Donator names */
	public array $donators = [];
	/** @var string */
	public string $donatorData = "";
	/** @var string */
	private static string $rotPath;
	/** @var string */
	private static string $doorRotPath;
	/** @var DiverseBossBar */#BossBar
	public DiverseBossBar $wailaBossBar;
	/** @var null|string */
	public static ?string $scoreboardAPI;
	/** @var AssetCollection */
	public static AssetCollection $assetCollection;

	/**
	 * Returns an instance of the plugin
	 * @return Loader
	 */
	public static function getInstance(): Loader
	{
		return self::$instance;
	}

	/**
	 * ShapeRegistry
	 * @return ShapeRegistry
	 * @throws ShapeRegistryException
	 */
	public static function getShapeRegistry(): ShapeRegistry
	{
		if (self::$shapeRegistry) return self::$shapeRegistry;
		throw new ShapeRegistryException("Shape registry is not initialized");
	}

	public static function getRotFlipPath(): string
	{
		return self::$rotPath;
		#return self::getInstance()->getFile() . "resources" . DIRECTORY_SEPARATOR . "rotation_flip_data.json";
	}

	public static function getDoorRotFlipPath(): string
	{
		return self::$doorRotPath;
		#return self::getInstance()->getFile() . "resources" . DIRECTORY_SEPARATOR . "door_data.json";
	}

	/**
	 * @return bool
	 */
	public static function hasScoreboard(): bool
	{
		return self::$scoreboardAPI !== null;
	}

	/**
	 * @throws PluginException
	 * @throws RuntimeException
	 * @throws JsonException
	 * @throws AssumptionFailedError
	 */
	public function onLoad(): void
	{
		self::$instance = $this;
		self::$ench = new Enchantment("", 0, ItemFlags::AXE, ItemFlags::NONE, 1);
		$enchantmapinstance = EnchantmentIdMap::getInstance();
		$enchantmapinstance->register(self::FAKE_ENCH_ID, self::$ench);
		self::$shapeRegistry = new ShapeRegistry();
		self::$actionRegistry = new ActionRegistry();
		SessionHelper::init();
		#$this->saveResource("rotation_flip_data.json", true);
		$this->saveResource("blockstate_alias_map.json", true);

		self::$rotPath = $this->getFile() . "resources" . DIRECTORY_SEPARATOR . "rotation_flip_data.json";
		self::$doorRotPath = $this->getFile() . "resources" . DIRECTORY_SEPARATOR . "door_data.json";
		$blockstateparserInstance = BlockStatesParser::getInstance();
		$blockstateparserInstance::$rotPath = $this->getFile() . "resources" . DIRECTORY_SEPARATOR . "rotation_flip_data.json";
		$blockstateparserInstance::$doorRotPath = $this->getFile() . "resources" . DIRECTORY_SEPARATOR . "door_data.json";

		$fileGetContents = file_get_contents($this->getDataFolder() . "blockstate_alias_map.json");
		if ($fileGetContents === false) {
			throw new PluginException("blockstate_alias_map.json could not be loaded! Blockstate support has been disabled!");
		}

		$blockstateparserInstance->setAliasMap(json_decode($fileGetContents, true, 512, JSON_THROW_ON_ERROR));
		//$blockstateparserInstance::runTests();//TODO REMOVE, DEBUG!!!!!!!

		self::$assetCollection = new AssetCollection(new PluginSession($this));
	}

	/**
	 * ActionRegistry
	 * @return ActionRegistry
	 * @throws ActionRegistryException
	 */
	public static function getActionRegistry(): ActionRegistry
	{
		if (self::$actionRegistry) return self::$actionRegistry;
		throw new ActionRegistryException("Action registry is not initialized");
	}

	/**
	 * @throws InvalidArgumentException
	 * @throws PluginException
	 * @throws LanguageNotFoundException
	 * @throws RuntimeException
	 */
	public function onEnable(): void
	{
		$lang = $this->getConfig()->get("language", Language::FALLBACK_LANGUAGE);
		$this->baseLang = new Language((string)$lang, $this->getFile() . "resources" . DIRECTORY_SEPARATOR . "lang" . DIRECTORY_SEPARATOR);
		$registerDeveloperCommands = $this->getConfig()->get("developer-commands", false);
		$this->getLogger()->warning("WARNING! Commands and their permissions changed! Make sure to update your permission sets!");
		if (!InvMenuHandler::isRegistered()) InvMenuHandler::register($this);
		if (!PacketListener::isRegistered()) PacketListener::register($this);
		//PacketListener::register($this);//TODO currently this just doubles the debug spam
		$this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
		$this->getServer()->getCommandMap()->registerAll("MagicWE2", [
			/* -- selection -- */
			new Pos1Command($this, "/pos1", "Set position 1", ["/1"]),
			new Pos2Command($this, "/pos2", "Set position 2", ["/2"]),
			new HPos1Command($this, "/hpos1", "Set position 1 to targeted block", ["/h1"]),
			new HPos2Command($this, "/hpos2", "Set position 2 to targeted block", ["/h2"]),
			new ChunkCommand($this, "/chunk", "Set the selection to your current chunk"),
			/* -- assets -- */
			#new AssetCommand($this, "/asset", "Manage assets (schematics, structures, clipboard)"),
			/* -- palette -- */
			new PaletteCommand($this, "/palette", "Manage block palettes"),
			/* -- tool -- */
			new WandCommand($this, "/wand", "Gives you the selection wand"),
			new TogglewandCommand($this, "/togglewand", "Toggle the wand tool on/off", ["/toggleeditwand"]),
			new DebugCommand($this, "/debug", "Gives you the debug stick, which gives information about the clicked block"),
			new ToggledebugCommand($this, "/toggledebug", "Toggle the debug stick on/off"),
			/* -- selection modify -- */
			//new ContractCommand($this,"/contract", "Contract the selection area"),
			//new ShiftCommand($this,"/shift", "Shift the selection area"),
			//new OutsetCommand($this,"/outset", "Outset the selection area"),
			//new InsetCommand($this,"/inset", "Inset the selection area"),
			/* -- selection info -- */
			new SizeCommand($this, "/size", "Get information about the selection"),
			new CountCommand($this, "/count", "Counts the number of blocks matching a mask in selection", ["/analyze"]),
			new ListChunksCommand($this, "/listchunks", "List chunks that your selection includes"),
			/* -- region -- */
			new SetCommand($this, "/set", "Fill a selection with the specified blocks"),
			//new LineCommand($this,"/line", "Draws a line segment between cuboid selection corners"),
			new ReplaceCommand($this, "/replace", "Replace blocks in an area with other blocks"),
			#new OverlayCommand($this,"/overlay", "Set a block on top of blocks in the region", ["/cover"]),
			//new CenterCommand($this,"/center", "Set the center block(s)",["/middle"]),
			//new NaturalizeCommand($this,"/naturalize", "3 layers of dirt on top then rock below"),
			//new WallsCommand($this,"/walls", "Build the four sides of the selection"),
			//new FacesCommand($this,"/faces", "Build the walls, ceiling, and floor of a selection"),
			//new MoveCommand($this,"/move", "Move the contents of the selection"),
			//new StackCommand($this,"/stack", "Repeat the contents of the selection"),
			//new HollowCommand($this,"/hollow", "Hollows out the object contained in this selection"),
			/* -- cosmetic -- */
			//new ForestCommand($this,"/forest", "Make a forest within the region"),
			//new FloraCommand($this,"/flora", "Make flora within the region"),
			/* -- generation -- */
			new CylinderCommand($this, "/cylinder", "Create a cylinder", ["/cyl"]),
			//new HollowCylinderCommand($this,"/hcyl", "Generates a hollow cylinder"),
			//new SphereCommand($this,"/sphere", "Generates a filled sphere"),
			//new HollowSphereCommand($this,"/hsphere", "Generates a hollow sphere"),
			//new PyramidCommand($this,"/pyramid", "Generates a filled pyramid"),
			//new HollowPyramidCommand($this,"/hpyramid", "Generates a hollow pyramid"),
			//new PumpkinsCommand($this,"/pumpkins", "Generate pumpkin patches"),
			/* -- clipboard -- */
			new CopyCommand($this, "/copy", "Copy the selection to the clipboard"),
			new PasteCommand($this, "/paste", "Paste the clipboard's contents"),
			new CutCommand($this, "/cut", "Cut the selection to the clipboard"),
			new Cut2Command($this, "/cut2", "Cut the selection to the clipboard - the new way"),
			new ClearClipboardCommand($this, "/clearclipboard", "Clear your clipboard"),
			new FlipCommand($this, "/flip", "Flip the contents of the clipboard across the origin", ["/mirror"]),
			new RotateCommand($this, "/rotate", "Rotate the contents of the clipboard around the origin"),
			/* -- history -- */
			new UndoCommand($this, "/undo", "Rolls back the last action"),
			new RedoCommand($this, "/redo", "Applies the last undo action again"),
			new ClearhistoryCommand($this, "/clearhistory", "Clear your history"),
			/* -- schematic -- */
			//new SchematicCommand($this,"/schematic", "Schematic commands for saving/loading areas"),
			/* -- navigation -- */
			//new UnstuckCommand($this,"/unstuck", "Switch between your position and pos1 for placement"),
			//new AscendCommand($this,"/ascend", "Switch between your position and pos1 for placement", ["/asc"]),
			//new DescendCommand($this,"/descend", "Switch between your position and pos1 for placement", ["/desc"]),
			//new CeilCommand($this,"/ceil", "Switch between your position and pos1 for placement"),
			//new ThruCommand($this,"/thru", "Switch between your position and pos1 for placement"),
			//new UpCommand($this,"/up", "Switch between your position and pos1 for placement"),
			/* -- generic -- */
			//new TogglePlaceCommand($this,"/toggleplace", "Switch between your position and pos1 for placement"),
			//new SearchItemCommand($this,"/searchitem", "Search for an item"),
			//new RangeCommand($this,"/range", "Set the brush range"),
			new SetRangeCommand($this, "/setrange", "Set tool range", ["/toolrange"]),
			new LimitCommand($this, "/limit", "Set the block change limit. Use -1 to disable"),
			new HelpCommand($this, "/help", "MagicWE help command", ["/?", "/mwe", "/wehelp"]),//Blame MCPE for client side /help shit! only the aliases work
			new VersionCommand($this, "/version", "MagicWE version", ["/ver"]),
			new InfoCommand($this, "/info", "Information about MagicWE"),
			new ReportCommand($this, "/report", "Report a bug to GitHub", ["/bug", "/github"]),
			new DonateCommand($this, "/donate", "Donate to support development of MagicWE!", ["/support", "/paypal"]),
			new LanguageCommand($this, "/language", "Set your language", ["/lang"]),
			new DonateCommand($this, "/donate", "Support the development of MagicWE and get a cape!", ["/support", "/paypal"]),
			/* -- biome -- */
			new BiomeListCommand($this, "/biomelist", "Gets all biomes available", ["/biomels"]),
			new BiomeInfoCommand($this, "/biomeinfo", "Get the biome of the targeted block"),
			new SetBiomeCommand($this, "/setbiome", "Sets the biome of your current block or region"),
			/* -- utility -- */
			//new DrainCommand($this,"/drain", "Drain a pool"),
			//new FixLavaCommand($this,"/fixlava", "Fix lava to be stationary"),
			//new FixWaterCommand($this,"/fixwater", "Fix water to be stationary"),
			//new SnowCommand($this,"/snow", "Creates a snow layer cover in the selection"),
			//new ThawCommand($this,"/thaw", "Thaws blocks in the selection"),
			new CalculateCommand($this, "/calculate", "Evaluate a mathematical expression", ["/calc", "/eval", "/evaluate", "/solve"]),
			new ToggleWailaCommand($this, "/togglewaila", "Toggle the What Am I Looking At utility", ["/waila", "/wyla"]),
			new ToggleSidebarCommand($this, "/togglesidebar", "Toggle the sidebar", ["/sidebar"]),
		]);
		if ($registerDeveloperCommands) $this->getServer()->getCommandMap()->registerAll("MagicWE2", [
			/* -- developer commands -- */
			new PlaceAllBlockstatesCommand($this, "/placeallblockstates", "Place all blockstates similar to Java debug worlds"),
			new TestAPICommand($this, "/testapi", "Internal command for testing API methods"),
			new GenerateCommandsMDCommand($this, "/generatecommandsmd", "Generates the commands.md file"),
		]);
		if (class_exists(CustomUIAPI::class)) {
			$this->getLogger()->notice("CustomUI found, can use ui-based commands");
			$this->getServer()->getCommandMap()->registerAll("MagicWE2", [
				/* -- brush -- */
				new BrushCommand($this, "/brush", "Opens the brush tool menu"),
				/* -- tool -- */
				new FloodCommand($this, "/flood", "Opens the flood fill tool menu", ["/floodfill"]),
			]);
		} else {
			$this->getLogger()->notice(TF::RED . "CustomUI NOT found, can NOT use ui-based commands");
		}
		if (class_exists(ScoreFactory::class)) {
			$this->getLogger()->notice("Scoreboard API found, can use scoreboards");
			self::$scoreboardAPI = ScoreFactory::class;
		} else {
			$this->getLogger()->notice(TF::RED . "Scoreboard API NOT found, can NOT use scoreboards");
		}

		//register WAILA bar
		$this->wailaBossBar = new DiverseBossBar();
		$this->wailaBossBar->setPercentage(1.0);
		//WAILA updater
		$this->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(function (): void {
			$players = Loader::getInstance()->wailaBossBar->getPlayers();
			foreach ($players as $player) {
				if (!$player->isOnline() || !SessionHelper::hasSession($player) || (($session = SessionHelper::getUserSession($player)) instanceof UserSession && !$session->isWailaEnabled())) {
					Loader::getInstance()->wailaBossBar->hideFrom([$player]);
					continue;
				}
				if (($block = $player->getTargetBlock(10)) instanceof Block && $block->getId() !== 0) {
					Loader::getInstance()->wailaBossBar->showTo([$player]);
					$stateEntry = BlockStatesParser::getStateByBlock($block);
					$sub = $block->getName();
					$title = (string)$block;
					if ($stateEntry instanceof BlockStatesEntry) {
						$sub = implode("," . TF::EOL, explode(",", BlockStatesParser::printStates($stateEntry, false)));
					}
					$distancePercentage = round(floor($block->getPosition()->distance($player->getEyePos())) / 10, 1);
					Loader::getInstance()->wailaBossBar->setTitleFor([$player], $title)->setSubTitleFor([$player], $sub)->setPercentage($distancePercentage);
				} else
					Loader::getInstance()->wailaBossBar->hideFrom([$player]);
			}
		}), 60, 1);
	}

	public function onDisable(): void
	{
		try {
			foreach (SessionHelper::getPluginSessions() as $session) {
				SessionHelper::destroySession($session, false);
			}
			foreach (SessionHelper::getUserSessions() as $session) {
				SessionHelper::destroySession($session);
			}
		} catch (JsonException $e) {
			$this->getLogger()->logException($e);
		}
	}

	/**
	 * @return Language
	 * @api
	 */
	public function getLanguage(): Language
	{
		return $this->baseLang;
	}

	public function getToolDistance(): int
	{
		return (int)$this->getConfig()->get("tool-range", 100);
	}

	public function getEditLimit(): int
	{
		return (int)$this->getConfig()->get("limit", -1);
	}

	/**
	 * @return array
	 * @throws RuntimeException
	 */
	public static function getInfo(): array
	{
		return [
			"| " . TF::GREEN . self::getInstance()->getFullName() . TF::RESET . " | Information |",
			"| --- | --- |",
			"| Website | " . self::getInstance()->getDescription()->getWebsite() . " |",
			"| Version | " . self::getInstance()->getDescription()->getVersion() . " |",
			"| Plugin API Version | " . implode(", ", self::getInstance()->getDescription()->getCompatibleApis()) . " |",
			"| Authors | " . implode(", ", self::getInstance()->getDescription()->getAuthors()) . " |",
			"| Enabled | " . (Server::getInstance()->getPluginManager()->isPluginEnabled(self::getInstance()) ? TF::GREEN . "Yes" : TF::RED . "No") . TF::RESET . " |",
			"| Uses UI | " . (class_exists(CustomUIAPI::class) ? TF::GREEN . "Yes" : TF::RED . "No") . TF::RESET . " |",
			"| Uses ScoreFactory | " . (class_exists(ScoreFactory::class) ? TF::GREEN . "Yes" : TF::RED . "No") . TF::RESET . " |",
			"| Phar | " . (strpos(self::getInstance()->getFile(), 'phar:') !== false ? TF::GREEN . "Yes" : TF::RED . "No") . TF::RESET . " |",
			"| PMMP Protocol Version | " . Server::getInstance()->getVersion() . " |",
			"| PMMP Version | " . Server::getInstance()->getPocketMineVersion() . " |",
			"| PMMP API Version | " . Server::getInstance()->getApiVersion() . " |",
		];
	}

	/**
	 * Returns the path to the language files folder.
	 *
	 * @return string
	 */
	public function getLanguageFolder(): string
	{
		return $this->getFile() . "resources" . DIRECTORY_SEPARATOR . "lang" . DIRECTORY_SEPARATOR;
	}

	/**
	 * Get a list of available languages
	 * @return string[]
	 * @phpstan-return array<string, string> code=>name
	 * @throws LanguageNotFoundException
	 */
	public function getLanguageList(): array
	{
		return Language::getLanguageList($this->getLanguageFolder());
	}
}