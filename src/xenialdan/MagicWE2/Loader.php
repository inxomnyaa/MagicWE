<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2;

use pocketmine\item\enchantment\Enchantment;
use pocketmine\lang\BaseLang;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use xenialdan\MagicWE2\commands\BrushCommand;
use xenialdan\MagicWE2\commands\CopyCommand;
use xenialdan\MagicWE2\commands\CountCommand;
use xenialdan\MagicWE2\commands\CylinderCommand;
use xenialdan\MagicWE2\commands\DebugCommand;
use xenialdan\MagicWE2\commands\FloodCommand;
use xenialdan\MagicWE2\commands\HelpCommand;
use xenialdan\MagicWE2\commands\PasteCommand;
use xenialdan\MagicWE2\commands\Pos1Command;
use xenialdan\MagicWE2\commands\Pos2Command;
use xenialdan\MagicWE2\commands\RedoCommand;
use xenialdan\MagicWE2\commands\ReplaceCommand;
use xenialdan\MagicWE2\commands\SetCommand;
use xenialdan\MagicWE2\commands\ToggledebugCommand;
use xenialdan\MagicWE2\commands\TogglewandCommand;
use xenialdan\MagicWE2\commands\UndoCommand;
use xenialdan\MagicWE2\commands\WandCommand;

class Loader extends PluginBase
{
    const FAKE_ENCH_ID = 201;
    public static $prefix = "§6§l[MagicWE]§r ";
    /** @var Loader */
    private static $instance = null;
    private $baseLang;

    /**
     * Returns an instance of the plugin
     * @return Loader
     */
    public static function getInstance()
    {
        return self::$instance;
    }

    public function onLoad()
    {
        self::$instance = $this;
        // TODO restore sessions
        $this->getLogger()->info("Restoring Sessions");

        foreach ($this->getServer()->getOnlinePlayers() as $player) { // Restores on /reload for now
            if ($player->hasPermission("we.session")) {
                if (is_null(($session = API::getSession($player)))) {
                    $session = API::addSession(new Session($player));
                    Loader::getInstance()->getLogger()->debug("Created new session with UUID {" . $session->getUUID() . "} for player {" . $session->getPlayer()->getName() . "}");
                } else {
                    $session->setPlayer($player);
                    Loader::getInstance()->getLogger()->debug("Restored session with UUID {" . $session->getUUID() . "} for player {" . $session->getPlayer()->getName() . "}");
                }
            }
        }
        $this->getLogger()->info("Sessions successfully restored");
        $ench = new Enchantment(self::FAKE_ENCH_ID, "", 0, Enchantment::SLOT_ALL, Enchantment::SLOT_NONE, 1);
        Enchantment::registerEnchantment($ench);
    }

    public function onEnable()
    {
        $this->saveDefaultConfig();
        $this->reloadConfig();
        $lang = $this->getConfig()->get("language", BaseLang::FALLBACK_LANGUAGE);
        $this->baseLang = new BaseLang((string)$lang, $this->getFile() . "resources/");
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        $this->getServer()->getCommandMap()->registerAll("we", [
            new Pos1Command("/pos1", "Select first position", ["/1"]),
            new Pos2Command("/pos2", "Select second position", ["/2"]),
            new SetCommand("/set", "Fill an area with the specified blocks", ["/fill"]),
            new ReplaceCommand("/replace", "Replace blocks in an area with other blocks"),
            new CopyCommand("/copy", "Copy an area into a clipboard"),
            new PasteCommand("/paste", "Paste your clipboard"),
            new WandCommand("/wand", "Gives you the selection wand"),
            new TogglewandCommand("/togglewand", "Toggle the wand tool on/off"),
            #new FlipCommand("/flip","Flip a clipboard by the given axis"),
            new UndoCommand("/undo", "Rolls back the last action"),
            new RedoCommand("/redo", "Applies the last undo action again"),
            new DebugCommand("/debug", "Gives you the debug stick, which gives information about the clicked block"),
            new ToggledebugCommand("/toggledebug", "Toggle the debug stick on/off"),
            #new RotateCommand("/rotate","Rotate a clipboard by x*90 degrees"),
            new CylinderCommand("/cylinder", "Create a cylinder", ["/cyl"]),
            new CountCommand("/count", "Count blocks in selection", ["/analyze"]),
            new HelpCommand("/help", "MagicWE help command", ["/?", "/mwe", "/wehelp"])//Blame MCPE for client side /help shit! only the aliases work
        ]);
        if (class_exists("xenialdan\\customui\\API")) {
            $this->getLogger()->notice("CustomUI found, can use ui-based commands");
            $this->getServer()->getCommandMap()->registerAll("we", [
                new BrushCommand("/brush", "Opens the brush tool menu"),
                new FloodCommand("/flood", "Opens the flood tool menu"),
            ]);
        } else {
            $this->getLogger()->notice(TextFormat::RED . "CustomUI NOT found, can NOT use ui-based commands");
        }
    }

    public function onDisable()
    {
        $this->getLogger()->info("Destroying Sessions");
        foreach (API::getSessions() as $session) {
            //TODO store sessions
            API::destroySession($session);
        }
        $this->getLogger()->info("Sessions successfully destroyed");
    }

    /**
     * @api
     * @return BaseLang
     */
    public function getLanguage(): BaseLang
    {
        return $this->baseLang;
    }
}