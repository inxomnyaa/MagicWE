<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use Closure;
use Exception;
use GlobalLogger;
use InvalidArgumentException;
use InvalidStateException;
use JsonException;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\Door;
use pocketmine\block\utils\BlockDataSerializer;
use pocketmine\data\bedrock\LegacyBlockIdToStringIdMap;
use pocketmine\item\LegacyStringToItemParser;
use pocketmine\math\Facing;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\UnexpectedTagTypeException;
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

	/** @var string */
	public static $rotPath;
	/** @var string */
	public static $doorRotPath;

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
//		$this->loadRotationAndFlipData(Loader::getRotFlipPath());
//		$this->loadDoorRotationAndFlipData(Loader::getDoorRotFlipPath());
		$this->loadRotationAndFlipData(self::$rotPath);
		$this->loadDoorRotationAndFlipData(self::$doorRotPath);
		$this->loadLegacyMappings();
	}

	private function loadLegacyMappings(): void
	{
		/** @var R12ToCurrentBlockMapEntry[][] $legacyStateMap */
		self::$legacyStateMap = [];
		$contents = file_get_contents(RESOURCE_PATH . "vanilla/r12_to_current_block_map.bin");
		if ($contents === false) throw new PluginException("Can not get contents of r12_to_current_block_map");
		$legacyStateMapReader = new PacketSerializer($contents);
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
			GlobalLogger::get()->debug("Successfully loaded rotation_flip_data.json");
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
			GlobalLogger::get()->debug("Successfully loaded door_data.json");
		}
	}

	/**
	 * @return array
	 */
	public static function getRotationFlipMap(): array
	{
		return self::$rotationFlipMap;
	}

	/**
	 * @return array
	 */
	public static function getDoorRotationFlipMap(): array
	{
		return self::$doorRotationFlipMap;
	}

	/**
	 * @param BlockQuery $query
	 * @param CompoundTag $states
	 * @return Door
	 * @throws InvalidArgumentException
	 * @throws \pocketmine\block\utils\InvalidBlockStateException
	 */
	private static function buildDoor(BlockQuery $query, CompoundTag $states): Door
	{
		$query = clone $query;//TODO test, i don't want the original $query to be modified
		$query->fullExtraQuery = null;
		$query->fullBlockQuery = $query->blockId;
		$query->blockStatesQuery = null;
		/** @var Door $door */
		$door = self::fromString($query);
		$door->setOpen($states->getByte("open_bit") === 1);
		$door->setTop($states->getByte("upper_block_bit") === 1);
		$door->setHingeRight($states->getByte("door_hinge_bit") === 1);
		$direction = $states->getInt("direction");
		$door->setFacing(Facing::rotateY(BlockDataSerializer::readLegacyHorizontalFacing($direction & 0x03), false));
		return $door;
	}

	/**
	 * @param array $aliasMap
	 */
	public function setAliasMap(array $aliasMap): void
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
	 * @return CompoundTag
	 */
	protected static function getDefaultStates(string $blockIdentifier): CompoundTag
	{
		return self::$legacyStateMap[$blockIdentifier][0]->getBlockState()->getCompoundTag('states') ?? new CompoundTag();
	}

	/**
	 * Parses a BlockQuery (acquired using BlockPalette::fromString()) to a block and sets the BlockQuery's blockFullId
	 * @param BlockQuery $query
	 * @return Block
	 * @throws InvalidArgumentException
	 */
	public static function fromString(BlockQuery $query): Block
	{
		$selectedBlockName = strtolower(str_replace("minecraft:", "", $query->blockId));//TODO try to keep namespace "minecraft:" to support custom blocks

		$namespacedSelectedBlockName = strpos($query->blockId, "minecraft:") === false ? "minecraft:" . $selectedBlockName : $selectedBlockName;
		/** @var LegacyStringToItemParser $legacyStringToItemParser */
		$legacyStringToItemParser = LegacyStringToItemParser::getInstance();
		$block = $legacyStringToItemParser->parse($selectedBlockName)->getBlock();
		//no states, just block
		if (!$query->hasBlockStates()) {
			$query->blockFullId = $block->getFullId();
			return $block;
		}

		$defaultStatesNamedTag = self::getDefaultStates($namespacedSelectedBlockName);
		if (!$defaultStatesNamedTag instanceof CompoundTag) {
			throw new InvalidArgumentException("Could not find default block states for $namespacedSelectedBlockName");
		}
		$blockStatesQuery = $query->blockStatesQuery ?? "";
		$statesExploded = explode(",", $blockStatesQuery);
		$finalStatesList = clone $defaultStatesNamedTag;

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
			$value = strtolower(trim((string)$value));
			if ($value === '') {
				throw new InvalidBlockStateException("Empty value for state $stateName");
			}
			//change blockstate alias to blockstate name
			$stateName = $availableAliases[$stateName] ?? $stateName;
			//TODO maybe validate wrong states here? i.e. stone[type=wrongtype] => Exception, "wrongtype" is invalid value
			try {
				$tag = $finalStatesList->getTag($stateName);
			} catch (UnexpectedTagTypeException $e) {
				throw new InvalidBlockStateException("Default states for block '$query->blockId' do not contain Tag with name '$stateName'");
			}
			if ($tag === null) {
				throw new InvalidBlockStateException("Invalid state $stateName");
			}
			if ($tag instanceof StringTag) {
				$finalStatesList->setString($stateName, $value);
			} else if ($tag instanceof IntTag) {
				$finalStatesList->setInt($stateName, (int)$value);
			} else if ($tag instanceof ByteTag) {
				if ($value === '1' || $value === 'true') $value = 'true';
				if ($value === '0' || $value === 'false') $value = 'false';
				if ($value !== "true" && $value !== "false") {
					throw new InvalidBlockStateException("Invalid value $value for blockstate $stateName, must be \"true\" or \"false\"");
				}
				$finalStatesList->setByte($stateName, $value === "true" ? 1 : 0);
			} else {
				throw new InvalidBlockStateException("Unknown tag of type " . get_class($tag) . " detected");
			}
		}
		//return found block(s)
		//doors.. special blocks annoying -.-
		if (strpos($query->blockId, "_door") !== false) {
			$block = self::buildDoor($query, $finalStatesList);
			$query->blockFullId = $block->getFullId();
			return $block;
		}
		/** @var Block[] $blocks */
		$blocks = [];
		foreach (self::$legacyStateMap[$namespacedSelectedBlockName] as $meta => $r12ToCurrentBlockMapEntry) {
			$clonedPrintedCompound = clone $r12ToCurrentBlockMapEntry->getBlockState()->getCompoundTag('states');
			if ($clonedPrintedCompound->equals($finalStatesList)) {
				/** @var BlockFactory $blockFactory */
				$blockFactory = BlockFactory::getInstance();
				$block = $blockFactory->get($block->getId(), $meta & 0xf);
				$blocks[] = $block;
			}
		}
		if (empty($blocks) && !$block instanceof Block) throw new InvalidArgumentException("No block $namespacedSelectedBlockName matching $query->query could be found");//no block found //TODO r12 map only has blocks up to id 255. On 4.0.0, return Item::fromString()?
		if (count($blocks) === 1) {
			$block = $blocks[0];
			$query->blockFullId = $block->getFullId();
			return $block;
		}
		//"Hack" to get just one block if multiple results have been found. Most times this results in the default one (meta:0)
		$smallestMeta = PHP_INT_MAX;
		foreach ($blocks as $blockFromStates) {
			if ($blockFromStates->getMeta() < $smallestMeta) {
				$smallestMeta = $blockFromStates->getMeta();
				$block = $blockFromStates;
			}
		}
		$query->blockFullId = $block->getFullId();
		return $block;
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

	public static function getStateByCompound(CompoundTag $compoundTag): ?BlockStatesEntry
	{
		$namespacedSelectedBlockName = $compoundTag->getString('name', "");
		if ($namespacedSelectedBlockName === "") return null;
		$states = $compoundTag->getCompoundTag('states') ?? self::getDefaultStates($namespacedSelectedBlockName);
		if (!$states instanceof CompoundTag) {
			throw new InvalidArgumentException("Could not find default block states for $namespacedSelectedBlockName");
		}

		if (strpos($namespacedSelectedBlockName, "_door") !== false) {
			$door = self::buildDoor($namespacedSelectedBlockName, $states);
			//return self::getStateByBlock($door);
			return new BlockStatesEntry($namespacedSelectedBlockName, $states, $door);
		}

		foreach (self::$legacyStateMap[$namespacedSelectedBlockName] ?? [] as $meta => $r12ToCurrentBlockMapEntry) {//??[] is to avoid crashes on newer blocks like light block
			$clonedPrintedCompound = $r12ToCurrentBlockMapEntry->getBlockState()->getCompoundTag('states');
			if ($clonedPrintedCompound->equals($states)) {
				#Server::getInstance()->getLogger()->notice(self::printStates(new BlockStatesEntry($namespacedSelectedBlockName, $clonedPrintedCompound), true));//might cause loop lol//todo rem
				return new BlockStatesEntry($namespacedSelectedBlockName, $clonedPrintedCompound);
			}
		}
		return null;
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
			"minecraft:campfire",
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
		/** @noinspection PhpUnreachableStatementInspection */
		// $tests2 = [
		// 	#"minecraft:wooden_slab[wood_type=oak]",
		// 	#"minecraft:wooden_slab[wood_type=spruce,top_slot_bit=true]",
		// 	#"minecraft:end_rod[]",
		// 	#"minecraft:end_rod[facing_direction=1]",
		// 	#"minecraft:end_rod[facing_direction=2]",
		// 	#"minecraft:stone_brick_stairs[direction=0]",
		// 	#"minecraft:stone_brick_stairs[direction=1]",
		// 	#"minecraft:stone_brick_stairs[direction=1,upside_down_bit=true]",
		// 	#"stone_brick_stairs[direction=1,upside_down_bit=true]",
		// 	#"minecraft:ladder[facing_direction=3]",
		// 	#"minecraft:magenta_glazed_terracotta[facing_direction=2]",
		// 	#"minecraft:trapdoor[direction=3,open_bit=true,upside_down_bit=false]",
		// 	#"minecraft:birch_door",
		// 	#"minecraft:birch_door[direction=1]",
		// 	#"minecraft:birch_door[direction=1,door_hinge_bit=false,open_bit=false,upper_block_bit=true]",
		// 	#"minecraft:birch_door[door_hinge_bit=false,open_bit=true,upper_block_bit=true]",
		// 	"minecraft:birch_door[direction=3,door_hinge_bit=false,open_bit=true,upper_block_bit=true]",
		// ];
		// foreach ($tests2 as $test) {
		// 	try {
		// 		Server::getInstance()->getLogger()->debug(TF::GOLD . "Rotation query: " . TF::LIGHT_PURPLE . $test);
		// 		$block = self::fromString($test)[0];
		// 		Server::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . "From block: " . TF::AQUA . $block);
		// 		$state = self::getStateByBlock($block)->rotate(90);
		// 		assert($state->toBlock() instanceof Block);
		// 		Server::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . "Rotated block: " . TF::AQUA . $state->toBlock());

		// 		Server::getInstance()->getLogger()->debug(TF::GOLD . "Mirror query x: " . TF::LIGHT_PURPLE . $test);
		// 		$state = self::getStateByBlock($block)->mirror("x");
		// 		assert($state->toBlock() instanceof Block);
		// 		Server::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . "Flipped block x: " . TF::AQUA . $state->toBlock());

		// 		Server::getInstance()->getLogger()->debug(TF::GOLD . "Mirror query y: " . TF::LIGHT_PURPLE . $test);
		// 		$state = self::getStateByBlock($block)->mirror("y");
		// 		assert($state->toBlock() instanceof Block);
		// 		Server::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . "Flipped block y: " . TF::AQUA . $state->toBlock());
		// 	} catch (Exception $e) {
		// 		Server::getInstance()->getLogger()->debug($e->getMessage());
		// 		continue;
		// 	}
		// }
		// //test doors because WTF they are weird
		// try {
		// 	for ($i = 0; $i < 15; $i++) {
		// 		$block = BlockFactory::getInstance()->get(BlockLegacyIds::IRON_DOOR_BLOCK, $i);
		// 		Server::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . $block);
		// 		$entry = self::getStateByBlock($block);
		// 		Server::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . $entry);
		// 		Server::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . $entry->blockStates);
		// 		Server::getInstance()->getLogger()->debug(TF::LIGHT_PURPLE . self::printStates($entry, false));
		// 	}
		// } catch (Exception $e) {
		// 	Server::getInstance()->getLogger()->debug($e->getMessage());
		// }
	}

	public static function placeAllBlockstates(Position $position): void
	{
		$pasteY = $position->getFloorY();
		$pasteX = $position->getFloorX();
		$pasteZ = $position->getFloorZ();
		$world = $position->getWorld();
		$sorted = [];
		foreach (self::$legacyStateMap as $name => $v) {
			foreach ($v as $meta => $r12ToCurrentBlockMapEntry) {
				try {
					$sorted[] = (new BlockStatesEntry($name, $r12ToCurrentBlockMapEntry->getBlockState()->getCompoundTag('states')))->toBlock();
				} catch (Exception $e) {
					//skip blocks that pm does not know about
					#$world->getServer()->broadcastMessage($e->getMessage());
				}
			}
		}
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

	/** @noinspection PhpUnusedPrivateMethodInspection */
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
	 * @noinspection PhpUnusedPrivateMethodInspection
	 */
	private static function generateBlockStateAliasMapJson(): void
	{
		Loader::getInstance()->saveResource("blockstate_alias_map.json");
		$config = new Config(Loader::getInstance()->getDataFolder() . "blockstate_alias_map.json");
		$config->setAll([]);
		$config->save();
		foreach (self::$legacyStateMap as $blockName => $v) {
			foreach ($v as $meta => $legacyMapEntry) {
				$states = clone $legacyMapEntry->getBlockState()->getCompoundTag('states');
				foreach ($states as $stateName => $state) {
					if (!$config->exists($stateName)) {
						$alias = $stateName;
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
						$config->set($stateName, [
							"alias" => [$alias],
						]);
					}
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
		foreach (self::$legacyStateMap as $blockName => $v) {
			foreach ($v as $meta => $legacyMapEntry) {
				$states = clone $legacyMapEntry->getBlockState()->getCompoundTag('states');
				foreach ($states as $stateName => $state) {
					if (!array_key_exists($stateName, $all)) {
						$all[(string)$stateName] = [];
					}
					if (!in_array($state->getValue(), $all[$stateName], true)) {
						$all[(string)$stateName][] = $state->getValue();
						if (strpos($stateName, "_bit") !== false) {
							var_dump("_bit");
						} else {
							var_dump("no _bit");
						}
					}
				}
			}
		}
		ksort($all);
		$config->setAll($all);
		$config->save();
		unset($config);
	}

	/**
	 * Reads a value of an object, regardless of access modifiers
	 * @param object $object
	 * @param string $property
	 * @return mixed
	 */
	public static function &readAnyValue(object $object, string $property)
	{
		$invoke = Closure::bind(function & () use ($property) {
			return $this->$property;
		}, $object, $object)->__invoke();
		/** @noinspection PhpUnnecessaryLocalVariableInspection */
		$value = &$invoke;

		return $value;
	}

}