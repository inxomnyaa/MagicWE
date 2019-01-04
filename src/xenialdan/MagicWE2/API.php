<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2;

use pocketmine\block\Block;
use pocketmine\block\BlockIds;
use pocketmine\block\UnknownBlock;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\item\ItemFactory;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\nbt\LittleEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\NamedTag;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use xenialdan\MagicWE2\clipboard\CopyClipboard;
use xenialdan\MagicWE2\shape\ShapeGenerator;
use xenialdan\MagicWE2\task\AsyncClipboardTask;
use xenialdan\MagicWE2\task\AsyncCopyTask;
use xenialdan\MagicWE2\task\AsyncCountTask;
use xenialdan\MagicWE2\task\AsyncFillTask;
use xenialdan\MagicWE2\task\AsyncReplaceTask;
use xenialdan\MagicWE2\task\AsyncRevertTask;

class API
{
    // Base flag to modify on
    const FLAG_BASE = 1;
    // Only replaces the air
    const FLAG_KEEP_BLOCKS = 0x01; // -r
    // Only change non-air blocks
    const FLAG_KEEP_AIR = 0x02; // -k
    // The -a flag makes it not paste air.
    const FLAG_PASTE_WITHOUT_AIR = 0x03; // -a
    // Pastes or sets hollow
    const FLAG_HOLLOW = 0x04; // -h
    // The -n flag makes it only consider naturally occurring blocks.
    const FLAG_NATURAL = 0x05; // -n
    // Without the -p flag, the paste will appear centered at the target location.
    // With the flag, the paste will appear relative to where you had
    // stood, relative by the copied area when you copied it.
    const FLAG_POSITION_RELATIVE = 0x06; // -p
    // Without the -v flag, block checks, selections and replacing will use and check the exact meta
    // of the blocks, with the flag it will check for similar variants
    // For example: Oak Logs with any rotation instead of a specific rotation
    const FLAG_VARIANT = 0x07; // -v
    // With the -m flag the damage values / meta will be kept
    // For example: Replacing all wool blocks with concrete of the same color
    const FLAG_KEEP_META = 0x08; // -m
    // Pastes or sets hollow but closes off the ends
    const FLAG_HOLLOW_CLOSED = 0x09; // -hc//TODO

    //TODO Split into seperate Class (SessionStorage?)
    /** @var Session[] */
    private static $sessions = [];
    //TODO Split into seperate Class (SchematicStorage?)
    /** @var Clipboard[] *///TODO
    private static $schematics = [];

    /**
     * @param Selection $selection
     * @param Session $session
     * @param Block[] $newblocks
     * @param int $flags
     * @return bool
     */
    public static function fillAsync(Selection $selection, Session $session, $newblocks = [], int $flags = self::FLAG_BASE)
    {
        try {
            Server::getInstance()->getAsyncPool()->submitTask(new AsyncFillTask($selection, $session->getPlayer()->getUniqueId(), $selection->getTouchedChunks(), $newblocks, $flags));
        } catch (\Exception $e) {
            Loader::getInstance()->getLogger()->logException($e);
            return false;
        }
        return true;
    }

    /**
     * @param Selection $selection
     * @param Session|null $session
     * @param Block[] $oldBlocks
     * @param Block[] $newBlocks
     * @param int $flags
     * @return bool
     */
    public static function replaceAsync(Selection $selection, ?Session $session, $oldBlocks = [], $newBlocks = [], int $flags = self::FLAG_BASE)
    {
        try {
            Server::getInstance()->getAsyncPool()->submitTask(new AsyncReplaceTask($selection, $session->getPlayer()->getUniqueId(), $selection->getTouchedChunks(), $oldBlocks, $newBlocks, $flags));
        } catch (\Exception $e) {
            Loader::getInstance()->getLogger()->logException($e);
            return false;
        }
        return true;
    }

