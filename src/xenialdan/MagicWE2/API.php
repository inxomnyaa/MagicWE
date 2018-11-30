<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2;

use pocketmine\block\Block;
use pocketmine\block\BlockIds;
use pocketmine\block\UnknownBlock;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\item\ItemFactory;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\nbt\LittleEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\NamedTag;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use xenialdan\MagicWE2\shape\ShapeGenerator;
use xenialdan\MagicWE2\task\AsyncFillTask;

class API
{
    /**
     * "  -p also kills pets.\n" +
     * "  -n also kills NPCs.\n" +
     * "  -g also kills Golems.\n" +
     * "  -a also kills animals.\n" +
     * "  -b also kills ambient mobs.\n" +
     * "  -t also kills mobs with name tags.\n" +
     * "  -f compounds all previous flags.\n" +
     * "  -r also destroys armor stands.\n" */

    /**
     * Only replaces the air
     */
    const FLAG_KEEP_BLOCKS = 0x01; // -r
    /**
     * Only change non-air blocks
     */
    const FLAG_KEEP_AIR = 0x02; // -k
    /**
     * The -a flag makes it not paste air.
     */
    const FLAG_PASTE_WITHOUT_AIR = 0x03; // -a
    /**
     * Pastes or sets hollow
     */
    const FLAG_HOLLOW = 0x04; // -h
    /**
     * The -n flag makes it only consider naturally occurring blocks.
     */
    const FLAG_NATURAL = 0x05; // -n
    /**
     * Without the -p flag, the paste will appear centered at the target location.
     * With the flag, the paste will appear relative to where you had
     * stood, relative by the copied area when you copied it.
     */
    const FLAG_UNCENTERED = 0x06; // -p
    /**
     * Without the -v flag, block checks, selections and replacing will use and check the exact meta
     * of the blocks, with the flag it will check for similar variants
     * For example: Oak Logs with any rotation instead of a specific rotation
     */
    const FLAG_VARIANT = 0x07; // -v
    /**
     * With the -m flag the damage values / meta will be kept
     */
    const FLAG_KEEP_META = 0x08; // -m

    /** @var Session[] */
    private static $sessions = [];
    /** @var Clipboard[] */
    private static $schematics = [];

    public static function flagParser(array $flags)
    {
        $flagmeta = 1;
        foreach ($flags as $flag) {
            switch ($flag) {
                case "-keepblocks":
                    $flagmeta ^= 1 << self::FLAG_KEEP_BLOCKS;
                    break;
                #case  "-keepair":
                #	$flagmeta ^= 1 << self::FLAG_KEEP_AIR;
                #	break;
                case  "-a":
                    $flagmeta ^= 1 << self::FLAG_PASTE_WITHOUT_AIR;
                    break;
                case  "-h":
                    $flagmeta ^= 1 << self::FLAG_HOLLOW;
                    break;
                case  "-n":
                    $flagmeta ^= 1 << self::FLAG_NATURAL;
                    break;
                case  "-p":
                    $flagmeta ^= 1 << self::FLAG_UNCENTERED;
                    break;
                case  "-v":
                    $flagmeta ^= 1 << self::FLAG_VARIANT;
                    break;
                case  "-m":
                    $flagmeta ^= 1 << self::FLAG_KEEP_META;
                    break;
                default:
                    Server::getInstance()->getLogger()->warning("The flag $flag is unknown");
            }
        }
        return $flagmeta;
    }

    /**
     * Checks if a flag is used
     * @param int $flags The return value of flagParser
     * @param int $check The flag to check
     * @return bool
     */
    public static function hasFlag(int $flags, int $check)
    {
        return ($flags & (1 << $check)) > 0;
    }

