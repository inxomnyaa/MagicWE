<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\plugin\PluginException;
use pocketmine\utils\TextFormat;
use RuntimeException;
use Throwable;
use xenialdan\MagicWE2\exception\InvalidBlockStateException;
use xenialdan\MagicWE2\Loader;

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
            Loader::getInstance()->getLogger()->logException($e);
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
     * @throws PluginException
     * @throws InvalidBlockStateException
     */
    public function toBlock(): Block
    {
        if ($this->block instanceof Block) return $this->block;
        if (!BlockFactory::isInit()) BlockFactory::init();
        if (!BlockStatesParser::isInit()) BlockStatesParser::init();
        return array_values(BlockStatesParser::fromString($this->blockFull, false))[0];
    }

    /**
     * TODO Optimize (reduce getStateByBlock/fromString calls)
     * @param int $amount any of [90,180,270]
     * @return BlockStatesEntry
     * @throws InvalidArgumentException
     * @throws InvalidBlockStateException
     * @throws PluginException
     * @throws RuntimeException
     */
    public function rotate(int $amount): BlockStatesEntry
    {
        //TODO validate $amount
        $clone = clone $this;
        $block = $clone->toBlock();
        $idMapName = str_replace("minecraft:", "", BlockStatesParser::getBlockIdMapName($block));
        $key = $idMapName . ":" . $block->getDamage();
        $fromMap = BlockStatesParser::getRotationFlipMap()[$key] ?? null;
        if ($fromMap === null) return $clone;
        $rotatedStates = $fromMap[$amount] ?? null;
        if ($rotatedStates === null) return $clone;
        //ugly hack to keep current ones
        //TODO use the states compound tag
        $bsCompound = $clone->blockStates;
        $bsCompound->setName("minecraft:$key");//TODO this might cause issues with the parser since it stays same
        var_dump($bsCompound);
        foreach ($rotatedStates as $k => $v) {
            //TODO clean up.. new method?
            $tag = $bsCompound->getTag($k);
            if ($tag === null) {
                throw new InvalidBlockStateException("Invalid state $k");
            }
            if ($tag instanceof StringTag) {
                $bsCompound->setString($tag->getName(), $v);
            } else if ($tag instanceof IntTag) {
                $bsCompound->setInt($tag->getName(), intval($v));
            } else if ($tag instanceof ByteTag) {
                if ($v !== "true" && $v !== "false") {
                    throw new InvalidBlockStateException("Invalid value $v for blockstate $k, must be \"true\" or \"false\"");
                }
                $val = ($v === "true" ? 1 : 0);
                $bsCompound->setByte($tag->getName(), $val);
            } else {
                throw new InvalidBlockStateException("Unknown tag of type " . get_class($tag) . " detected");
            }
        }
        var_dump($bsCompound);
        $clone->blockStates = $bsCompound;
        $clone->block = null;
        $clone->blockFull = TextFormat::clean(BlockStatesParser::printStates($this, false));
        return $clone;
        //TODO reduce useless calls. BSP::fromStates?
        #$blockFull = TextFormat::clean(BlockStatesParser::printStates($clone, false));
        #return BlockStatesParser::getStateByBlock(BlockStatesParser::fromString($blockFull)[0]);
    }

}