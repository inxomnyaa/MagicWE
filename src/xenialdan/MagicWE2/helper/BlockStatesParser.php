<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use Closure;
use Exception;
use InvalidArgumentException;
use InvalidStateException;
use JsonException;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockLegacyIds;
use pocketmine\data\bedrock\LegacyBlockIdToStringIdMap;
use pocketmine\item\LegacyStringToItemParser;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\convert\R12ToCurrentBlockMapEntry;
use pocketmine\network\mcpe\protocol\serializer\NetworkNbtSerializer;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\plugin\PluginException;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\Position;
use RuntimeException;
use xenialdan\MagicWE2\exception\InvalidBlockStateException;
use xenialdan\MagicWE2\Loader;
use const pocketmine\RESOURCE_PATH;

final class BlockStatesParser
{
	use SingletonTrait;

	/** @var R12ToCurrentBlockMapEntry[][] *///TODO check type correct? phpstan!
	private static $legacyStateMap;

	/** @var array */
	private static $aliasMap = [];
	/** @var array */
	private static $rotationFlipMap = [];
	/** @var array */
	private static $doorRotationFlipMap = [];

	private function __construct()
	{
		$this->loadRotationAndFlipData(Loader::getRotFlipPath());
		$this->loadDoorRotationAndFlipData(Loader::getDoorRotFlipPath());
		$this->loadLegacyMappings();
	}

	private function loadLegacyMappings(): void
	{
		/** @var R12ToCurrentBlockMapEntry[][] $legacyStateMap */
		self::$legacyStateMap = [];
		$legacyStateMapReader = new PacketSerializer(file_get_contents(RESOURCE_PATH . "vanilla/r12_to_current_block_map.bin"));
		$nbtReader = new NetworkNbtSerializer();
		while (!$legacyStateMapReader->feof()) {
			$id = $legacyStateMapReader->getString();
			$meta = $legacyStateMapReader->getLShort();

			$offset = $legacyStateMapReader->getOffset();
			$state = $nbtReader->read($legacyStateMapReader->getBuffer(), $offset)->mustGetCompoundTag();
			$legacyStateMapReader->setOffset($offset);
			$r12ToCurrentBlockMapEntry = new R12ToCurrentBlockMapEntry($id, $meta, $state);
			self::$legacyStateMap[$id][$meta] = $r12ToCurrentBlockMapEntry;
		}
		ksort(self::$legacyStateMap, SORT_NUMERIC);
	}

	/**
	 * @param string|null $path
	 * @throws JsonException
	 * @throws PluginException
	 */
	protected function loadRotationAndFlipData(?string $path = null): void
	{
		if ($path !== null) {
			$fileGetContents = file_get_contents($path);
			if ($fileGetContents === false) {
				throw new PluginException("rotation_flip_data.json could not be loaded! Rotation and flip support has been disabled!");
			}

			self::$rotationFlipMap = json_decode($fileGetContents, true, 512, JSON_THROW_ON_ERROR);
			var_dump("Successfully loaded rotation_flip_data.json");
		}
	}

	/**
	 * @param string|null $path
	 * @throws JsonException
	 * @throws PluginException
	 */
	protected function loadDoorRotationAndFlipData(?string $path = null): void
	{
		if ($path !== null) {
			$fileGetContents = file_get_contents($path);
			if ($fileGetContents === false) {
				throw new PluginException("door_data.json could not be loaded! Door rotation and flip support has been disabled!");
			}

			self::$doorRotationFlipMap = json_decode($fileGetContents, true, 512, JSON_THROW_ON_ERROR);
			var_dump("Successfully loaded door_data.json");
		}
	}

	/**
	 * @param array $aliasMap
	 */
	public function setAliasMap(array $aliasMap)
	{
		self::$aliasMap = $aliasMap;
	}

	/**
	 * @param Block $block
	 * @return string|null
	 */
	public static function getBlockIdMapName(Block $block): ?string
	{
		return LegacyBlockIdToStringIdMap::getInstance()->legacyToString($block->getId());
	}

	/**
	 * @param string $blockIdentifier
	 * @return CompoundTag|null
	 */
	protected static function getDefaultStates(string $blockIdentifier): CompoundTag
	{
		return self::$legacyStateMap[$blockIdentifier][0]->getBlockState()->getCompoundTag('states');
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
			$pregSplit = preg_split('/,(?![^\[]*])/', trim($query), -1, PREG_SPLIT_NO_EMPTY);
			if (!is_array($pregSplit)) throw new InvalidArgumentException("Regex matching failed");
			foreach ($pregSplit as $b) {
				/** @noinspection SlowArrayOperationsInLoopInspection */
				$blocks = array_merge($blocks, self::fromString($b, false));
			}
			return $blocks;
		}

