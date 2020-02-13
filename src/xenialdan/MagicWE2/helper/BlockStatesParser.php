<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\nbt\NetworkLittleEndianNBTStream;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\plugin\PluginException;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use RuntimeException;
use xenialdan\MagicWE2\exception\InvalidBlockStateException;
use const pocketmine\RESOURCE_PATH;

class BlockStatesParser
{
    /** @var ListTag|null */
    private static $rootListTag = null;
    /** @var CompoundTag|null */
    private static $defaultStates = null;
    /** @var string */
    private static $regex = "/,(?![^\[]*\])/";

    /**
     * @throws InvalidArgumentException
     * @throws PluginException
     * @throws RuntimeException
     */
    public static function init(): void
    {
        if (self::$defaultStates instanceof CompoundTag && self::$rootListTag instanceof ListTag) return;//Silent return if already initialised
        $contentsStateNBT = file_get_contents(RESOURCE_PATH . '/vanilla/r12_to_current_block_map.nbt');
        if ($contentsStateNBT === false) throw new PluginException("BlockState mapping file (r12_to_current_block_map) could not be loaded!");
        /** @var string $contentsStateNBT */
        /** @var ListTag $namedTag */
        $namedTag = (new NetworkLittleEndianNBTStream())->read($contentsStateNBT);
        self::$rootListTag = $namedTag;
        //Load default states
        self::$defaultStates = new CompoundTag("defaultStates");
        foreach (self::$rootListTag->getAllValues() as $rootCompound) {
            /** @var CompoundTag $rootCompound */
            $oldCompound = $rootCompound->getCompoundTag("old");
            $newCompound = $rootCompound->getCompoundTag("new");
            $states = clone $newCompound->getCompoundTag("states");
            if ($oldCompound->getShort("val") === 0) {
                $states->setName($oldCompound->getString("name"));
                self::$defaultStates->setTag($states);
            }
        }
    }

    /**
     * @param string $query
     * @param bool $multiple
     * @return Block[]
     * @throws InvalidArgumentException
     * @throws InvalidBlockStateException
     * @throws RuntimeException
     */
    public static function fromString(string $query, bool $multiple = false): array
    {
        $blocks = [];
        if ($multiple) {
            $pregSplit = preg_split(self::$regex, trim($query), -1, PREG_SPLIT_NO_EMPTY);
            if (!is_array($pregSplit)) throw new InvalidArgumentException("Regex matching failed");
            foreach ($pregSplit as $b) {
                $blocks = array_merge($blocks, self::fromString($b, false));
            }
            return $blocks;
        } else {
            #Loader::getInstance()->getLogger()->debug(TF::GOLD . "Search query: " . TF::LIGHT_PURPLE . $query);
            $blockData = strtolower(str_replace("minecraft:", "", $query));
            $re = '/([\w:]+)(?:\[([\w=,]*)\])?/m';
            preg_match_all($re, $blockData, $matches, PREG_SET_ORDER, 0);
            $selectedBlockName = "minecraft:" . ($matches[0][1] ?? "air");
            if (count($matches[0]) < 3) {
                /** @var Item $items */
                $items = Item::fromString($query);
                return [$items->getBlock()];
            }
            $defaultStatesNamedTag = self::$defaultStates->getTag($selectedBlockName);
            if (!$defaultStatesNamedTag instanceof CompoundTag) {
                throw new InvalidArgumentException("Could not find default block states for $selectedBlockName");
            }
            $extraData = $matches[0][2] ?? "";
            $explode = explode(",", $extraData);
            $finalStatesList = clone $defaultStatesNamedTag;
            $finalStatesList->setName("states");
            foreach ($explode as $boom) {
                if (strpos($boom, "=") === false) continue;
                [$k, $v] = explode("=", $boom);
                $v = strtolower(trim($v));
                if (empty($v)) {
                    throw new InvalidBlockStateException("Empty value for state $k");
                }
                //TODO add state alias here by mapping alias => blockstate name
                $tag = $finalStatesList->getTag($k);
                if ($tag === null) {
                    throw new InvalidBlockStateException("Invalid state $k");
                }
                if ($tag instanceof StringTag) {
                    $finalStatesList->setString($tag->getName(), $v);
                } else if ($tag instanceof IntTag) {
                    $finalStatesList->setInt($tag->getName(), intval($v));
                } else if ($tag instanceof ByteTag) {
                    if ($v !== "true" && $v !== "false") {
                        throw new InvalidBlockStateException("Invalid value $v for blockstate $k, must be \"true\" or \"false\"");
                    }
                    $val = ($v === "true" ? 1 : 0);
                    $finalStatesList->setByte($tag->getName(), $val);
                } else {
                    throw new InvalidBlockStateException("Unknown tag of type " . get_class($tag) . " detected");
                }
            }
            //print final list
            self::printStates($finalStatesList, self::$defaultStates, $selectedBlockName, false);
            //print found block(s)
            $blocks = [];
            //TODO there must be a more efficient way to do this
            foreach (self::$rootListTag->getAllValues() as $rootCompound) {
                /** @var CompoundTag $rootCompound */
                $oldCompound = $rootCompound->getCompoundTag("old");
                $newCompound = $rootCompound->getCompoundTag("new");
                $states = $newCompound->getCompoundTag("states");
                if (($oldCompound->getString("name") === $selectedBlockName || $newCompound->getString("name") === $selectedBlockName) && $states->equals($finalStatesList)) {
                    /** @var Item $items1 */
                    $items1 = Item::fromString($selectedBlockName . ":" . $oldCompound->getShort("val"));
                    $block = $items1->getBlock();
                    $blocks[] = $block;
                    #Loader::getInstance()->getLogger()->debug(TF::GREEN . "Found block: " . TF::GOLD . $block);
                }
            }
            if (empty($blocks)) return [];//no block found //TODO r12 map only has blocks up to id 255. On 4.0.0, return Item::fromString()?
            //"Hack" to get just one block if multiple results have been found. Most times this results in the default one (meta:0)
            $smallestMeta = PHP_INT_MAX;
            $result = null;
            foreach ($blocks as $block) {
                if ($block->getDamage() < $smallestMeta) {
                    $smallestMeta = $block->getDamage();
                    $result = $block;
                }
            }
            #Loader::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . "Final block: " . TF::AQUA . $result);
            return [$result];
        }
    }