    /**
     * @param Selection $selection
     * @param null|Session $session
     * @param int $flags
     * @return bool
     */
    public static function copyAsync(Selection $selection, ?Session $session, int $flags = self::FLAG_BASE)
    {
        #return false;
        try {
            if (self::hasFlag($flags, self::FLAG_POSITION_RELATIVE))//TODO relative or not by flags
                $offset = new Vector3();
            else
                $offset = $selection->getMinVec3()->subtract($session->getPlayer())->floor();
            Server::getInstance()->getAsyncPool()->submitTask(new AsyncCopyTask($selection, $offset, $session->getPlayer()->getUniqueId(), $selection->getTouchedChunks(), $flags));
        } catch (\Exception $e) {
            Loader::getInstance()->getLogger()->logException($e);
            return false;
        }
        return true;
    }

    /**
     * TODO: flag parsing, Position to paste at
     * @param CopyClipboard $clipboard
     * @param null|Session $session
     * @param Position $target
     * @param int $flags
     * @return bool
     */
    public static function pasteAsync(CopyClipboard $clipboard, ?Session $session, Position $target, int $flags = self::FLAG_BASE)
    {
        #return false;
        try {
            $clipboard->setCenter($target);//TODO check
            Server::getInstance()->getAsyncPool()->submitTask(new AsyncClipboardTask($clipboard, $session->getPlayer()->getUniqueId(), $clipboard->getTouchedChunksByLevel($target->asVector3()), AsyncClipboardTask::TYPE_PASTE, $flags));
        } catch (\Exception $e) {
            Loader::getInstance()->getLogger()->logException($e);
            return false;
        }
        return true;
    }

    /**
     * @param Session $session
     * @return bool
     */
    public static function undoAsync(Session $session)
    {
        try {
            $session->getPlayer()->sendMessage("You had " . count($session->getUndos()) . " undo actions left");//TODO remove
            $clipboard = $session->getLatestUndo();
            if (is_null($clipboard)) {
                $session->getPlayer()->sendMessage("Nothing to undo");//TODO prettify
                return true;
            }
            Server::getInstance()->getAsyncPool()->submitTask(new AsyncRevertTask($clipboard, $session->getPlayer()->getUniqueId(), $clipboard->getTouchedChunks(), AsyncRevertTask::TYPE_UNDO));
        } catch (\Exception $e) {
            Loader::getInstance()->getLogger()->logException($e);
            return false;
        }
        return true;
    }

    /**
     * @param Session $session
     * @return bool
     */
    public static function redoAsync(Session $session)
    {
        try {
            $session->getPlayer()->sendMessage("You had " . count($session->getRedos()) . " redo actions left");//TODO remove
            $clipboard = $session->getLatestRedo();
            if (is_null($clipboard)) {
                $session->getPlayer()->sendMessage("Nothing to redo");//TODO prettify
                return true;
            }
            Server::getInstance()->getAsyncPool()->submitTask(new AsyncRevertTask($clipboard, $session->getPlayer()->getUniqueId(), $clipboard->getTouchedChunks(), AsyncRevertTask::TYPE_REDO));
        } catch (\Exception $e) {
            Loader::getInstance()->getLogger()->logException($e);
            return false;
        }
        return true;
    }

    /**
     * @param Selection $selection
     * @param null|Session $session
     * @param Block[] $filterBlocks
     * @param int $flags
     * @return bool
     */

    public static function countAsync(Selection $selection, Session $session, array $filterBlocks, int $flags = self::FLAG_BASE)
    {
        try {
            Server::getInstance()->getAsyncPool()->submitTask(new AsyncCountTask($selection, $session->getPlayer()->getUniqueId(), $selection->getTouchedChunks(), $filterBlocks, $flags));
        } catch (\Exception $e) {
            Loader::getInstance()->getLogger()->logException($e);
            return false;
        }
        return true;
    }