		$blockData = strtolower(str_replace("minecraft:", "", $query));//TODO try to keep namespace "minecraft:" to support custom blocks
		$re = '/([\w:]+)(?:\[([\w=,]*)\])?/m';
		preg_match_all($re, $blockData, $matches, PREG_SET_ORDER, 0);
		if (!isset($matches[0][1])) {
			throw new InvalidArgumentException("Could not detect block id");
		}

		$block = LegacyStringToItemParser::getInstance()->parse($matches[0][1] ?? $query)->getBlock();
		if (count($matches[0]) < 3) {
			return [$block];
		}
		$selectedBlockName = $matches[0][1];
		$namespacedSelectedBlockName = "minecraft:" . $selectedBlockName;//TODO try to keep namespace "minecraft:" to support custom blocks
		$defaultStatesNamedTag = self::getDefaultStates($namespacedSelectedBlockName);
		if (!$defaultStatesNamedTag instanceof CompoundTag) {
			throw new InvalidArgumentException("Could not find default block states for $namespacedSelectedBlockName");
		}
		$extraData = $matches[0][2] ?? "";
		$statesExploded = explode(",", $extraData);
		$finalStatesList = clone $defaultStatesNamedTag;
		#var_dump($statesExploded, $finalStatesList->toString());
		#$finalStatesList->setName("states");
		$availableAliases = [];//TODO map in init()! No need to recreate every time! EDIT 2k20: uhm what? @ my past self, why can't you explain properly?!
		foreach ($finalStatesList as $stateName => $state) {
			if (array_key_exists($stateName, self::$aliasMap)) {
				foreach (self::$aliasMap[$stateName]["alias"] as $alias) {
					//todo maybe check for duplicated alias here? "block state mapping invalid: duplicated alias detected"
					$availableAliases[$alias] = $stateName;
				}
			}
		}
		foreach ($statesExploded as $stateKeyValuePair) {
			if (strpos($stateKeyValuePair, "=") === false) continue;
			[$stateName, $value] = explode("=", $stateKeyValuePair);
			$value = strtolower(trim($value));
			if ($value === '') {
				throw new InvalidBlockStateException("Empty value for state $stateName");
			}
			//change blockstate alias to blockstate name
			$stateName = $availableAliases[$stateName] ?? $stateName;
			//TODO maybe validate wrong states here? i.e. stone[type=wrongtype] => Exception, "wrongtype" is invalid value
			$tag = $finalStatesList->getTag($stateName);
			if ($tag === null) {
				throw new InvalidBlockStateException("Invalid state $stateName");
			}
			if ($tag instanceof StringTag) {
				$finalStatesList->setString($stateName, $value);
			} else if ($tag instanceof IntTag) {
				$finalStatesList->setInt($stateName, (int)$value);
			} else if ($tag instanceof ByteTag) {
				if ($value === 1 || $value === '1' || $value === 'true') $value = 'true';
				if ($value === 0 || $value === '0' || $value === 'false') $value = 'false';
				if ($value !== "true" && $value !== "false") {
					throw new InvalidBlockStateException("Invalid value $value for blockstate $stateName, must be \"true\" or \"false\"");
				}
				$finalStatesList->setByte($stateName, $value === "true" ? 1 : 0);
			} else {
				throw new InvalidBlockStateException("Unknown tag of type " . get_class($tag) . " detected");
			}
		}
		#var_dump($finalStatesList->toString());
		//print final list
		//TODO remove. This crashes in AsyncTasks and is just for debug
		#Loader::getInstance()->getLogger()->notice(self::printStates(new BlockStatesEntry($namespacedSelectedBlockName,$finalStatesList), false));
		//return found block(s)
		$blocks = [];
		//doors.. special blocks annoying -.- TODO 1.16 apparently fixed this shit! YAY TODO remove
//		$isDoor = strpos($namespacedSelectedBlockName, "_door") !== false;
//		if ($isDoor && $finalStatesList->getByte("upper_block_bit") === 1) {
//			$fromString = LegacyStringToItemParser::getInstance()->parse($selectedBlockName . "_block:8")->getBlock();
//			return [$fromString];
//		}
		#var_dump((string)$finalStatesList);
		foreach (self::$legacyStateMap[$namespacedSelectedBlockName] as $meta => $r12ToCurrentBlockMapEntry) {
			$clonedPrintedCompound = clone $r12ToCurrentBlockMapEntry->getBlockState()->getCompoundTag('states');
			if ($clonedPrintedCompound->equals($finalStatesList)) {
				#Server::getInstance()->getLogger()->notice("FOUND!");
				$block = BlockFactory::getInstance()->get($block->getId(), $meta);
				#var_dump($oldNameAndMeta,$block);
				#var_dump($block, $finalStatesList);
				$blocks[] = $block;
				#Server::getInstance()->getLogger()->debug(TF::GREEN . "Found block: " . TF::GOLD . $block);
				#Server::getInstance()->getLogger()->notice(self::printStates(new BlockStatesEntry($namespacedSelectedBlockName, $clonedPrintedCompound), true));//might cause loop lol
			}
		}
		#if (empty($blocks)) return [Block::get(0)];//no block found //TODO r12 map only has blocks up to id 255. On 4.0.0, return Item::fromString()?
		if (empty($blocks)) throw new InvalidArgumentException("No block $namespacedSelectedBlockName matching $query could be found");//no block found //TODO r12 map only has blocks up to id 255. On 4.0.0, return Item::fromString()?
		if (count($blocks) === 1) return $blocks;
		//"Hack" to get just one block if multiple results have been found. Most times this results in the default one (meta:0)
		$smallestMeta = PHP_INT_MAX;
		$result = null;
		foreach ($blocks as $block) {
			if ($block->getMeta() < $smallestMeta) {
				$smallestMeta = $block->getMeta();
				$result = $block;
			}
		}
		#Loader::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . "Final block: " . TF::AQUA . $result);
		/** @var Block $result */
		return [$result];
	}

	public static function getStateByBlock(Block $block): ?BlockStatesEntry
	{
		$name = self::getBlockIdMapName($block);
		if ($name === null) return null;
		$damage = $block->getMeta();
		$blockStates = clone self::$legacyStateMap[$name][$damage]->getBlockState()->getCompoundTag('states');
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
		$s = [];
		foreach ($printedCompound as $statesTagEntryName => $statesTagEntry) {
			/** @var CompoundTag $defaultStatesNamedTag */
			$defaultStatesNamedTag = self::getDefaultStates($blockIdentifier);
			$namedTag = $defaultStatesNamedTag->getTag($statesTagEntryName);
			if (!$namedTag instanceof ByteTag && !$namedTag instanceof StringTag && !$namedTag instanceof IntTag) {
				continue;
			}
			//skip defaults
			/** @var ByteTag|IntTag|StringTag $namedTag */
			if ($skipDefaults && $namedTag->equals($statesTagEntry)) continue;
			//prepare string
			if ($statesTagEntry instanceof ByteTag) {
				$s[] = TF::RED . $statesTagEntryName . "=" . ($statesTagEntry->getValue() ? TF::GREEN . "true" : TF::RED . "false") . TF::RESET;
			} else if ($statesTagEntry instanceof IntTag) {
				$s[] = TF::BLUE . $statesTagEntryName . "=" . TF::BLUE . $statesTagEntry->getValue() . TF::RESET;
			} else if ($statesTagEntry instanceof StringTag) {
				$s[] = TF::LIGHT_PURPLE . $statesTagEntryName . "=" . TF::LIGHT_PURPLE . $statesTagEntry->getValue() . TF::RESET;
			}
		}
		if (count($s) === 0) {
			#Server::getInstance()->getLogger()->debug($blockIdentifier);
			return $blockIdentifier;
		}

		#Server::getInstance()->getLogger()->debug($blockIdentifier . "[" . implode(",", $s) . "]");
		return $blockIdentifier . "[" . implode(",", $s) . "]";
	}

	/**
	 * Prints all blocknames with states (without default states)
	 * @throws RuntimeException
	 */
	public static function printAllStates(): void
	{
		foreach (self::$legacyStateMap as $name => $v) {
			foreach ($v as $meta => $legacyMapEntry) {
				$currentoldName = $legacyMapEntry->getId();
				$printedCompound = $legacyMapEntry->getBlockState()->getCompoundTag('states');
				$bs = new BlockStatesEntry($currentoldName, $printedCompound);
				try {
					Server::getInstance()->getLogger()->debug(self::printStates($bs, true));
					Server::getInstance()->getLogger()->debug((string)$bs);
				} catch (RuntimeException $e) {
					Server::getInstance()->getLogger()->logException($e);
				}
			}
		}
	}

	public static function runTests(): void
	{
		var_dump("Running tests");
		//testing blockstate parser
		$tests = [
			"minecraft:tnt",
			#"minecraft:wood",
			#"minecraft:log",
			"minecraft:wooden_slab",
			"minecraft:wooden_slab_wrongname",
			"minecraft:wooden_slab[foo=bar]",
			"minecraft:wooden_slab[top_slot_bit=]",
			"minecraft:wooden_slab[top_slot_bit=true]",
			"minecraft:wooden_slab[top_slot_bit=false]",
			"minecraft:wooden_slab[wood_type=oak]",
			#"minecraft:wooden_slab[wood_type=spruce]",
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
			#//alias testing
			"minecraft:wooden_slab[top=true]",
			"minecraft:wooden_slab[top=true,type=spruce]",
			"minecraft:stone[type=granite]",
			"minecraft:bedrock[burn=true]",
			"minecraft:lever[direction=1]",
			"minecraft:wheat[growth=3]",
			"minecraft:stone_button[direction=1,pressed=true]",
			"minecraft:stone_button[direction=0]",
			"minecraft:stone_brick_stairs[direction=0]",
			"minecraft:trapdoor[direction=0,open_bit=true,upside_down_bit=false]",
			"minecraft:birch_door",
			"minecraft:iron_door[direction=1]",
			"minecraft:birch_door[upper_block_bit=true]",
			"minecraft:birch_door[direction=1,door_hinge_bit=false,open_bit=false,upper_block_bit=true]",
			"minecraft:birch_door[door_hinge_bit=false,open_bit=true,upper_block_bit=true]",
			"minecraft:birch_door[direction=3,door_hinge_bit=false,open_bit=true,upper_block_bit=true]",
		];
		foreach ($tests as $test) {
			try {
				Loader::getInstance()->getLogger()->debug(TF::GOLD . "Search query: " . TF::LIGHT_PURPLE . $test);
				foreach (self::fromString($test) as $block) {
					assert($block instanceof Block);
					$blockStatesEntry = self::getStateByBlock($block);
					Server::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . self::printStates($blockStatesEntry, true));
					Server::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . self::printStates($blockStatesEntry, false));
					Server::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . "Final block: " . TF::AQUA . $block);
				}
			} catch (Exception $e) {
				Server::getInstance()->getLogger()->debug($e->getMessage());
				continue;
			}
		}
		return;//TODO
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
			"minecraft:birch_door[direction=3,door_hinge_bit=false,open_bit=true,upper_block_bit=true]",
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
				$block = Block::get(BlockLegacyIds::IRON_DOOR_BLOCK, $i);
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
		$world = $position->getWorld();
		$sorted = [];
		foreach (self::$allStates as $oldNameAndMeta => $printedCompound) {
			$currentoldName = rtrim(preg_replace("/(\d+)/", "", $oldNameAndMeta), ":");
			$bs = new BlockStatesEntry($currentoldName, $printedCompound);
			try {
				#$block = array_values(self::fromString(TF::clean(strval($bs))))[0];
				$block = $bs->toBlock();
				$sorted[($block->getId() << 4) | $block->getMeta()] = $bs;
			} catch (Exception $e) {
				//skip blocks that pm does not know about
				#$world->getServer()->broadcastMessage($e->getMessage());
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
				$block = $blockStatesEntry->toBlock();
				#if($block->getId() !== $id || $block->getMeta() !== $meta) var_dump("error, $id:$meta does not match {$block->getId()}:{$block->getMeta()}");
				#$world->setBlock(new Vector3($pasteX + $x, $pasteY, $pasteZ + $z), $block);
				$world->setBlockAt($pasteX + $x, $pasteY, $pasteZ + $z, $block, false);
			} catch (Exception $e) {
				$i++;
				continue;
			}
			$i++;
		}
		var_dump("DONE");
	}

	private static function doorEquals(int $currentoldDamage, CompoundTag $defaultStatesNamedTag, CompoundTag $clonedPrintedCompound, CompoundTag $finalStatesList): bool
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

	/**
	 * Generates an alias map for blockstates
	 * Only call from main thread!
	 * @throws InvalidStateException
	 * @throws AssumptionFailedError
	 * @internal
	 */
	private static function generateBlockStateAliasMapJson(): void
	{
		Loader::getInstance()->saveResource("blockstate_alias_map.json");
		$config = new Config(Loader::getInstance()->getDataFolder() . "blockstate_alias_map.json");
		$config->setAll([]);
		$config->save();
		foreach (self::$legacyStateMap as $legacyMapEntry) {
			$states = clone $legacyMapEntry->getBlockState()->getCompoundTag('states');
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
		foreach (self::$legacyStateMap as $legacyMapEntry) {
			$states = clone $legacyMapEntry->getBlockState()->getCompoundTag('states');
			foreach ($states as $state) {
				if (!array_key_exists($state->getName(), $all)) {
					$all[$state->getName()] = [];
				}
				if (!in_array($state->getValue(), $all[$state->getName()], true)) {
					$all[$state->getName()][] = $state->getValue();
					if (strpos($state->getName(), "_bit") !== false) {
						var_dump("_bit");
					} else {
						var_dump("no _bit");
					}
				}
			}
		}
		ksort($all);
		$config->setAll($all);
		$config->save();
		unset($config);
	}

	public static function &readAnyValue($object, $property)
	{
		$invoke = Closure::bind(function & () use ($property) {
			return $this->$property;
		}, $object, $object)->__invoke();
		$value = &$invoke;

		return $value;
	}

}