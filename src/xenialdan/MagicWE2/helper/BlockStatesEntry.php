<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use GlobalLogger;
use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\utils\TextFormat;
use RuntimeException;
use Throwable;
use xenialdan\MagicWE2\exception\InvalidBlockStateException;
use xenialdan\MagicWE2\task\action\FlipAction;

class BlockStatesEntry
{
	/** @var string */
	public $blockIdentifier;
	/** @var CompoundTag */
	public $blockStates;
	/** @var string */
	public $blockFull;
	/** @var Block|null */
	public $block;

	/**
	 * BlockStatesEntry constructor.
	 * @param string $blockIdentifier
	 * @param CompoundTag $blockStates
	 * @param Block|null $block
	 */
	public function __construct(string $blockIdentifier, CompoundTag $blockStates, ?Block $block = null)
	{
		$this->blockIdentifier = $blockIdentifier;
		$this->blockStates = $blockStates;
		$this->block = $block;
		try {
			if ($this->blockStates !== null)
				$this->blockFull = TextFormat::clean(BlockStatesParser::printStates($this, false));
			else
				$this->blockFull = $this->blockIdentifier;
		} catch (Throwable $e) {
			GlobalLogger::get()->logException($e);
			$this->blockFull = $this->blockIdentifier;
		}
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		return $this->blockFull;
	}

	/**
	 * TODO hacky AF. clean up
	 * @return Block
	 * @throws InvalidArgumentException
	 * @throws RuntimeException
	 * @throws InvalidBlockStateException
	 */
	public function toBlock(): Block
	{
		if ($this->block instanceof Block) return $this->block;
		BlockFactory::getInstance();
		$blocks = BlockStatesParser::getInstance()::fromString($this->blockFull, false);
		$block = reset($blocks);
		if($block instanceof Block) $this->block = $block;
		return $this->block;
	}

	/**
	 * TODO Optimize (reduce getStateByBlock/fromString calls)
	 * @param int $amount any of [90,180,270]
	 * @return BlockStatesEntry
	 * @throws InvalidArgumentException
	 * @throws InvalidBlockStateException
	 * @throws RuntimeException
	 */
	public function rotate(int $amount): BlockStatesEntry
	{
		//TODO validate $amount
		$clone = clone $this;
		$block = $clone->toBlock();
		$idMapName = str_replace("minecraft:", "", BlockStatesParser::getBlockIdMapName($block));
		$key = $idMapName . ":" . $block->getMeta();
		if (strpos($idMapName, "_door") !== false) {
			$fromMap = BlockStatesParser::getDoorRotationFlipMap()[$block->getMeta()] ?? null;
		} else {
			$fromMap = BlockStatesParser::getRotationFlipMap()[$key] ?? null;
		}
		if ($fromMap === null) return $clone;
		$rotatedStates = $fromMap[$amount] ?? null;
		if ($rotatedStates === null) return $clone;
		//ugly hack to keep current ones
		//TODO use the states compound tag
		$bsCompound = $clone->blockStates;
		#$bsCompound->setName("minecraft:$key");//TODO this might cause issues with the parser since it stays same //seems to work ¯\_(ツ)_/¯
		foreach ($rotatedStates as $tagName => $v) {
			//TODO clean up.. new method?
			$tag = $bsCompound->getTag($tagName);
			if ($tag === null) {
				throw new InvalidBlockStateException("Invalid state $tagName");
			}
			if ($tag instanceof StringTag) {
				$bsCompound->setString($tagName, $v);
			} else if ($tag instanceof IntTag) {
				$bsCompound->setInt($tagName, (int)$v);
			} else if ($tag instanceof ByteTag) {
				if ($v === 1) $v = "true";
				if ($v === 0) $v = "false";
				if ($v !== "true" && $v !== "false") {
					throw new InvalidBlockStateException("Invalid value $v for blockstate $tagName, must be \"true\" or \"false\"");
				}
				$bsCompound->setByte($tagName, $v === "true" ? 1 : 0);
			} else {
				throw new InvalidBlockStateException("Unknown tag of type " . get_class($tag) . " detected");
			}
		}
		$clone->blockStates = $bsCompound;
		$clone->blockFull = TextFormat::clean(BlockStatesParser::printStates($clone, false));
		if (strpos($idMapName, "_door") !== false) {
			$clone->block = BlockStatesParser::fromString($clone->blockFull, false)[0];
		} else
			$clone->block = null;
		return $clone;
		//TODO reduce useless calls. BSP::fromStates?
		#$blockFull = TextFormat::clean(BlockStatesParser::printStates($clone, false));
		#return BlockStatesParser::getStateByBlock(BlockStatesParser::fromString($blockFull)[0]);
	}