    /**
     * Creates a brush at a specific location with the passed settings
     * @param Block $target
     * @param NamedTag $settings
     * @param Session $session
     * @return bool
     * @throws \Exception
     */
    public static function createBrush(Block $target, NamedTag $settings, Session $session)
    {//TODO messages
        $shape = null;
        if (!$settings instanceof CompoundTag) return false;
        $messages = [];
        $error = false;
        switch ($type = $settings->getInt("type", -1)) {
            case ShapeGenerator::TYPE_CUBOID:
            case ShapeGenerator::TYPE_CUBE:
            case ShapeGenerator::TYPE_CYLINDER:
            case ShapeGenerator::TYPE_SPHERE:
                {
                    $shape = ShapeGenerator::getShape($target->getLevel(), $type, self::compoundToArray($settings));
                    $shape->setCenter($target->asVector3());//TODO fix the offset?: if you have a uneven number, the center actually is between 2 blocks
                    return self::fillAsync($shape, $session, self::blockParser($shape->options['blocks'], $messages, $error), $shape->options["flags"]);
                    break;
                }
            case ShapeGenerator::TYPE_CUSTOM://TODO fix/Change to actual shape, flags
                {
                    $clipboard = $session->getClipboards()[0] ?? null;
                    if (is_null($clipboard)) {
                        $session->getPlayer()->sendMessage(TextFormat::RED . "You have no clipboard - create one first");
                        return false;
                    }
                    return self::pasteAsync($clipboard, $session, $target);//TODO flags & proper brush tool
                    break;
                }
            default:
                {
                    $session->getPlayer()->sendMessage("Unknown shape");
                }
        }
        return false;
    }

    /**
     * @param Block $target
     * @param NamedTag $settings
     * @param Session $session
     * @param int $flags
     * @return bool
     */
    public static function floodArea(Block $target, NamedTag $settings, Session $session, int $flags = self::FLAG_BASE)
    { //TODO
        if (!$settings instanceof CompoundTag) return null;
        $shape = ShapeGenerator::getShape($target->getLevel(), ShapeGenerator::TYPE_FLOOD, self::compoundToArray($settings));
        $shape->setCenter($target->asVector3());//TODO fix the offset?: if you have a uneven number, the center actually is between 2 blocks
        $messages = [];
        $error = false;
        return self::fillAsync($shape, $session, self::blockParser($shape->options['blocks'], $messages, $error), $flags);
    }

    /// SESSION RELATED API PART

    public static function &addSession(Session $session)
    {
        self::$sessions[$session->getPlayer()->getLowerCaseName()] = $session;
        return self::$sessions[$session->getPlayer()->getLowerCaseName()];
    }

    public static function destroySession(Session $session)
    {
        unset(self::$sessions[$session->getPlayer()->getLowerCaseName()]);
    }

    /**
     * @param Player $player
     * @return Session|null
     * @throws \Error
     */
    public static function &getSession(Player $player): ?Session
    {
        $session = self::$sessions[$player->getLowerCaseName()] ?? null;
        if (is_null($session)) {
            if ($player->hasPermission("we.session")) {
                $session = API::addSession(new Session($player));
                Loader::getInstance()->getLogger()->debug("Created new session with UUID {" . $session->getUUID() . "} for player {" . $session->getPlayer()->getName() . "}");
                return $session;
            } else {
                $player->sendMessage(Loader::$prefix . TextFormat::RED . "You do not have the permission \"magicwe.session\"");
            }
        }
        if (!$player->hasPermission("we.session")) {
            if ($session instanceof Session)
                self::destroySession($session);
            Loader::getInstance()->getLogger()->info("Player " . $player->getName() . " does not have the permission \"magicwe.session\", but tried to use " . Loader::$prefix);
        }
        return $session;
    }

    /**
     * @param Player $player
     * @return bool
     */
    public static function hasSession(Player $player): bool
    {
        return !is_null(self::$sessions[$player->getLowerCaseName()] ?? null);
    }

    /**
     * @return Session[]
     */
    public static function getSessions(): array
    {
        return self::$sessions;
    }

    /// SCHEMATIC RELATED API PART

