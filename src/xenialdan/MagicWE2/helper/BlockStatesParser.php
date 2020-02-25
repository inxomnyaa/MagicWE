<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use Exception;
use InvalidArgumentException;
use InvalidStateException;
use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\nbt\NetworkLittleEndianNBTStream;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\plugin\PluginException;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;
use RuntimeException;
use xenialdan\MagicWE2\exception\InvalidBlockStateException;
use xenialdan\MagicWE2\Loader;
use const pocketmine\RESOURCE_PATH;

class BlockStatesParser
{
    /** @var ListTag|null */
    private static $rootListTag = null;
    /** @var CompoundTag|null */
    private static $defaultStates = null;
    /** @var CompoundTag|null */
    private static $allStates = null;
    /** @var string */
    private static $regex = "/,(?![^\[]*\])/";
    /** @var array */
    private static $aliasMap = [];
    /** @var array */
    private static $rotationFlipMap = [];
    /** @var array */
    private static $blockIdMap = [];

    /**
     * @param string|null $rotFlipMapPath
     * @throws InvalidArgumentException
     * @throws PluginException
     * @throws RuntimeException
     */
    public static function init(?string $rotFlipMapPath): void
    {
        if (self::isInit()) return;//Silent return if already initialised
        $contentsStateNBT = file_get_contents(RESOURCE_PATH . '/vanilla/r12_to_current_block_map.nbt');
        if ($contentsStateNBT === false) throw new PluginException("BlockState mapping file (r12_to_current_block_map) could not be loaded!");
        /** @var string $contentsStateNBT */
        /** @var ListTag $namedTag */
        $namedTag = (new NetworkLittleEndianNBTStream())->read($contentsStateNBT);
        self::$rootListTag = $namedTag;
        //Load default states
        self::$defaultStates = new CompoundTag("defaultStates");
        //and all states. Mapping: oldname:meta->states
        self::$allStates = new CompoundTag("allStates");
        foreach (self::$rootListTag->getAllValues() as $rootCompound) {
            /** @var CompoundTag $rootCompound */
            $oldCompound = $rootCompound->getCompoundTag("old");
            $newCompound = $rootCompound->getCompoundTag("new");
            $states = clone $newCompound->getCompoundTag("states");
            if ($oldCompound->getShort("val") === 0) {
                $states->setName($oldCompound->getString("name"));
                self::$defaultStates->setTag($states);
            }
            $s2 = clone $newCompound->getCompoundTag("states");
            $s2->setName($oldCompound->getString("name") . ":" . $oldCompound->getShort("val"));
            self::$allStates->setTag($s2);
        }
        $blockIdMap = file_get_contents(RESOURCE_PATH . '/vanilla/block_id_map.json');
        if ($blockIdMap === false) throw new PluginException("Block id mapping file (block_id_map) could not be loaded!");
        self::$blockIdMap = json_decode($blockIdMap, true);
        //self::runTests();
        if ($rotFlipMapPath !== null) {
            $fileGetContents = file_get_contents($rotFlipMapPath);
            if ($fileGetContents === false) {
                throw new PluginException("rotation_flip_data.json could not be loaded! Rotation and flip support has been disabled!");
            } else {
                self::setRotationFlipMap(json_decode($fileGetContents, true));
                var_dump("Successfully loaded");
            }
        }
    }

    /**
     * @return bool
     */
    public static function isInit(): bool
    {
        return self::$defaultStates instanceof CompoundTag && self::$rootListTag instanceof ListTag;
    }

    /**
     * @param Block $block
     * @return string|null
     */
    public static function getBlockIdMapName(Block $block)
    {
        return array_flip(self::$blockIdMap)[$block->getId()] ?? null;
    }

    /**
     * @param array $aliasMap
     */
    public static function setAliasMap(array $aliasMap): void
    {
        self::$aliasMap = $aliasMap;
    }