    /**
     * @param CompoundTag $printedCompound
     * @param CompoundTag $defaultStates
     * @param string $blockIdentifier
     * @param bool $skipDefaults
     * @return void
     * @throws RuntimeException
     */
    private static function printStates(CompoundTag $printedCompound, CompoundTag $defaultStates, string $blockIdentifier, bool $skipDefaults): void
    {
        $s = $failed = [];
        foreach ($printedCompound as $statesTagEntry) {
            /** @var CompoundTag $defaultStatesNamedTag */
            $defaultStatesNamedTag = self::$defaultStates->getTag($blockIdentifier);
            $namedTag = $defaultStatesNamedTag->getTag($statesTagEntry->getName());
            if (!$namedTag instanceof ByteTag && !$namedTag instanceof StringTag && !$namedTag instanceof IntTag) {
                continue;
            }
            //skip defaults
            /** @var ByteTag|IntTag|StringTag $namedTag */
            if ($skipDefaults && $namedTag->getValue() === $statesTagEntry->getValue()) continue;
            //prepare string
            if ($statesTagEntry instanceof ByteTag) {
                $s[] = TF::RED . $statesTagEntry->getName() . "=" . ($statesTagEntry->getValue() ? TF::GREEN . "true" : TF::RED . "false") . TF::RESET;
            } else if ($statesTagEntry instanceof IntTag) {
                $s[] = TF::BLUE . $statesTagEntry->getName() . "=" . TF::BLUE . strval($statesTagEntry->getValue()) . TF::RESET;
            } else if ($statesTagEntry instanceof StringTag) {
                $s[] = TF::LIGHT_PURPLE . $statesTagEntry->getName() . "=" . TF::LIGHT_PURPLE . strval($statesTagEntry->getValue()) . TF::RESET;
            }
        }
        if (count($s) === 0) {
            Server::getInstance()->getLogger()->debug($blockIdentifier);
        } else {
            Server::getInstance()->getLogger()->debug($blockIdentifier . "[" . implode(",", $s) . "]");
        }
    }

    /**
     * Prints all blocknames with states (without default states)
     * @throws RuntimeException
     */
    public static function printAllStates(): void
    {
        foreach (self::$rootListTag->getAllValues() as $rootCompound) {
            /** @var CompoundTag $rootCompound */
            $oldCompound = $rootCompound->getCompoundTag("old");
            $newCompound = $rootCompound->getCompoundTag("new");
            $currentoldName = $oldCompound->getString("name");
            $printedCompound = $newCompound->getCompoundTag("states");
            self::printStates($printedCompound, self::$defaultStates, $currentoldName, true);
        }
    }

    public static function runTests(): void
    {
        //testing cases
        $tests = [
            "minecraft:tnt",
            "minecraft:wood",
            "minecraft:log",
            "minecraft:wooden_slab",
            "minecraft:wooden_slab_wrongname",
            "minecraft:wooden_slab[foo=bar]",
            "minecraft:wooden_slab[top_slot_bit=]",
            "minecraft:wooden_slab[top_slot_bit=true]",
            "minecraft:wooden_slab[top_slot_bit=false]",
            "minecraft:wooden_slab[wood_type=oak]",
            "minecraft:wooden_slab[wood_type=spruce]",
            "minecraft:wooden_slab[wood_type=spruce,top_slot_bit=false]",
            "minecraft:wooden_slab[wood_type=spruce,top_slot_bit=true]",
            "minecraft:end_rod[]",
            "minecraft:end_rod[facing_direction=1]",
            "minecraft:end_rod[block_light_level=14]",
            "minecraft:end_rod[block_light_level=13]",
            "minecraft:light_block[block_light_level=14]",
            "minecraft:stone[]",
            "minecraft:stone[stone_type=granite]",
            "minecraft:stone[stone_type=andesite]",
            "minecraft:stone[stone_type=wrongtag]",//seems to just not find a block at all. neat!
        ];
        foreach ($tests as $test) {
            foreach (self::fromString($test) as $block)
                assert($block instanceof Block);
        }
    }

}