    /**
     * @return Clipboard[]
     */
    public static function getSchematics(): array
    {
        return self::$schematics;
    }

    /**
     * @param Clipboard[] $schematics
     */
    public static function setSchematics(array $schematics)
    {
        self::$schematics = $schematics;
    }

    /* HELPER FUNCTIONS API PART */

    /**
     * Parses String representations of flags into an integer with flags applied
     * @param string[] $flags An array containing string representations of the flags
     * @return int
     */
    public static function flagParser(array $flags)
    {
        $flagmeta = self::FLAG_BASE;
        foreach ($flags as $flag) {
            switch ($flag) {
                case "-keepblocks":
                    $flagmeta ^= self::FLAG_BASE << self::FLAG_KEEP_BLOCKS;
                    break;
                case  "-keepair":
                    $flagmeta ^= self::FLAG_BASE << self::FLAG_KEEP_AIR;
                    break;
                case  "-a":
                    $flagmeta ^= self::FLAG_BASE << self::FLAG_PASTE_WITHOUT_AIR;
                    break;
                case  "-h":
                    $flagmeta ^= self::FLAG_BASE << self::FLAG_HOLLOW;
                    break;
                case  "-hc":
                    $flagmeta ^= self::FLAG_BASE << self::FLAG_HOLLOW_CLOSED;
                    break;
                case  "-n":
                    $flagmeta ^= self::FLAG_BASE << self::FLAG_NATURAL;
                    break;
                case  "-p":
                    $flagmeta ^= self::FLAG_BASE << self::FLAG_POSITION_RELATIVE;
                    break;
                case  "-v":
                    $flagmeta ^= self::FLAG_BASE << self::FLAG_VARIANT;
                    break;
                case  "-m":
                    $flagmeta ^= self::FLAG_BASE << self::FLAG_KEEP_META;
                    break;
                default:
                    Server::getInstance()->getLogger()->warning("The flag $flag is unknown");
            }
        }
        return $flagmeta;
    }

    /**
     * Checks if $flags has the specified flag $check
     * @param int $flags The return value of flagParser
     * @param int $check The flag to check
     * @return bool
     */
    public static function hasFlag(int $flags, int $check)
    {
        return ($flags & (self::FLAG_BASE << $check)) > 0;
    }

    /**
     * More fail proof method of parsing a string to a Block
     * @param string $fullstring
     * @param array $messages
     * @param bool $error
     * @return Block[]
     */
    public static function blockParser(string $fullstring, array &$messages, bool &$error)
    {
        $blocks = [];
        foreach (self::fromString($fullstring, true) as [$name, $item]) {
            if (($item instanceof ItemBlock) or ($item instanceof Item && $item->getBlock()->getId() !== Block::AIR)) {
                $block = $item->getBlock();
                $blocks[] = $block;
            } else {
                $error = true;
                $messages[] = Loader::$prefix . TextFormat::RED . "Could not find a block/item with the " . (is_numeric($name) ? "id" : "name") . ": " . $name;
                continue;
            }
            if ($block instanceof UnknownBlock) {
                $messages[] = Loader::$prefix . TextFormat::GOLD . $block . " is an unknown block";
            }
        }

        return $blocks;
    }

