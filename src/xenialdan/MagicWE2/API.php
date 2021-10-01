<?php /** @noinspection PhpUnusedParameterInspection */

declare(strict_types=1);

namespace xenialdan\MagicWE2;

use Exception;
use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\utils\InvalidBlockStateException;
use pocketmine\item\LegacyStringToItemParserException;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\UnexpectedTagTypeException;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\Position;
use pocketmine\world\World;
use RuntimeException;
use xenialdan\MagicWE2\clipboard\Clipboard;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\exception\BlockQueryAlreadyParsedException;
use xenialdan\MagicWE2\exception\CalculationException;
use xenialdan\MagicWE2\exception\LimitExceededException;
use xenialdan\MagicWE2\helper\BlockPalette;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\selection\shape\Cuboid;
use xenialdan\MagicWE2\selection\shape\Shape;
use xenialdan\MagicWE2\session\data\Asset;
use xenialdan\MagicWE2\session\Session;
use xenialdan\MagicWE2\session\UserSession;
use xenialdan\MagicWE2\task\action\SetBiomeAction;
use xenialdan\MagicWE2\task\action\TaskAction;
use xenialdan\MagicWE2\task\AsyncActionTask;
use xenialdan\MagicWE2\task\AsyncCopyTask;
use xenialdan\MagicWE2\task\AsyncCountTask;
use xenialdan\MagicWE2\task\AsyncFillTask;
use xenialdan\MagicWE2\task\AsyncPasteAssetTask;
use xenialdan\MagicWE2\task\AsyncPasteTask;
use xenialdan\MagicWE2\task\AsyncReplaceTask;
use xenialdan\MagicWE2\tool\Brush;

class API
{
	// Base flag to modify on
	public const FLAG_BASE = 1;
	// Only replaces the air
	public const FLAG_KEEP_BLOCKS = 0x01; // -r
	// Only change non-air blocks
	public const FLAG_KEEP_AIR = 0x02; // -k
	// The -a flag makes it not paste air.
	public const FLAG_PASTE_WITHOUT_AIR = 0x03; // -a
	// Pastes or sets hollow
	public const FLAG_HOLLOW = 0x04; // -h
	// The -n flag makes it only consider naturally occurring blocks.
	public const FLAG_NATURAL = 0x05; // -n
	// Without the -p flag, the paste will appear centered at the target location.
	// With the flag, the paste will appear relative to where you had
	// stood, relative by the copied area when you copied it.
	public const FLAG_POSITION_RELATIVE = 0x06; // -p
	// Without the -v flag, block checks, selections and replacing will use and check the exact meta
	// of the blocks, with the flag it will check for similar variants
	// For example: Oak Logs with any rotation instead of a specific rotation
	public const FLAG_VARIANT = 0x07; // -v
	// With the -m flag the damage values / meta will be kept
	// For example: Replacing all wool blocks with concrete of the same color
	public const FLAG_KEEP_META = 0x08; // -m
	// Pastes or sets hollow but closes off the ends
	public const FLAG_HOLLOW_CLOSED = 0x09; // -hc//TODO

	public const TAG_MAGIC_WE = "MagicWE";
	public const TAG_MAGIC_WE_BRUSH = "MagicWEBrush";
	public const TAG_MAGIC_WE_ASSET = "MagicWEAsset";
	public const TAG_MAGIC_WE_PALETTE = "MagicWEPalette";

	//TODO Split into separate Class (SchematicStorage?)
	/** @var Clipboard[] */
	private static array $schematics = [];//TODO

	/**
	 * @param Selection $selection
	 * @param Session $session
	 * @param BlockPalette $newblocks
	 * @param int $flags
	 * @return bool
	 */
	public static function fillAsync(Selection $selection, Session $session, BlockPalette $newblocks, int $flags = self::FLAG_BASE): bool
	{
		if ($newblocks->empty()) {
			$session->sendMessage(TF::RED . "New blocks is empty!");
			return false;
		}
		try {
			$limit = Loader::getInstance()->getConfig()->get("limit", -1);
			if ($limit !== -1 && $selection->getShape()->getTotalCount() > $limit) {
				throw new LimitExceededException($session->getLanguage()->translateString('error.limitexceeded'));
			}
			if ($session instanceof UserSession) {
				$player = $session->getPlayer();
				/** @var Player $player */
				$session->getBossBar()->showTo([$player]);
			}
			Server::getInstance()->getAsyncPool()->submitTask(new AsyncFillTask($session->getUUID(), $selection, $selection->getShape()->getTouchedChunks($selection->getWorld()), $newblocks, $flags));
		} catch (Exception $e) {
			$session->sendMessage($e->getMessage());
			Loader::getInstance()->getLogger()->logException($e);
			return false;
		}
		return true;
	}