    /**
     * @param Selection $selection
     * @param Session|null $session
     * @param Block[] $newblocks
     * @param array ...$flagarray
     * @return bool
     */
    public static function fill(Selection $selection, ?Session $session, $newblocks = [], ...$flagarray)
    {
        $flags = self::flagParser($flagarray);
        $changed = 0;
        $time = microtime(TRUE);
        try {
            $blocks = [];
            /** @var Block $block */
            foreach ($selection->getBlocks($flags) as $block) {
                $level = $selection->getLevel() ?? (!is_null($session) ? $session->getPlayer()->getLevel() : $block->getLevel());
                if ($block->y >= Level::Y_MAX || $block->y < 0) continue;
                if (API::hasFlag($flags, API::FLAG_HOLLOW) && ($block->x > $selection->getMinVec3()->getX() && $block->x < $selection->getMaxVec3()->getX()) && ($block->y > $selection->getMinVec3()->getY() && $block->y < $selection->getMaxVec3()->getY()) && ($block->z > $selection->getMinVec3()->getZ() && $block->z < $selection->getMaxVec3()->getZ())) continue;
                $newblock = $newblocks[array_rand($newblocks, 1)];
                $newblock->position($block->asPosition());
                if (API::hasFlag($flags, API::FLAG_KEEP_BLOCKS)) {
                    if ($level->getBlock($block)->getId() !== Block::AIR) continue;
                }
                #if (API::hasFlag($flags, API::FLAG_KEEP_AIR)){
                #	if ($level->getBlock($block)->getId() === Block::AIR) continue;
                #}
                if ($level->setBlock($block, $newblock, false, false)) {
                    $blocks[] = $block;
                    $changed++;
                }
            }
            $undoClipboard = new Clipboard();
            $undoClipboard->setData($blocks);
        } catch (\Exception $exception) {
            if (!is_null($session)) $session->getPlayer()->sendMessage(Loader::$prefix . TextFormat::RED . $exception->getMessage());
            return false;
        }
        if (!is_null($session)) {
            $session->addUndo($undoClipboard);
            $session->getPlayer()->sendMessage(Loader::$prefix . TextFormat::GREEN . "Fill succeed, took " . round((microtime(TRUE) - $time), 2) . "s, " . $changed . " blocks out of " . $selection->getTotalCount() . " changed.");
        } else {
            Server::getInstance()->getLogger()->debug(Loader::$prefix . TextFormat::GREEN . "Fill succeed, took " . round((microtime(TRUE) - $time), 2) . "s, " . $changed . " blocks out of " . $selection->getTotalCount() . " changed.");
        }
        return true;
    }

    /**
     * @param Selection $selection
     * @param Session $session
     * @param Block[] $newblocks
     * @param array ...$flagarray
     * @throws \Exception
     */
    public static function fillAsync(Selection $selection, Session $session, $newblocks = [], ...$flagarray)
    {
        $flags = self::flagParser($flagarray);
        try {
            Server::getInstance()->getAsyncPool()->submitTask(new AsyncFillTask($session->getPlayer(), $selection->__serialize(), $selection->getTouchedChunks(), $selection->getBlocks($flags), $newblocks, $flags));
        } catch (\Exception $e) {
        }
    }

    /**
     * @param Selection $selection
     * @param Session|null $session
     * @param Block[] $blocks1
     * @param Block[] $blocks2
     * @param array ...$flagarray
     * @return bool
     */
    public static function replace(Selection $selection, ?Session $session, $blocks1 = [], $blocks2 = [], ...$flagarray)
    {
        $flags = self::flagParser($flagarray);
        $changed = 0;
        $time = microtime(TRUE);
        try {
            $blocks = [];
            /** @var Block $block */
            foreach ($selection->getBlocks($flags, ...$blocks1) as $block) {
                $level = $selection->getLevel() ?? (!is_null($session) ? $session->getPlayer()->getLevel() : $block->getLevel());
                if ($block->y >= Level::Y_MAX || $block->y < 0) continue;
                $newblock = $blocks2[array_rand($blocks2, 1)];
                $newblock->position($block->asPosition());
                if (self::hasFlag($flags, self::FLAG_KEEP_META)) $newblock->setDamage($block->getDamage());
                if ($level->setBlock($block, $newblock, false, false)) {
                    $blocks[] = $block;
                    $changed++;
                }
            }
            $undoClipboard = new Clipboard();
            $undoClipboard->setData($blocks);
        } catch (\Exception $exception) {
            if (!is_null($session)) $session->getPlayer()->sendMessage(Loader::$prefix . TextFormat::RED . $exception->getMessage());
            return false;
        }
        if (!is_null($session)) {
            $session->addUndo($undoClipboard);
            $session->getPlayer()->sendMessage(Loader::$prefix . TextFormat::GREEN . "Replace succeed, took " . round((microtime(TRUE) - $time), 2) . "s, " . $changed . " blocks out of " . $selection->getTotalCount() . " changed.");
        } else {
            Server::getInstance()->getLogger()->debug(Loader::$prefix . TextFormat::GREEN . "Replace succeed, took " . round((microtime(TRUE) - $time), 2) . "s, " . $changed . " blocks out of " . $selection->getTotalCount() . " changed.");
        }
        return true;
    }