    /**
     * TODO: remove when ItemFactory::fromString() is fully supporting air aka "air","0","0:0","minecraft:air"
     * Replacement function for ItemFactory::fromString() with more fail-proof AIR support
     *
     * Tries to parse the specified string into Item ID/meta identifiers, and returns Item instances it created.
     *
     * Example accepted formats:
     * - `diamond_pickaxe:5`
     * - `minecraft:string`
     * - `351:4 (lapis lazuli ID:meta)`
     *
     * If multiple item instances are to be created, their identifiers must be comma-separated, for example:
     * `diamond_pickaxe,wooden_shovel:18,iron_ingot`
     *
     * @param string $str
     * @param bool $multiple
     *
     * @return array
     */
    public static function fromString(string $str, bool $multiple = false)
    {
        if ($multiple === true) {
            $blocks = [];
            foreach (explode(",", $str) as $b) {
                $blocks[] = self::fromString($b, false);
            }

            return $blocks;
        } else {
            $b = explode(":", str_replace([" ", "minecraft:"], ["_", ""], trim($str)));
            if (!isset($b[1])) {
                $meta = 0;
            } else {
                $meta = $b[1] & 0xFFFF;
            }

            if (is_numeric($b[0])) {
                $item = ItemFactory::get(((int)$b[0]) & 0xFFFF, $meta);
            } elseif (defined(Item::class . "::" . strtoupper($b[0]))) {
                $item = ItemFactory::get(constant(Item::class . "::" . strtoupper($b[0])), $meta);
                if ($item->getId() === Item::AIR and strtoupper($b[0]) !== "AIR") {
                    $item = null;
                }
            } else {
                $item = null;
            }

            return [$b[0], $item];
        }
    }

    /**
     * Parses a CompoundTag into an array
     * @param CompoundTag $compoundTag
     * @return array
     */
    public static function compoundToArray(CompoundTag $compoundTag)
    {
        $nbt = new LittleEndianNBTStream();
        $nbt->writeTag($compoundTag);
        return $nbt::toArray($compoundTag);
    }