	/**
	 * @param Selection $selection
	 * @param Session $session
	 * @param BlockPalette $oldBlocks
	 * @param BlockPalette $newBlocks
	 * @param int $flags
	 * @return bool
	 */
	public static function replaceAsync(Selection $selection, Session $session, BlockPalette $oldBlocks, BlockPalette $newBlocks, int $flags = self::FLAG_BASE): bool
	{
		if ($oldBlocks->empty()) $session->sendMessage(TF::RED . "Old blocks is empty!");
		if ($newBlocks->empty()) $session->sendMessage(TF::RED . "New blocks is empty!");
		if ($oldBlocks->empty() || $newBlocks->empty()) return false;
		try {
			$limit = Loader::getInstance()->getConfig()->get("limit", -1);
			if ($limit !== -1 && $selection->getShape()->getTotalCount() > $limit) {
				throw new LimitExceededException($session->getLanguage()->translateString('error.limitexceeded'));
			}
			if ($session instanceof UserSession) {
				$player = $session->getPlayer();
				/** @var Player $player */
				$session->getBossBar()->showTo([$player]);
			}
			Server::getInstance()->getAsyncPool()->submitTask(new AsyncReplaceTask($session->getUUID(), $selection, $selection->getShape()->getTouchedChunks($selection->getWorld()), $oldBlocks, $newBlocks, $flags));
		} catch (Exception $e) {
			$session->sendMessage($e->getMessage());
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
	 */
	public static function copyAsync(Selection $selection, Session $session, int $flags = self::FLAG_BASE): bool
	{
		#return false;
		try {
			$limit = Loader::getInstance()->getConfig()->get("limit", -1);
			if ($limit !== -1 && $selection->getShape()->getTotalCount() > $limit) {
				throw new LimitExceededException($session->getLanguage()->translateString('error.limitexceeded'));
			}
			//TODO check/edit how relative position works
			$offset = new Vector3(0, 0, 0);
			if ($session instanceof UserSession) {
				/** @var Player $player */
				$player = $session->getPlayer();
				/*if (!self::hasFlag($flags, self::FLAG_POSITION_RELATIVE)*///TODO relative or not by flags
				$offset = $selection->getShape()->getMinVec3()->subtractVector($player->getPosition()->asVector3()->floor())->floor();//TODO figure out wrong offset
				$session->getBossBar()->showTo([$player]);
			}
			#var_dump($selection->getShape()->getMinVec3(), $session->getPlayer()->asVector3(), $selection->getShape()->getMinVec3()->subtract($session->getPlayer()), $offset);
			Server::getInstance()->getAsyncPool()->submitTask(new AsyncCopyTask($session->getUUID(), $selection, $offset, $selection->getShape()->getTouchedChunks($selection->getWorld()), $flags));
		} catch (Exception $e) {
			$session->sendMessage($e->getMessage());
			Loader::getInstance()->getLogger()->logException($e);
			return false;
		}
		return true;
	}

	/**
	 * TODO: flag parsing, Position to paste at
	 * @param SingleClipboard $clipboard TODO should this be Clipboard?
	 * @param Session $session
	 * @param Position $target CURRENTLY SENDER POSITION
	 * @param int $flags
	 * @return bool
	 * @throws AssumptionFailedError
	 */
	public static function pasteAsync(SingleClipboard $clipboard, Session $session, Position $target, int $flags = self::FLAG_BASE): bool
	{
		#return false;
		try {
			$limit = Loader::getInstance()->getConfig()->get("limit", -1);
			if ($limit !== -1 && $clipboard->getTotalCount() > $limit) {
				throw new LimitExceededException($session->getLanguage()->translateString('error.limitexceeded'));
			}
			#$c = $clipboard->getCenter();
			#$clipboard->setCenter($target->asVector3());//TODO check
			if ($session instanceof UserSession) {
				$player = $session->getPlayer();
				/** @var Player $player */
				$session->getBossBar()->showTo([$player]);
			}
			$start = clone $target->asVector3()->floor()->addVector($clipboard->position)->floor();//start pos of paste//TODO if using rotate, this fails
			$end = $start->addVector($clipboard->selection->getShape()->getMaxVec3()->subtractVector($clipboard->selection->getShape()->getMinVec3()));//add size
			$aabb = new AxisAlignedBB($start->getFloorX(), $start->getFloorY(), $start->getFloorZ(), $end->getFloorX(), $end->getFloorY(), $end->getFloorZ());//create paste aabb
			$shape = clone $clipboard->selection->getShape();//needed
			$shape->setPasteVector($target->asVector3()->floor());//needed
			$clipboard->selection->setShape($shape);//needed
			$touchedChunks = self::getAABBTouchedChunksTemp($target->getWorld(), $aabb);//TODO clean up or move somewhere else. Better not touch, it works.
			Server::getInstance()->getAsyncPool()->submitTask(new AsyncPasteTask($session->getUUID(), $clipboard->selection, $touchedChunks, $clipboard));
		} catch (Exception $e) {
			$session->sendMessage($e->getMessage());
			Loader::getInstance()->getLogger()->logException($e);
			return false;
		}
		return true;
	}

	/**
	 * @param ChunkManager $manager
	 * @param AxisAlignedBB $aabb
	 * @return string[]
	 */
	private static function getAABBTouchedChunksTemp(ChunkManager $manager, AxisAlignedBB $aabb): array
	{
		$maxX = $aabb->maxX >> 4;
		$minX = $aabb->minX >> 4;
		$maxZ = $aabb->maxZ >> 4;
		$minZ = $aabb->minZ >> 4;
		$touchedChunks = [];
		for ($x = $minX; $x <= $maxX; $x++) {
			for ($z = $minZ; $z <= $maxZ; $z++) {
				$chunk = $manager->getChunk($x, $z);
				if ($chunk === null) {
					continue;
				}
				print __METHOD__ . " Touched Chunk at: $x:$z" . PHP_EOL;
				$touchedChunks[World::chunkHash($x, $z)] = FastChunkSerializer::serialize($chunk);
			}
		}
		print  __METHOD__ . " Touched chunks count: " . count($touchedChunks) . PHP_EOL;
		return $touchedChunks;
	}

	/**
	 * @param Selection $selection
	 * @param Session $session
	 * @param BlockPalette $filterBlocks
	 * @param int $flags
	 * @return bool
	 */
	public static function countAsync(Selection $selection, Session $session, BlockPalette $filterBlocks, int $flags = self::FLAG_BASE): bool
	{
		try {
			$limit = Loader::getInstance()->getConfig()->get("limit", -1);
			if ($limit !== -1 && $selection->getShape()->getTotalCount() > $limit) {
				throw new LimitExceededException("You are trying to count too many blocks at once. Reduce the selection or raise the limit");
			}
			Server::getInstance()->getAsyncPool()->submitTask(new AsyncCountTask($session->getUUID(), $selection, $selection->getShape()->getTouchedChunks($selection->getWorld()), $filterBlocks, $flags));
		} catch (Exception $e) {
			$session->sendMessage($e->getMessage());
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
	public static function setBiomeAsync(Selection $selection, Session $session, int $biomeId): bool
	{
		try {
			$limit = Loader::getInstance()->getConfig()->get("limit", -1);
			if ($limit !== -1 && $selection->getShape()->getTotalCount() > $limit) {
				throw new LimitExceededException($session->getLanguage()->translateString('error.limitexceeded'));
			}
			if ($session instanceof UserSession) {
				$player = $session->getPlayer();
				/** @var Player $player */
				$session->getBossBar()->showTo([$player]);
			}
			Server::getInstance()->getAsyncPool()->submitTask(new AsyncActionTask($session->getUUID(), $selection, new SetBiomeAction($biomeId), $selection->getShape()->getTouchedChunks($selection->getWorld()), BlockPalette::CREATE(), BlockPalette::CREATE()));
		} catch (Exception $e) {
			$session->sendMessage($e->getMessage());
			Loader::getInstance()->getLogger()->logException($e);
			return false;
		}
		return true;
	}

	/**
	 * Creates a brush at a specific location with the passed settings
	 * @param Block $target
	 * @param Brush $brush
	 * @param Session $session
	 * @throws InvalidArgumentException
	 * @throws RuntimeException
	 * @throws AssumptionFailedError
	 * @throws Exception
	 * @throws Exception
	 * @throws Exception
	 */
	public static function createBrush(Block $target, Brush $brush, Session $session): void
	{
		$shapeClass = $brush->properties->shape;
		/** @var Shape $shape */
		$shape = new $shapeClass($target->getPosition()->asVector3(), ...array_values($brush->properties->shapeProperties));
		$selection = new Selection($session->getUUID(), $target->getPosition()->getWorld());
		$selection->setShape($shape);
		$actionClass = $brush->properties->action;
		//TODO remove hack
		if ($actionClass === SetBiomeAction::class) $brush->properties->actionProperties["biomeId"] = $brush->properties->biomeId;
		/** @var TaskAction $action */
		$action = new $actionClass(...array_values($brush->properties->actionProperties));
		$action->prefix = "Brush";
		Server::getInstance()->getAsyncPool()->submitTask(new AsyncActionTask($session->getUUID(), $selection, $action, $selection->getShape()->getTouchedChunks($selection->getWorld()), BlockPalette::fromString($brush->properties->blocks), BlockPalette::fromString($brush->properties->filter)));
	}

	/**
	 * @param Block $target
	 * @param CompoundTag $settings
	 * @param Session $session
	 * @param int $flags
	 * @return bool
	 */
	public static function floodArea(Block $target, CompoundTag $settings, Session $session, int $flags = self::FLAG_BASE): bool
	{ //TODO
		$session->sendMessage(TF::RED . "TEMPORARILY DISABLED!");
		return false;/*
        $shape = ShapeRegistry::getShape($target->getWorld(), ShapeRegistry::TYPE_FLOOD, self::compoundToArray($settings));
        $shape->setCenter($target->asVector3());//TODO fix the offset?: if you have a uneven number, the center actually is between 2 blocks
        $messages = [];
        $error = false;
        return self::fillAsync($shape, $session, self::blockParser($shape->options['blocks'], $messages, $error), $flags);*/
	}

	public static function placeAsset(Position $target, Asset $asset, CompoundTag $settings, UserSession $session): bool
	{

		#$start = clone $target->asVector3()->floor()->addVector($asset->getOrigin())->floor();//start pos of paste//TODO if using rotate, this fails
		#$end = $start->addVector($asset->getSize()->subtractVector($asset->getOrigin()));//add size
		//[$target->x,$target->y,$target->z] = [($v=$target->asVector3())->getFloorX(),$v->getFloorY(),$v->getFloorZ()];
		$start = clone $target->asVector3();//start pos of paste//TODO if using rotate, this fails
		$end = $start->addVector($asset->getSize());//add size
		$shape = Cuboid::constructFromPositions(Vector3::minComponents($start, $end), Vector3::maxComponents($start, $end));
		$offset = new Vector3($asset->getSize()->getX() / 2, 0, $asset->getSize()->getZ() / 2);
		#$shape->setPasteVector($target->asVector3()->subtract(($asset->getSize()->getX()) / 2, 0, ($asset->getSize()->getZ()) / 2));//TODO this causes a size +0.5x +0.5z shift
		$selection = new Selection($session->getUUID(), $target->getWorld());
		$selection->setShape($shape);
		$aabb = $shape->getAABB();
		$touchedChunks = self::getAABBTouchedChunksTemp($target->getWorld(), $aabb->offset(-$offset->getX(), -$offset->getY(), -$offset->getZ()));//TODO clean up or move somewhere else. Better not touch, it works.
		Server::getInstance()->getAsyncPool()->submitTask(new AsyncPasteAssetTask($session->getUUID(), $target->asVector3(), $selection, $touchedChunks, $asset));
		return true;
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
	public static function setSchematics(array $schematics): void
	{
		self::$schematics = $schematics;
	}

	/* HELPER FUNCTIONS API PART */

	/**
	 * Parses String representations of flags into an integer with flags applied
	 * @param string[] $flags An array containing string representations of the flags
	 * @return int
	 * @throws RuntimeException
	 */
	public static function flagParser(array $flags): int
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
	public static function hasFlag(int $flags, int $check): bool
	{
		return ($flags & (self::FLAG_BASE << $check)) > 0;
	}

	/**
	 * More fail proof method of parsing a string to a Block
	 * @param string $fullstring
	 * @param array $messages
	 * @param bool $error
	 * @return BlockPalette
	 * @throws BlockQueryAlreadyParsedException
	 * @throws InvalidArgumentException
	 * @throws InvalidBlockStateException
	 * @throws LegacyStringToItemParserException
	 * @throws UnexpectedTagTypeException
	 * @deprecated Use BlockPalette::fromString()
	 */
	public static function blockParser(string $fullstring, array &$messages, bool &$error): BlockPalette
	{
		BlockFactory::getInstance();
		return BlockPalette::fromString($fullstring);
	}

	/**
	 * Evaluate mathematics in a string
	 * https://stackoverflow.com/a/54684348/4532380
	 * @param string $str
	 * @return float|int
	 * @throws CalculationException
	 */
	public static function evalAsMath(string $str): float|int
	{
		$error = false;
		$div_mul = false;
		$add_sub = false;
		$result = 0;

		$str = preg_replace('/[^\d.+\-*\/]/', '', $str);
		$str = rtrim(trim($str, '/*+'), '-');

		if ((str_contains($str, '/') || str_contains($str, '*'))) {
			$div_mul = true;
			$operators = ['*', '/'];
			while (!$error && !empty($operators)) {
				$operator = array_pop($operators);
				while ($operator !== null && str_contains($str, $operator)) {
					$regex = '/([\d\.]+)\\' . $operator . '(\-?[\d\.]+)/';
					preg_match($regex, $str, $matches);
					if (isset($matches[1], $matches[2])) {
						//if ($operator === '+') $result = (float)$matches[1] + (float)$matches[2];
						//if ($operator === '-') $result = (float)$matches[1] - (float)$matches[2];
						if ($operator === '*') $result = (float)$matches[1] * (float)$matches[2];
						if ($operator === '/') {
							if ((float)$matches[2]) {
								$result = (float)$matches[1] / (float)$matches[2];
							} else {
								$error = true;
							}
						}
						$str = preg_replace($regex, (string)$result, $str, 1);
						$str = str_replace(['++', '--', '-+', '+-'], ['+', '+', '-', '-'], $str);
					} else {
						$error = true;
					}
				}
			}
		}

		if (!$error && (str_contains($str, '+') || str_contains($str, '-'))) {
			$add_sub = true;
			preg_match_all('/([\d.]+|[+\-])/', $str, $matches);
			if (isset($matches[0])) {
				$result = 0;
				$operator = '+';
				$tokens = $matches[0];
				foreach ($tokens as $iValue) {
					if ($iValue === '+' || $iValue === '-') {
						$operator = $iValue;
					} else {
						$result = ($operator === '+') ? ($result + (float)$iValue) : ($result - (float)$iValue);
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
	public static function compoundToArray(CompoundTag $compoundTag): array
	{
		$a = [];
		foreach ($compoundTag->getValue() as $key => $value) {
			$a[$key] = $value;
		}
		return $a;
		#$nbt = new LittleEndianNbtSerializer();
		#$treeRoot = new TreeRoot($compoundTag);
		#$buffer = $nbt->write($treeRoot);
		#return $treeRoot->getTag()->getValue();//TODO TEST PM4
	}

	/**
	 * This replicates the behaviour of Vector3::setComponents in PM3
	 * @param Block $block
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @return Block
	 */
	public static function setComponents(Block $block, int $x, int $y, int $z): Block
	{
		[$block->getPosition()->x, $block->getPosition()->y, $block->getPosition()->z] = [$x, $y, $z];
		return $block;
	}

	public static function vecToString(Vector3 $v): string
	{
		return TF::RESET . "[" . TF::RED . $v->getFloorX() . TF::RESET . ":" . TF::GREEN . $v->getFloorY() . TF::RESET . ":" . TF::AQUA . $v->getFloorZ() . TF::RESET . "]";
	}

	public static function boolToString(bool $b): string
	{
		return $b ? TF::RESET . TF::GREEN . "On" . TF::RESET : TF::RESET . TF::RED . "Off" . TF::RESET;
	}
}