    /**
     * @return array
     */
    public static function getRotationFlipMap(): array
    {
        return self::$rotationFlipMap;
    }

    /**
     * @param array $map
     */
    public static function setRotationFlipMap(array $map): void
    {
        self::$rotationFlipMap = $map;
    }

    /**
     * Generates an alias map for blockstates
     * Only call from main thread!
     * @throws InvalidStateException
     * @internal
     */
    private static function generateBlockStateAliasMapJson(): void
    {
        Loader::getInstance()->saveResource("blockstate_alias_map.json");
        $config = new Config(Loader::getInstance()->getDataFolder() . "blockstate_alias_map.json");
        $config->setAll([]);
        $config->save();
        foreach (self::$rootListTag->getAllValues() as $rootCompound) {
            /** @var CompoundTag $rootCompound */
            $newCompound = $rootCompound->getCompoundTag("new");
            $states = clone $newCompound->getCompoundTag("states");
            foreach ($states as $state) {
                if (!$config->exists($state->getName())) {
                    $alias = $state->getName();
                    $fullReplace = [
                        "top" => "top",
                        "type" => "type",
                        "_age" => "age",
                        "age_" => "age",
                        "directions" => "vine_b",//hack for vine_directions => directions
                        "direction" => "direction",
                        "vine_b" => "directions",//hack for vine_directions => directions
                        "axis" => "axis",
                        "delay" => "delay",
                        "bite_counter" => "bites",
                        "count" => "count",
                        "pressed" => "pressed",
                        "upper_block" => "top",
                        "data" => "data",
                        "extinguished" => "off",
                        "color" => "color",
                        "block_light" => "light",
                        #"_lit"=>"lit",
                        #"lit_"=>"lit",
                        "liquid_depth" => "depth",
                        "upside_down" => "flipped",
                        "infiniburn" => "burn",
                    ];
                    $partReplace = [
                        "_bit",
                        "piece",
                        "output_",
                        "level",
                        "amount",
                        "cauldron",
                        "allow",
                        "state",
                        "door",
                        "redstone",
                        "bamboo",
                        #"head",
                        "brewing_stand",
                        "item_frame",
                        "mushrooms",
                        "composter",
                        "coral",
                        "_2",
                        "_3",
                        "_4",
                        "end_portal",
                    ];
                    foreach ($fullReplace as $stateAlias => $setTo)
                        if (strpos($alias, $stateAlias) !== false) {
                            $alias = $setTo;
                        }
                    foreach ($partReplace as $replace)
                        $alias = trim(trim(str_replace($replace, "", $alias), "_"));
                    $config->set($state->getName(), [
                        "alias" => [$alias],
                    ]);
                }
            }
        }
        $all = $config->getAll();
        /** @var array<string, mixed> $all */
        ksort($all);
        $config->setAll($all);
        $config->save();
        unset($config);
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
            $availableAliases = [];//TODO map in init() if too slow
            foreach ($finalStatesList as $state) {
                if (array_key_exists($state->getName(), self::$aliasMap)) {
                    foreach (self::$aliasMap[$state->getName()]["alias"] as $alias) {
                        //todo maybe check for duplicated alias here? "block state mapping invalid: duplicated alias detected"
                        $availableAliases[$alias] = $state->getName();
                    }
                }
            }
            foreach ($explode as $boom) {
                if (strpos($boom, "=") === false) continue;
                [$k, $v] = explode("=", $boom);
                $v = strtolower(trim($v));
                if (strlen($v) < 1) {
                    throw new InvalidBlockStateException("Empty value for state $k");
                }
                //change blockstate alias to blockstate name
                $k = $availableAliases[$k] ?? $k;
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
            //TODO remove. This crashes in AsyncTasks and is just for debug
            #Server::getInstance()->getLogger()->debug(self::printStates($finalStatesList, $selectedBlockName, false));
            //return found block(s)
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
            if (empty($blocks)) return [Block::get(0)];//no block found //TODO r12 map only has blocks up to id 255. On 4.0.0, return Item::fromString()?
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

    public static function getStateByBlock(Block $block): ?BlockStatesEntry
    {
        $name = self::getBlockIdMapName($block);
        if ($name === null) return null;
        /** @var string $name */
        $blockStates = self::$allStates->getCompoundTag($name . ":" . $block->getDamage());
        if ($blockStates === null) return null;
        return new BlockStatesEntry($name, $blockStates, $block);
    }

    /**
     * @param BlockStatesEntry $entry
     * @param bool $skipDefaults
     * @return string
     * @throws RuntimeException
     */
    public static function printStates(BlockStatesEntry $entry, bool $skipDefaults): string
    {
        $printedCompound = $entry->blockStates;
        $blockIdentifier = $entry->blockIdentifier;
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
            #Server::getInstance()->getLogger()->debug($blockIdentifier);
            return $blockIdentifier;
        } else {
            #Server::getInstance()->getLogger()->debug($blockIdentifier . "[" . implode(",", $s) . "]");
            return $blockIdentifier . "[" . implode(",", $s) . "]";
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
            $bs = new BlockStatesEntry($currentoldName, $printedCompound);
            Server::getInstance()->getLogger()->debug(self::printStates($bs, true));
            try {
                Server::getInstance()->getLogger()->debug(strval($bs));
            } catch (InvalidBlockStateException $e) {
            } catch (InvalidArgumentException $e) {
            } catch (RuntimeException $e) {
                Server::getInstance()->getLogger()->logException($e);
            }
        }
    }

    /**
     * Generates an alias map for blockstates
     * Only call from main thread!
     * @throws InvalidStateException
     * @internal
     */
    public static function generatePossibleStatesJson(): void
    {
        $config = new Config(Loader::getInstance()->getDataFolder() . "possible_blockstates.json");
        $config->setAll([]);
        $config->save();
        $all = [];
        foreach (self::$rootListTag->getAllValues() as $rootCompound) {
            /** @var CompoundTag $rootCompound */
            $newCompound = $rootCompound->getCompoundTag("new");
            $states = clone $newCompound->getCompoundTag("states");
            foreach ($states as $state) {
                if (!array_key_exists($state->getName(), $all)) {
                    $all[$state->getName()] = [];
                }
                if (!in_array($state->getValue(), $all[$state->getName()])) {
                    $all[$state->getName()][] = $state->getValue();
                    if (strpos($state->getName(), "_bit") !== false) {
                    } else {
                    }
                }
            }
        }
        /** @var array<string, mixed> $all */
        ksort($all);
        $config->setAll($all);
        $config->save();
        unset($config);
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
            //alias testing
            "minecraft:wooden_slab[top=true]",
            "minecraft:wooden_slab[top=true,type=spruce]",
            "minecraft:stone[type=granite]",
            "minecraft:bedrock[burn=true]",
            "minecraft:lever[direction=1]",
            "minecraft:wheat[growth=3]",
            "minecraft:stone_button[direction=1,pressed=true]",
            "minecraft:stone_button[direction=0]",
            "minecraft:stone_brick_stairs[direction=0]",
        ];
        foreach ($tests as $test) {
            try {
                Server::getInstance()->getLogger()->debug(TF::GOLD . "Search query: " . TF::LIGHT_PURPLE . $test);
                foreach (self::fromString($test) as $block) {
                    assert($block instanceof Block);
                    Server::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . "Final block: " . TF::AQUA . $block);
                }
            } catch (Exception $e) {
                Server::getInstance()->getLogger()->debug($e->getMessage());
                continue;
            }
        }
        $tests2 = [
            "minecraft:wooden_slab[wood_type=oak]",
            "minecraft:wooden_slab[wood_type=spruce,top_slot_bit=true]",
            "minecraft:end_rod[]",
            "minecraft:end_rod[facing_direction=1]",
            "minecraft:end_rod[facing_direction=2]",
            "minecraft:stone_brick_stairs[direction=0]",
            "minecraft:stone_brick_stairs[direction=1]",
            "minecraft:stone_brick_stairs[direction=1,upside_down_bit=true]",
            "stone_brick_stairs[direction=1,upside_down_bit=true]",
            "minecraft:ladder[facing_direction=3]",
            "minecraft:magenta_glazed_terracotta[facing_direction=2]",
            "minecraft:trapdoor[direction=3,open_bit=true,upside_down_bit=false]"
        ];
        foreach ($tests2 as $test) {
            try {
                Server::getInstance()->getLogger()->debug(TF::GOLD . "Rotation query: " . TF::LIGHT_PURPLE . $test);
                $block = self::fromString($test)[0];
                Server::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . "From block: " . TF::AQUA . $block);
                $state = self::getStateByBlock($block)->rotate(90);
                assert($state->toBlock() instanceof Block);
                Server::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . "Rotated block: " . TF::AQUA . $state->toBlock());