	/**
	 * TODO Optimize (reduce getStateByBlock/fromString calls)
	 * @param string $axis any of ["x","y","z","xz"]
	 * @return BlockStatesEntry
	 * @throws InvalidArgumentException
	 * @throws InvalidBlockStateException
	 * @throws RuntimeException
	 */
	public function mirror(string $axis): BlockStatesEntry
	{
		//TODO validate $axis
		$clone = clone $this;
		$block = $clone->toBlock();
		$idMapName = str_replace("minecraft:", "", BlockStatesParser::getBlockIdMapName($block));
		$key = $idMapName . ":" . $block->getMeta();
		if ($axis !== FlipAction::AXIS_Y) {//ugly hack for y flip
			$fromMap = BlockStatesParser::getRotationFlipMap()[$key] ?? null;
			if ($fromMap === null) {
				#var_dump("block not in mirror map");
				return $clone;
			}
			$flippedStates = $fromMap[$axis] ?? null;
			if ($flippedStates === null /*&& $axis !== FlipAction::AXIS_Y*/) {//ugly hack for y flip
				#var_dump("axis not in mirror map");
				return $clone;
			}
		}
		//ugly hack to keep current ones
		//TODO use the states compound tag
		$bsCompound = clone $clone->blockStates;//TODO check if clone is necessary
		#$bsCompound->setName("minecraft:$key");//TODO this might cause issues with the parser since it stays same //seems to work ¯\_(ツ)_/¯
		if ($axis === FlipAction::AXIS_Y && !(//TODO maybe add vine + mushroom block directions
				$bsCompound->hasTag("attachment") ||
				$bsCompound->hasTag("facing_direction") ||
				$bsCompound->hasTag("hanging") ||
				$bsCompound->hasTag("lever_direction") ||
				$bsCompound->hasTag("rail_direction") ||
				$bsCompound->hasTag("top_slot_bit") ||
				$bsCompound->hasTag("torch_facing_direction") ||
				$bsCompound->hasTag("upper_block_bit") ||
				$bsCompound->hasTag("upside_down_bit")
			)) {//ugly hack for y flip
			#var_dump("nothing can be flipped around y axis");
			return $clone;
		}
		foreach ($bsCompound as $tagName => $tag) {
			//TODO clean up.. new method?
			if ($axis === FlipAction::AXIS_Y) {
				$value = $tag->getValue();
				switch ($tagName) {//TODO clean up oh my god
					case "attachment":
					{
						if ($value === "standing") $value = "hanging";
						else if ($value === "hanging") $value = "standing";
						break;
					}
					case "hanging":
					case "upside_down_bit":
					case "upper_block_bit":
					case "top_slot_bit":
					case "facing_direction":
					{
						if ($value === 0) $value = 1;
						else if ($value === 1) $value = 0;
						break;
					}
					case "lever_direction":
					{
						if ($value === "down_east_west") $value = "up_east_west";
						else if ($value === "up_east_west") $value = "down_east_west";
						else if ($value === "down_north_south") $value = "up_north_south";
						else if ($value === "up_north_south") $value = "down_north_south";
						break;
					}
					case "rail_direction":
					{
						//TODO
						break;
					}
					case "torch_facing_direction":
					{
						if ($value === "unknown") $value = "top";
						else if ($value === "top") $value = "unknown";
						break;
					}/*
                    default:
                    {
                        $value = $flippedStates[$stateName];
                    }*/
				}
			} else if (isset($flippedStates)) $value = $flippedStates[$tagName] ?? $tag->getValue(); else throw new InvalidArgumentException("flippedStates is not set. Error should never occur, please use //report and send a stack trace");
			if ($tag instanceof StringTag) {
				$bsCompound->setString($tagName, $value);
			} else if ($tag instanceof IntTag) {
				$bsCompound->setInt($tagName, (int)$value);
			} else if ($tag instanceof ByteTag) {
				if ($value === 1) $value = "true";
				if ($value === 0) $value = "false";
				if ($value !== "true" && $value !== "false") {
					throw new InvalidBlockStateException("Invalid value $value for blockstate $tagName, must be \"true\" or \"false\"");
				}
				$bsCompound->setByte($tagName, $value === "true" ? 1 : 0);
			} else {
				throw new InvalidBlockStateException("Unknown tag of type " . get_class($tag) . " detected");
			}
		}
		$clone->blockStates = $bsCompound;
		$clone->block = null;
		$clone->blockFull = TextFormat::clean(BlockStatesParser::printStates($clone, false));
		#var_dump($clone->blockFull);
		return $clone;
		//TODO reduce useless calls. BSP::fromStates?
		#$blockFull = TextFormat::clean(BlockStatesParser::printStates($clone, false));
		#return BlockStatesParser::getStateByBlock(BlockStatesParser::fromString($blockFull)[0]);
	}

}