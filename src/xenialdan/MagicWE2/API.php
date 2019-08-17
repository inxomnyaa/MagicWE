<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2;

use pocketmine\block\Block;
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
use pocketmine\plugin\PluginException;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\UUID;
use xenialdan\MagicWE2\clipboard\Clipboard;
use xenialdan\MagicWE2\clipboard\CopyClipboard;
use xenialdan\MagicWE2\exception\CalculationException;
use xenialdan\MagicWE2\exception\LimitExceededException;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\session\Session;
use xenialdan\MagicWE2\session\UserSession;
use xenialdan\MagicWE2\task\action\SetBiomeAction;
use xenialdan\MagicWE2\task\AsyncActionTask;
use xenialdan\MagicWE2\task\AsyncClipboardTask;
use xenialdan\MagicWE2\task\AsyncCopyTask;
use xenialdan\MagicWE2\task\AsyncCountTask;
use xenialdan\MagicWE2\task\AsyncFillTask;
use xenialdan\MagicWE2\task\AsyncReplaceTask;

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

    const TAG_MAGIC_WE = "MagicWE";

    //TODO Split into seperate Class (SessionStorage?)
    /** @var Session[] */
    private static $sessions = [];
    //TODO Split into seperate Class (SchematicStorage?)
    /** @var CopyClipboard[] *///TODO
    private static $schematics = [];

    /**
     * @param Selection $selection
     * @param Session $session
     * @param Block[] $newblocks
     * @param int $flags
     * @return bool
     * @throws LimitExceededException
     */
    public static function fillAsync(Selection $selection, Session $session, $newblocks = [], int $flags = self::FLAG_BASE)
    {
        try {
            $limit = Loader::getInstance()->getConfig()->get("limit", -1);
            if ($selection->getShape()->getTotalCount() > $limit && !$limit === -1) {
                throw new LimitExceededException(Loader::PREFIX . "You are trying to edit too many blocks at once. Reduce the selection or raise the limit");
            }
            if ($session instanceof UserSession) $session->getBossBar()->showTo([$session->getPlayer()]);
            Server::getInstance()->getAsyncPool()->submitTask(new AsyncFillTask($session->getUUID(), $selection, $selection->getShape()->getTouchedChunks($selection->getLevel()), $newblocks, $flags));
        } catch (\Exception $e) {
            Loader::getInstance()->getLogger()->logException($e);
            return false;
        }
        return true;
    }

    /**
     * @param Selection $selection
     * @param Session $session
     * @param Block[] $oldBlocks
     * @param Block[] $newBlocks
     * @param int $flags
     * @return bool
     * @throws LimitExceededException
     */
    public static function replaceAsync(Selection $selection, Session $session, $oldBlocks = [], $newBlocks = [], int $flags = self::FLAG_BASE)
    {
        try {
            $limit = Loader::getInstance()->getConfig()->get("limit", -1);
            if ($selection->getShape()->getTotalCount() > $limit && !$limit === -1) {
                throw new LimitExceededException(Loader::PREFIX . "You are trying to edit too many blocks at once. Reduce the selection or raise the limit");
            }
            if ($session instanceof UserSession) $session->getBossBar()->showTo([$session->getPlayer()]);
            Server::getInstance()->getAsyncPool()->submitTask(new AsyncReplaceTask($session->getUUID(), $selection, $selection->getShape()->getTouchedChunks($selection->getLevel()), $oldBlocks, $newBlocks, $flags));
        } catch (\Exception $e) {
            Loader::getInstance()->getLogger()->logException($e);
            return false;
        }
        return true;
    }

    /**
     * @param Selection $selection
     * @param Session $session
     * @param int $flags
     * @return bool
     * @throws LimitExceededException
     */
    public static function copyAsync(Selection $selection, Session $session, int $flags = self::FLAG_BASE)
    {
        #return false;
        try {
            $limit = Loader::getInstance()->getConfig()->get("limit", -1);
            if ($selection->getShape()->getTotalCount() > $limit && !$limit === -1) {
                throw new LimitExceededException(Loader::PREFIX . "You are trying to edit too many blocks at once. Reduce the selection or raise the limit");
            }
            //TODO check/edit how relative position works
            $offset = new Vector3();
            if (!self::hasFlag($flags, self::FLAG_POSITION_RELATIVE) && $session instanceof UserSession)//TODO relative or not by flags
                $offset = $selection->getShape()->getMinVec3()->subtract($session->getPlayer())->floor();
            if ($session instanceof UserSession) $session->getBossBar()->showTo([$session->getPlayer()]);
            Server::getInstance()->getAsyncPool()->submitTask(new AsyncCopyTask($session->getUUID(), $selection, $offset, $selection->getShape()->getTouchedChunks($selection->getLevel()), $flags));
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
     * @throws LimitExceededException
     */
    public static function pasteAsync(CopyClipboard $clipboard, ?Session $session, Position $target, int $flags = self::FLAG_BASE)
    {
        #return false;
        try {
            $limit = Loader::getInstance()->getConfig()->get("limit", -1);
            if ($clipboard->getTotalCount() > $limit && !$limit === -1) {
                throw new LimitExceededException(Loader::PREFIX . "You are trying to edit too many blocks at once. Reduce the selection or raise the limit");
            }
            $c = $clipboard->getCenter();
            $clipboard->setCenter($target);//TODO check
            if ($session instanceof UserSession) $session->getBossBar()->showTo([$session->getPlayer()]);
            Server::getInstance()->getAsyncPool()->submitTask(new AsyncClipboardTask($session->getUUID(), $clipboard, $clipboard->getTouchedChunks($c), AsyncClipboardTask::TYPE_PASTE, $flags));
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
     * @throws LimitExceededException
     */
    public static function countAsync(Selection $selection, Session $session, array $filterBlocks, int $flags = self::FLAG_BASE)
    {
        try {
            $limit = Loader::getInstance()->getConfig()->get("limit", -1);
            if ($selection->getShape()->getTotalCount() > $limit && !$limit === -1) {
                throw new LimitExceededException(Loader::PREFIX . "You are trying to count too many blocks at once. Reduce the selection or raise the limit");
            }
            Server::getInstance()->getAsyncPool()->submitTask(new AsyncCountTask($session->getUUID(), $selection, $selection->getShape()->getTouchedChunks($selection->getLevel()), $filterBlocks, $flags));
        } catch (\Exception $e) {
            Loader::getInstance()->getLogger()->logException($e);
            return false;
        }
        return true;
    }

    /**
     * @param Selection $selection
     * @param Session $session
     * @param int $biomeId
     * @return bool
     */
    public static function setBiomeAsync(Selection $selection, Session $session, int $biomeId)
    {
        try {
            $limit = Loader::getInstance()->getConfig()->get("limit", -1);
            if ($selection->getShape()->getTotalCount() > $limit && !$limit === -1) {
                throw new LimitExceededException(Loader::PREFIX . "You are trying to edit too many blocks at once. Reduce the selection or raise the limit");
            }
            if ($session instanceof UserSession) $session->getBossBar()->showTo([$session->getPlayer()]);
            Server::getInstance()->getAsyncPool()->submitTask(new AsyncActionTask($session->getUUID(), $selection, new SetBiomeAction($biomeId), $selection->getShape()->getTouchedChunks($selection->getLevel())));
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
     * @throws LimitExceededException
     */
    public static function createBrush(Block $target, NamedTag $settings, Session $session)
    {//TODO messages
        $session->sendMessage(TF::RED . "TEMPORARILY DISABLED!");/*
        $shape = null;
        if (!$settings instanceof CompoundTag) return false;
        $messages = [];
        $error = false;
        switch ($type = $settings->getInt("type", -1)) {
            case ShapeRegistry::TYPE_CUBOID:
            case ShapeRegistry::TYPE_CUBE:
            case ShapeRegistry::TYPE_CYLINDER:
            case ShapeRegistry::TYPE_SPHERE:
                {
                    $shape = ShapeRegistry::getShape($target->getLevel(), $type, self::compoundToArray($settings));
                    $shape->setCenter($target->asVector3());//TODO fix the offset?: if you have a uneven number, the center actually is between 2 blocks
                    return self::fillAsync($shape, $session, self::blockParser($shape->options['blocks'], $messages, $error), $shape->options["flags"]);
                    break;
                }
            case ShapeRegistry::TYPE_CUSTOM://TODO fix/Change to actual shape, flags
                {
                    $clipboard = $session->getCurrentClipboard();
                    if (is_null($clipboard)) {
                        $session->sendMessage(TF::RED . "You have no clipboard - create one first");
                        return false;
                    }
                    return self::pasteAsync($clipboard, $session, $target);//TODO flags & proper brush tool
                    break;
                }
            default:
                {
                    $session->sendMessage("Unknown shape");
                }
        }*/
        return false;
    }

    /**
     * @param Block $target
     * @param NamedTag $settings
     * @param Session $session
     * @param int $flags
     * @return bool
     * @throws LimitExceededException
     */
    public static function floodArea(Block $target, NamedTag $settings, Session $session, int $flags = self::FLAG_BASE)
    { //TODO
        if (!$settings instanceof CompoundTag) return null;
        $session->sendMessage(TF::RED . "TEMPORARILY DISABLED!");
        return false;/*
        $shape = ShapeRegistry::getShape($target->getLevel(), ShapeRegistry::TYPE_FLOOD, self::compoundToArray($settings));
        $shape->setCenter($target->asVector3());//TODO fix the offset?: if you have a uneven number, the center actually is between 2 blocks
        $messages = [];
        $error = false;
        return self::fillAsync($shape, $session, self::blockParser($shape->options['blocks'], $messages, $error), $flags);*/
    }

    /// SESSION RELATED API PART

    public static function addSession(Session $session)
    {
        return (self::$sessions[$session->getUUID()->toString()] = $session);
    }

    public static function destroySession(Session $session)
    {
        unset(self::$sessions[$session->getUUID()->toString()]);
    }

    /**
     * @param Player $player
     * @return UserSession|null
     * @throws \Error
     */
    public static function getSession(Player $player): ?UserSession
    {
        $session = self::findSession($player);
        if (is_null($session)) {
            if ($player->hasPermission("we.session")) {
                $session = new UserSession($player);
                API::addSession($session);
                Loader::getInstance()->getLogger()->debug("Created new session with UUID {" . $session->getUUID() . "} for player {" . $session->getPlayer()->getName() . "}");
                return $session;
            } else {
                $player->sendMessage(Loader::PREFIX . TF::RED . "You do not have the permission \"magicwe.session\"");
            }
        }
        if (!$player->hasPermission("we.session")) {
            if ($session instanceof UserSession)
                self::destroySession($session);
            Loader::getInstance()->getLogger()->info("Player " . $player->getName() . " does not have the permission \"magicwe.session\", but tried to use " . Loader::PREFIX);
        }
        return $session;
    }

    /**
     * @param Player $player
     * @return bool
     */
    public static function hasSession(Player $player): bool
    {
        return self::findSession($player) instanceof UserSession;
    }

    /**
     * @param Player $player
     * @return null|UserSession
     */
    public static function findSession(Player $player): ?UserSession
    {
        $filtered = array_filter(self::$sessions, function (Session $session) use ($player) {
            return $session instanceof UserSession && $session->getPlayer() === $player;
        });
        if (count($filtered) > 1) throw new PluginException("Multiple sessions found for player {$player->getName()}. This should never happen!");
        if (count($filtered) === 1) return array_values($filtered)[0];
        return null;
    }

    /**
     * @param UUID $uuid
     * @return null|Session
     */
    public static function getSessionByUUID(UUID $uuid): ?Session
    {
        return self::$sessions[$uuid->toString()] ?? null;
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
                $messages[] = Loader::PREFIX . TF::RED . "Could not find a block/item with the " . (is_numeric($name) ? "id" : "name") . ": " . $name;
                continue;
            }
            if ($block instanceof UnknownBlock) {
                $messages[] = Loader::PREFIX . TF::GOLD . $block . " is an unknown block";
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
            } else if (defined(Item::class . "::" . strtoupper($b[0]))) {
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
     * Evaluate mathematics in a string
     * https://stackoverflow.com/a/54684348/4532380
     * @param string $str
     * @return float|int
     * @throws CalculationException
     */
    public static function evalAsMath(string $str)
    {
        $error = false;
        $div_mul = false;
        $add_sub = false;
        $result = 0;

        $str = preg_replace('/[^\d\.\+\-\*\/]/i', '', $str);
        $str = rtrim(trim($str, '/*+'), '-');

        if ((strpos($str, '/') !== false || strpos($str, '*') !== false)) {
            $div_mul = true;
            $operators = ['*', '/'];
            while (!$error && $operators) {
                $operator = array_pop($operators);
                while ($operator && strpos($str, $operator) !== false) {
                    if ($error) {
                        break;
                    }
                    $regex = '/([\d\.]+)\\' . $operator . '(\-?[\d\.]+)/';
                    preg_match($regex, $str, $matches);
                    if (isset($matches[1]) && isset($matches[2])) {
                        if ($operator == '+') $result = (float)$matches[1] + (float)$matches[2];
                        if ($operator == '-') $result = (float)$matches[1] - (float)$matches[2];
                        if ($operator == '*') $result = (float)$matches[1] * (float)$matches[2];
                        if ($operator == '/') {
                            if ((float)$matches[2]) {
                                $result = (float)$matches[1] / (float)$matches[2];
                            } else {
                                $error = true;
                            }
                        }
                        $str = preg_replace($regex, $result, $str, 1);
                        $str = str_replace(['++', '--', '-+', '+-'], ['+', '+', '-', '-'], $str);
                    } else {
                        $error = true;
                    }
                }
            }
        }

        if (!$error && (strpos($str, '+') !== false || strpos($str, '-') !== false)) {
            $add_sub = true;
            preg_match_all('/([\d\.]+|[\+\-])/', $str, $matches);
            if (isset($matches[0])) {
                $result = 0;
                $operator = '+';
                $tokens = $matches[0];
                $count = count($tokens);
                for ($i = 0; $i < $count; $i++) {
                    if ($tokens[$i] == '+' || $tokens[$i] == '-') {
                        $operator = $tokens[$i];
                    } else {
                        $result = ($operator == '+') ? ($result + (float)$tokens[$i]) : ($result - (float)$tokens[$i]);
                    }
                }
            }
        }

        if (!$error && !$div_mul && !$add_sub) {
            $result = (float)$str;
        }

        if ($error) throw new CalculationException("Expression contains an error");

        return $result;
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
}