                Server::getInstance()->getLogger()->debug(TF::GOLD . "Mirror query x: " . TF::LIGHT_PURPLE . $test);
                $state = self::getStateByBlock($block)->mirror("x");
                assert($state->toBlock() instanceof Block);
                Server::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . "Flipped block x: " . TF::AQUA . $state->toBlock());

                Server::getInstance()->getLogger()->debug(TF::GOLD . "Mirror query y: " . TF::LIGHT_PURPLE . $test);
                $state = self::getStateByBlock($block)->mirror("y");
                assert($state->toBlock() instanceof Block);
                Server::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . "Flipped block y: " . TF::AQUA . $state->toBlock());
            } catch (Exception $e) {
                Server::getInstance()->getLogger()->debug($e->getMessage());
                continue;
            }
        }
    }

    public static function placeAllBlockstates(Position $position): void
    {
        $pasteY = $position->getFloorY();
        $pasteX = $position->getFloorX();
        $pasteZ = $position->getFloorZ();
        $level = $position->getLevel();
        /** @var array<int,Block> $sorted */
        $sorted = [];
        foreach (self::$allStates as $oldNameAndMeta => $printedCompound) {
            $currentoldName = rtrim(preg_replace("/([0-9]+)/", "", $oldNameAndMeta), ":");
            $bs = new BlockStatesEntry($currentoldName, $printedCompound);
            try {
                /** @var Block $block */
                #$block = array_values(self::fromString(TF::clean(strval($bs))))[0];
                $sorted[($bs->toBlock()->getId() << 4) | $bs->toBlock()->getDamage()] = $bs;
            } catch (Exception $e) {
                //skip blocks that pm does not know about
                #$level->getServer()->broadcastMessage($e->getMessage());
            }
        }
        ksort($sorted);
        $i = 0;
        $limit = 50;
        foreach ($sorted as $blockStatesEntry) {
            /** @var BlockStatesEntry $blockStatesEntry */
            $x = ($i % $limit) * 2;
            $z = ($i - ($i % $limit)) / $limit * 2;
            try {
                /** @var Block $block */
                $block = $blockStatesEntry->toBlock();
                #if($block->getId() !== $id || $block->getDamage() !== $meta) var_dump("error, $id:$meta does not match {$block->getId()}:{$block->getDamage()}");
                #$level->setBlock(new Vector3($pasteX + $x, $pasteY, $pasteZ + $z), $block);
                $level->setBlockIdAt($pasteX + $x, $pasteY, $pasteZ + $z, $block->getId());
                $level->setBlockDataAt($pasteX + $x, $pasteY, $pasteZ + $z, $block->getDamage());
            } catch (Exception $e) {
                $i++;
                continue;
            }
            $i++;
        }
        var_dump("DONE");
    }

}