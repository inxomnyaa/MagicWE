<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use Exception;
use InvalidArgumentException;
use InvalidStateException;
use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\level\Position;
use pocketmine\nbt\NetworkLittleEndianNBTStream;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\NamedTag;
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
    private static $allStates = null;
    /** @var array */
    private static $aliasMap = [];
    /** @var array */
    private static $rotationFlipMap = [];
    /** @var array */
    private static $doorRotationFlipMap = [];
    /** @var array */
    private static $blockIdMap = [];

    /**
     * @param string|null $rotFlipMapPath
     * @param string|null $doorRotFlipMapPath
     * @throws InvalidArgumentException
     * @throws PluginException
     * @throws RuntimeException
     */
    public static function init(?string $rotFlipMapPath = null, ?string $doorRotFlipMapPath = null): void
    {
        if (self::isInit()) return;//Silent return if already initialised
        $contentsStateNBT = file_get_contents(RESOURCE_PATH . '/vanilla/r12_to_current_block_map.nbt');
        if ($contentsStateNBT === false) throw new PluginException("BlockState mapping file (r12_to_current_block_map) could not be loaded!");
        /** @var string $contentsStateNBT */
        /** @var ListTag $namedTag */
        $namedTag = (new NetworkLittleEndianNBTStream())->read($contentsStateNBT);
        self::$rootListTag = $namedTag;
        //Load all states. Mapping: oldname:meta->states
        self::$allStates = new CompoundTag("allStates");
        foreach (self::$rootListTag->getAllValues() as $rootCompound) {
            /** @var CompoundTag $rootCompound */
            $oldCompound = $rootCompound->getCompoundTag("old");
            $newCompound = $rootCompound->getCompoundTag("new");
            $states = clone $newCompound->getCompoundTag("states");
            $states->setName($oldCompound->getString("name") . ":" . $oldCompound->getShort("val"));
            self::$allStates->setTag($states);
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
                var_dump("Successfully loaded rotation_flip_data.json");
            }
        }
        if ($doorRotFlipMapPath !== null) {
            $fileGetContents = file_get_contents($doorRotFlipMapPath);
            if ($fileGetContents === false) {
                throw new PluginException("door_data.json could not be loaded! Door rotation and flip support has been disabled!");
            } else {
                self::setDoorRotationFlipMap(json_decode($fileGetContents, true));
                var_dump("Successfully loaded door_data.json");
            }
        }
    }

    /**
     * @return bool
     */
    public static function isInit(): bool
    {
        return self::$allStates instanceof CompoundTag && self::$rootListTag instanceof ListTag;
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
     * @return array
     */
    public static function getDoorRotationFlipMap(): array
    {
        return self::$doorRotationFlipMap;
    }

    /**
     * @param array $map
     */
    public static function setDoorRotationFlipMap(array $map): void
    {
        self::$doorRotationFlipMap = $map;
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
        #if (!BlockFactory::isInit()) BlockFactory::init();
        $blocks = [];
        if ($multiple) {
            $pregSplit = preg_split('/,(?![^\[]*\])/', trim($query), -1, PREG_SPLIT_NO_EMPTY);
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
            if (!isset($matches[0][1])) {
                throw new InvalidArgumentException("Could not detect block id");
            }
            if (count($matches[0]) < 3) {
                /** @var Item $items */
                $items = Item::fromString($matches[0][1] ?? $query);
                return [$items->getBlock()];
            }
            $selectedBlockName = "minecraft:" . $matches[0][1];
            #$defaultStatesNamedTag = self::$defaultStates->getTag($selectedBlockName);
            $defaultStatesNamedTag = self::$allStates->getTag($selectedBlockName . ":0");
            if (!$defaultStatesNamedTag instanceof CompoundTag) {
                throw new InvalidArgumentException("Could not find default block states for $selectedBlockName");
            }
            $extraData = $matches[0][2] ?? "";
            $explode = explode(",", $extraData);
            $finalStatesList = clone $defaultStatesNamedTag;
            $finalStatesList->setName("states");
            $availableAliases = [];//TODO map in init()! No need to recreate every time!
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
                    if ($v === 1) $v = "true";
                    if ($v === 0) $v = "false";
                    if ($v !== "true" && $v !== "false") {
                        throw new InvalidBlockStateException("Invalid value $v for blockstate $k, must be \"true\" or \"false\"");
                    }
                    $finalStatesList->setByte($tag->getName(), $v === "true" ? 1 : 0);
                } else {
                    throw new InvalidBlockStateException("Unknown tag of type " . get_class($tag) . " detected");
                }
            }
            //print final list
            //TODO remove. This crashes in AsyncTasks and is just for debug
            #Server::getInstance()->getLogger()->notice(self::printStates(new BlockStatesEntry($selectedBlockName,$finalStatesList), false));
            //return found block(s)
            $blocks = [];
            //doors.. special blocks annoying -.-
            $isDoor = strpos($selectedBlockName, "_door") !== false;
            if ($isDoor && $finalStatesList->getByte("upper_block_bit") === 1) {
                return [ItemBlock::fromString($selectedBlockName . "_block:8")->getBlock()];
            }
            //TODO there must be a more efficient way to do this
            //TODO Testing a new method here, still iterating over all entries, but skipping those with different id
            #var_dump((string)$finalStatesList);
            foreach (self::$allStates as $oldNameAndMeta => $printedCompound) {
                [$mc, $currentoldName, $currentoldDamage] = explode(":", $oldNameAndMeta);//first is minecraft:
                $currentoldName = $mc . ":" . $currentoldName;
                if ($currentoldName !== $selectedBlockName) {//skip wrong blocks
                    continue;
                }
                $currentoldDamage = intval($currentoldDamage);
                if ($currentoldDamage > 15) {
                    $currentoldDamage = $currentoldDamage & 0x0F;
                    #var_dump("META TOO BIG");
                    #continue;
                }
                /** @var CompoundTag $printedCompound */
                $clonedPrintedCompound = clone $printedCompound;
                $clonedPrintedCompound->setName($finalStatesList->getName());
                if ($isDoor) {
                    $currentoldName = str_replace("_door", "_door_block", $currentoldName);
                    $oldNameAndMeta = str_replace("_door:", "_door_block:", $oldNameAndMeta);
                }
                if ($clonedPrintedCompound->equals($finalStatesList) || ($isDoor && self::doorEquals($currentoldDamage, $defaultStatesNamedTag, $clonedPrintedCompound, $finalStatesList))) {
                    #Server::getInstance()->getLogger()->notice("FOUND!");
                    /** @var Item $items1 */
                    $items1 = Item::fromString($oldNameAndMeta);
                    #var_dump($oldNameAndMeta,$items1);
                    $block = $items1->getBlock();
                    var_dump($oldNameAndMeta, $items1, $block, $finalStatesList);
                    if ($isDoor) {
                        var_dump(
                            self::printStates(self::getStateByBlock($block), false),
                            #self::printStates(new BlockStatesEntry($currentoldName, $finalStatesList, $block), false)
                        );
                    }
                    $blocks[] = $block;
                    #Server::getInstance()->getLogger()->debug(TF::GREEN . "Found block: " . TF::GOLD . $block);
                    #Server::getInstance()->getLogger()->notice(self::printStates(new BlockStatesEntry($selectedBlockName, $clonedPrintedCompound), true));//might cause loop lol
                }
            }
            #if (empty($blocks)) return [Block::get(0)];//no block found //TODO r12 map only has blocks up to id 255. On 4.0.0, return Item::fromString()?
            if (empty($blocks)) throw new InvalidArgumentException("No block $selectedBlockName matching $query could be found");//no block found //TODO r12 map only has blocks up to id 255. On 4.0.0, return Item::fromString()?
            if (count($blocks) === 1) return $blocks;
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
        $damage = $block->getDamage();
        $blockStates = clone self::$allStates->getCompoundTag($name . ":" . $damage);
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
            $defaultStatesNamedTag = self::$allStates->getTag($blockIdentifier . ":0");
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
        //testing blockstate parser
        $tests = [
            #"minecraft:tnt",
            #"minecraft:wood",
            #"minecraft:log",
            #"minecraft:wooden_slab",
            #"minecraft:wooden_slab_wrongname",
            #"minecraft:wooden_slab[foo=bar]",
            #"minecraft:wooden_slab[top_slot_bit=]",
            #"minecraft:wooden_slab[top_slot_bit=true]",
            #"minecraft:wooden_slab[top_slot_bit=false]",
            #"minecraft:wooden_slab[wood_type=oak]",
            #"minecraft:wooden_slab[wood_type=spruce]",
            #"minecraft:wooden_slab[wood_type=spruce,top_slot_bit=false]",
            #"minecraft:wooden_slab[wood_type=spruce,top_slot_bit=true]",
            #"minecraft:end_rod[]",
            #"minecraft:end_rod[facing_direction=1]",
            #"minecraft:end_rod[block_light_level=14]",
            #"minecraft:end_rod[block_light_level=13]",
            #"minecraft:light_block[block_light_level=14]",
            #"minecraft:stone[]",
            #"minecraft:stone[stone_type=granite]",
            #"minecraft:stone[stone_type=andesite]",
            #"minecraft:stone[stone_type=wrongtag]",//seems to just not find a block at all. neat!
            #//alias testing
            #"minecraft:wooden_slab[top=true]",
            #"minecraft:wooden_slab[top=true,type=spruce]",
            #"minecraft:stone[type=granite]",
            #"minecraft:bedrock[burn=true]",
            #"minecraft:lever[direction=1]",
            #"minecraft:wheat[growth=3]",
            #"minecraft:stone_button[direction=1,pressed=true]",
            #"minecraft:stone_button[direction=0]",
            #"minecraft:stone_brick_stairs[direction=0]",
            #"minecraft:trapdoor[direction=0,open_bit=true,upside_down_bit=false]",
            "minecraft:birch_door",
            #"minecraft:iron_door[direction=1]",
            "minecraft:birch_door[upper_block_bit=true]",
            "minecraft:birch_door[direction=1,door_hinge_bit=false,open_bit=false,upper_block_bit=true]",
            #"minecraft:birch_door[door_hinge_bit=false,open_bit=true,upper_block_bit=true]",
            #"minecraft:birch_door[direction=3,door_hinge_bit=false,open_bit=true,upper_block_bit=true]",
        ];
        foreach ($tests as $test) {
            try {
                Server::getInstance()->getLogger()->debug(TF::GOLD . "Search query: " . TF::LIGHT_PURPLE . $test);
                foreach (self::fromString($test) as $block) {
                    assert($block instanceof Block);
                    #Server::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . self::printStates(self::getStateByBlock($block), true));
                    Server::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . self::printStates(self::getStateByBlock($block), false));
                    Server::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . "Final block: " . TF::AQUA . $block);
                }
            } catch (Exception $e) {
                Server::getInstance()->getLogger()->debug($e->getMessage());
                continue;
            }
        }
        //test flip+rotation
        $tests2 = [
            #"minecraft:wooden_slab[wood_type=oak]",
            #"minecraft:wooden_slab[wood_type=spruce,top_slot_bit=true]",
            #"minecraft:end_rod[]",
            #"minecraft:end_rod[facing_direction=1]",
            #"minecraft:end_rod[facing_direction=2]",
            #"minecraft:stone_brick_stairs[direction=0]",
            #"minecraft:stone_brick_stairs[direction=1]",
            #"minecraft:stone_brick_stairs[direction=1,upside_down_bit=true]",
            #"stone_brick_stairs[direction=1,upside_down_bit=true]",
            #"minecraft:ladder[facing_direction=3]",
            #"minecraft:magenta_glazed_terracotta[facing_direction=2]",
            #"minecraft:trapdoor[direction=3,open_bit=true,upside_down_bit=false]",
            #"minecraft:birch_door",
            #"minecraft:birch_door[direction=1]",
            #"minecraft:birch_door[direction=1,door_hinge_bit=false,open_bit=false,upper_block_bit=true]",
            #"minecraft:birch_door[door_hinge_bit=false,open_bit=true,upper_block_bit=true]",
            #"minecraft:birch_door[direction=3,door_hinge_bit=false,open_bit=true,upper_block_bit=true]",
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
        //test doors because WTF they are weird
        try {
            for ($i = 0; $i < 15; $i++) {
                $block = Block::get(Block::IRON_DOOR_BLOCK, $i);
                Server::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . $block);
                $entry = self::getStateByBlock($block);
                Server::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . $entry);
                Server::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . $entry->blockStates);
                Server::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . self::printStates($entry, false));
            }
        } catch (Exception $e) {
            Server::getInstance()->getLogger()->debug($e->getMessage());
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
                $block = $bs->toBlock();
                $sorted[($block->getId() << 4) | $block->getDamage()] = $bs;
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

    private static function doorEquals(int $currentoldDamage, CompoundTag $defaultStatesNamedTag, CompoundTag $clonedPrintedCompound, NamedTag $finalStatesList): bool
    {
        if (
            /*(
                $isUp &&
                $currentoldDamage === 8 &&
                $finalStatesList->getByte("door_hinge_bit") === $defaultStatesNamedTag->getByte("door_hinge_bit") &&
                $finalStatesList->getByte("open_bit") === $defaultStatesNamedTag->getByte("open_bit") &&
                $finalStatesList->getInt("direction") === $defaultStatesNamedTag->getInt("direction")
            )
            xor*/
        (
            #$finalStatesList->getByte("door_hinge_bit") === $clonedPrintedCompound->getByte("door_hinge_bit") &&
            $finalStatesList->getByte("open_bit") === $clonedPrintedCompound->getByte("open_bit") &&
            $finalStatesList->getInt("direction") === $clonedPrintedCompound->getInt("direction")
        )
        ) return true;
        return false;
    }

}