    /**
     * TODO needs updates/fixes
     * @param Block $block
     * @param int $timesRotate
     * @return int|mixed
     */
    public static function rotationMetaHelper(Block $block, $timesRotate = 1)
    {
        $meta = $block->getDamage();
        $variant = $block->getVariant();
        $rotation = [0, 0, 0, 0];
        switch ($block->getId()) {
            case BlockIds::FURNACE:
            case BlockIds::BURNING_FURNACE:
            case BlockIds::CHEST:
            case BlockIds::TRAPPED_CHEST:
            case BlockIds::ENDER_CHEST:
            case BlockIds::STONE_BUTTON:
            case BlockIds::WOODEN_BUTTON:
            case BlockIds::WALL_BANNER:
            case BlockIds::PURPLE_GLAZED_TERRACOTTA:
            case BlockIds::WHITE_GLAZED_TERRACOTTA :
            case BlockIds::ORANGE_GLAZED_TERRACOTTA :
            case BlockIds::MAGENTA_GLAZED_TERRACOTTA :
            case BlockIds::LIGHT_BLUE_GLAZED_TERRACOTTA:
            case BlockIds::YELLOW_GLAZED_TERRACOTTA:
            case BlockIds::LIME_GLAZED_TERRACOTTA :
            case BlockIds::PINK_GLAZED_TERRACOTTA :
            case BlockIds::GRAY_GLAZED_TERRACOTTA :
            case BlockIds::SILVER_GLAZED_TERRACOTTA :
            case BlockIds::CYAN_GLAZED_TERRACOTTA:
            case BlockIds::BLUE_GLAZED_TERRACOTTA:
            case BlockIds::BROWN_GLAZED_TERRACOTTA :
            case BlockIds::GREEN_GLAZED_TERRACOTTA :
            case BlockIds::RED_GLAZED_TERRACOTTA :
            case BlockIds::BLACK_GLAZED_TERRACOTTA :
            case BlockIds::LADDER:
            case BlockIds::WALL_SIGN:
                {
                    $rotation = [3, 4, 2, 5];
                    break;
                }
            case BlockIds::ITEM_FRAME_BLOCK:
                {
                    $rotation = [2, 1, 3, 0];
                    //$rotation = [14,12,15,12];//TODO
                    break;
                }
            case BlockIds::IRON_TRAPDOOR:
            case BlockIds::TRAPDOOR:
                {
                    $rotation = [2, 1, 3, 0];
                    //$rotation = [14,12,15,12];//TODO
                    break;
                }
            case BlockIds::UNPOWERED_REPEATER:
            case BlockIds::UNPOWERED_COMPARATOR:
            case BlockIds::POWERED_REPEATER:
            case BlockIds::POWERED_COMPARATOR:
            case BlockIds::END_PORTAL_FRAME:
            case BlockIds::LIT_PUMPKIN:
            case BlockIds::PUMPKIN:
                {
                    $rotation = [0, 1, 2, 3];
                    break;
                }
            case BlockIds::WOODEN_STAIRS:
            case BlockIds::STONE_STAIRS:
            case BlockIds::BRICK_STAIRS:
            case BlockIds::STONE_BRICK_STAIRS:
            case BlockIds::NETHER_BRICK_STAIRS:
            case BlockIds::SANDSTONE_STAIRS:
            case BlockIds::SPRUCE_STAIRS:
            case BlockIds::BIRCH_STAIRS:
            case BlockIds::JUNGLE_STAIRS:
            case BlockIds::QUARTZ_STAIRS:
            case BlockIds::ACACIA_STAIRS:
            case BlockIds::DARK_OAK_STAIRS:
            case BlockIds::RED_SANDSTONE_STAIRS:
            case BlockIds::PURPUR_STAIRS:
                {
                    $rotation = [3, 0, 2, 1];
                    break;
                }
            case BlockIds::WOODEN_DOOR_BLOCK:
            case BlockIds::IRON_DOOR_BLOCK:
            case BlockIds::SPRUCE_DOOR_BLOCK:
            case BlockIds::BIRCH_DOOR_BLOCK:
            case BlockIds::JUNGLE_DOOR_BLOCK:
            case BlockIds::ACACIA_DOOR_BLOCK:
            case BlockIds::DARK_OAK_DOOR_BLOCK:
            case BlockIds::ANVIL:
                {
                    $rotation = [3, 0, 1, 2];
                    break;
                }
            case BlockIds::VINE:
                {
                    $rotation = [4, 8, 1, 2];
                    break;
                }
            case BlockIds::BED_BLOCK:
            case BlockIds::OAK_FENCE_GATE:
            case BlockIds::SPRUCE_FENCE_GATE:
            case BlockIds::BIRCH_FENCE_GATE:
            case BlockIds::JUNGLE_FENCE_GATE:
            case BlockIds::DARK_OAK_FENCE_GATE:
            case BlockIds::ACACIA_FENCE_GATE:
                {
                    $rotation = [2, 3, 0, 1];
                    //TODO [10, 11,8,9]
                    break;
                }
            case BlockIds::STANDING_BANNER:
            case BlockIds::SIGN_POST:
                {
                    $rotation = [0, 4, 8, 12];
                    //TODO all rotation
                    break;
                }
            case BlockIds::QUARTZ_BLOCK:
            case BlockIds::PURPUR_BLOCK:
                {
                    $rotation = [10, 6, 10, 6];
                    break;
                }
            case BlockIds::HAY_BLOCK:
            case BlockIds::BONE_BLOCK:
            case BlockIds::LOG:
            case BlockIds::LOG2:
                {
                    $rotation = [10, 6, 10, 6];
                    break;
                }
            case BlockIds::END_ROD:
                {
                    $rotation = [2, 4, 3, 5];
                    break;
                }
            case BlockIds::PISTON:
            case BlockIds::STICKY_PISTON:
                {
                    $rotation = [2, 5, 3, 4];
                    break;
                }
            case BlockIds::TORCH:
            case BlockIds::REDSTONE_ORE:
            case BlockIds::UNLIT_REDSTONE_TORCH:
                {
                    $rotation = [3, 2, 4, 1];
                    break;
                }
            //TODO: Heads
        }
        $currentrotationindex = array_search($meta % count($rotation), $rotation);
        if ($currentrotationindex === false) return $block->getDamage();
        $currentrotationindex += $timesRotate;
        #return $rotation[($currentrotationindex % count($rotation))];
        $extra = intval($meta / count($rotation));
        return $rotation[$currentrotationindex % count($rotation)] + ($extra * count($rotation)) % 16;
    }
}