    /**
     * @param Selection $selection
     * @param Session $session
     * @param array ...$flagarray
     * @return bool
     */
    public static function copy(Selection $selection, Session $session, ...$flagarray)
    {
        $flags = self::flagParser($flagarray);
        try {
            $clipboard = new Clipboard();
            $clipboard->setData($selection->getBlocksRelative($flags));
            if (self::hasFlag($flags, self::FLAG_UNCENTERED))//TODO relative or not by flags
                $clipboard->setOffset(new Vector3());
            else
                $clipboard->setOffset($selection->getMinVec3()->subtract($session->getPlayer())->floor());//SUBTRACT THE LEAST X Y Z OF SELECTION //TODO check if player less than minvec
            $session->setClipboards([0 => $clipboard]);// TODO Multiple clipboards
        } catch (\Exception $exception) {
            $session->getPlayer()->sendMessage(Loader::$prefix . TextFormat::RED . $exception->getMessage());
            return false;
        }
        $session->getPlayer()->sendMessage(Loader::$prefix . TextFormat::GREEN . "Copied selection to clipboard");
        return true;
    }

    /**
     * @param Clipboard $clipboard
     * @param null|Session $session
     * @param Position $target
     * @param array ...$flagarray
     * @return bool
     */
    public static function paste(Clipboard $clipboard, ?Session $session, Position $target, ...$flagarray)
    {//TODO: maybe clone clipboard
        $flags = self::flagParser($flagarray);
        $changed = 0;
        $time = microtime(TRUE);
        try {
            $blocks = [];
            foreach ($clipboard->getData() as $block1) {
                /** @var Block $block */
                $block = clone $block1;
                if (self::hasFlag($flags, self::FLAG_PASTE_WITHOUT_AIR) && $block->getId() === BlockIds::AIR)
                    continue;
                $blockvec3 = $target->add($block);
                $level = $target->getLevel() ?? $block->getLevel();
                if (!self::hasFlag($flags, self::FLAG_UNCENTERED))
                    $blockvec3 = $blockvec3->add($clipboard->getOffset());
                $oldblock = $level->getBlock($blockvec3->floor());
                if ($level->setBlock($blockvec3->floor(), $block, false, false)) {
                    $blocks[] = $oldblock;
                    $changed++;
                }
            }
            $undoClipboard = new Clipboard();
            $undoClipboard->setData($blocks);
        } catch (\Exception $exception) {
            if (!is_null($session)) $session->getPlayer()->sendMessage(Loader::$prefix . TextFormat::RED . $exception->getMessage());
            return false;
        }
        if (!is_null($session)) {
            $session->addUndo($undoClipboard);
            $session->getPlayer()->sendMessage(Loader::$prefix . TextFormat::GREEN . "Pasted clipboard " . (self::hasFlag($flags, self::FLAG_UNCENTERED) ? "absolute" : "relative") . " to your position, took " . round((microtime(TRUE) - $time), 2) . "s, " . $changed . " blocks changed.");
        } else {
            Server::getInstance()->getLogger()->info(Loader::$prefix . TextFormat::GREEN . "Pasted clipboard " . (self::hasFlag($flags, self::FLAG_UNCENTERED) ? "absolute" : "relative") . " to your position, took " . round((microtime(TRUE) - $time), 2) . "s, " . $changed . " blocks changed.");
        }
        return true;
    }

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
     * /////////////////////////////////////////////////////////////////////
     * This fixes ItemFactory::fromString until pmmp get's its shit together
     * /////////////////////////////////////////////////////////////////////
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
     * Creates a brush at a specific location with the passed settings
     * @param Block $target
     * @param NamedTag $settings
     * @param Session $session
     * @param array[] $flagarray
     * @return bool
     */
    public static function createBrush(Block $target, NamedTag $settings, Session $session, array ...$flagarray)
    {//TODO messages
        $shape = null;
        $lang = Loader::getInstance()->getLanguage();
        if (!$settings instanceof CompoundTag) return false;
        $messages = [];
        $error = false;
        switch ($settings->getString("type", $lang->translateString('ui.brush.select.type.cuboid'))) {
            //TODO use/parse int as type !! IMPORTANT TODO !!
            //TODO use/parse int as type !! IMPORTANT TODO !!
            //TODO use/parse int as type !! IMPORTANT TODO !!
            //TODO use/parse int as type !! IMPORTANT TODO !!
            //TODO use/parse int as type !! IMPORTANT TODO !!
            case $lang->translateString('ui.brush.select.type.cuboid'):
                {
                    $shape = ShapeGenerator::getShape($target->getLevel(), ShapeGenerator::TYPE_CUBOID, self::compoundToArray($settings));
                    $shape->setCenter($target->asVector3());//TODO fix the offset?: if you have a uneven number, the center actually is between 2 blocks
                    return self::fill($shape, $session, self::blockParser($shape->options['blocks'], $messages, $error), ...$flagarray);
                    break;
                }
            case $lang->translateString('ui.brush.select.type.cylinder'):
                {
                    $shape = ShapeGenerator::getShape($target->getLevel(), ShapeGenerator::TYPE_CYLINDER, self::compoundToArray($settings));
                    $shape->setCenter($target->asVector3());//TODO fix the offset?: if you have a uneven number, the center actually is between 2 blocks
                    return self::fill($shape, $session, self::blockParser($shape->options['blocks'], $messages, $error), ...$flagarray);
                    break;
                }
            case $lang->translateString('ui.brush.select.type.sphere'):
                {
                    $shape = ShapeGenerator::getShape($target->getLevel(), ShapeGenerator::TYPE_SPHERE, self::compoundToArray($settings));
                    $shape->setCenter($target->asVector3());//TODO fix the offset?: if you have a uneven number, the center actually is between 2 blocks
                    return self::fill($shape, $session, self::blockParser($shape->options['blocks'], $messages, $error), ...$flagarray);
                    break;
                }
            case $lang->translateString('ui.brush.select.type.clipboard'):
                {
                    $clipboard = $session->getClipboards()[0] ?? null;
                    if (is_null($clipboard)) {
                        $session->getPlayer()->sendMessage(TextFormat::RED . "You have no clipboard - create one first");
                        return false;
                    }
                    return self::paste($clipboard, $session, $target, ...$flagarray);
                    break;
                }
            case null:
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
     * @param array[] $flagarray
     * @return bool
     */
    public static function floodArea(Block $target, NamedTag $settings, Session $session, array ...$flagarray)
    { //TODO
        if (!$settings instanceof CompoundTag) return null;
        $shape = ShapeGenerator::getShape($target->getLevel(), ShapeGenerator::TYPE_FLOOD, self::compoundToArray($settings));
        $shape->setCenter($target->asVector3());//TODO fix the offset?: if you have a uneven number, the center actually is between 2 blocks
        $messages = [];
        $error = false;
        return self::fill($shape, $session, self::blockParser($shape->options['blocks'], $messages, $error), ...$flagarray);
    }

    public static function compoundToArray(CompoundTag $compoundTag)
    {
        $nbt = new LittleEndianNBTStream();
        $nbt->writeTag($compoundTag);
        return $nbt::toArray($compoundTag);
    }

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
     */
    public static function &getSession(Player $player): ?Session
    {
        $session = self::$sessions[$player->getLowerCaseName()] ?? null;
        if (is_null($session)) {
            if ($player->hasPermission("we.session")) {
                $session = API::addSession(new Session($player));
                Loader::getInstance()->getLogger()->debug("Created new session with UUID {" . $session->getUUID() . "} for player {" . $session->getPlayer()->getName() . "}");
            }
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

    public static function undo(Session $session)
    {
        $changed = 0;
        $time = microtime(true);
        $clipboard = $session->getLatestUndo();
        /** @var Clipboard $clipboard */
        if (is_null($clipboard)) {
            $session->getPlayer()->sendMessage(TextFormat::RED . "Nothing to undo");
            return false;
        }
        /** @var Block $block */
        foreach ($clipboard->getData() as $block) {
            $level = $block->getLevel() ?? $session->getPlayer()->getLevel();
            if ($level->setBlock($block, $block, false, false)) $changed++;
        }
        $session->addRedo($clipboard);
        $session->getPlayer()->sendMessage(Loader::$prefix . TextFormat::GREEN . "Undo succeed, took " . round((microtime(TRUE) - $time), 2) . "s, " . $changed . " blocks changed, " . count($session->getUndos()) . " undo actions left");
        return true;
    }

    public static function redo(Session $session)
    {
        $changed = 0;
        $time = microtime(true);
        $clipboard = $session->getLatestRedo();
        /** @var Clipboard $clipboard */
        if (is_null($clipboard)) {
            $session->getPlayer()->sendMessage(TextFormat::RED . "Nothing to redo");
            return false;
        }
        /** @var Block $block */
        foreach ($clipboard->getData() as $block) {
            $level = $block->getLevel() ?? $session->getPlayer()->getLevel();
            if ($level->setBlock($block, $block, false, false)) $changed++;
        }
        $session->addUndo($clipboard);
        $session->getPlayer()->sendMessage(Loader::$prefix . TextFormat::GREEN . "Redo succeed, took " . round((microtime(TRUE) - $time), 2) . "s, " . $changed . " blocks changed, " . count($session->getRedos()) . " redo actions left");
        return true;
    }

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