<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\nbt\tag\CompoundTag;
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
        $block = $this->toBlock();
        $idMapName = BlockStatesParser::getBlockIdMapName($block);
        $key = str_replace("minecraft:", "", $idMapName . ":" . $block->getDamage());
        $fromMap = BlockStatesParser::getRotationFlipMap()[$key] ?? null;
        if ($fromMap === null) return $this;
        $rotatedStates = $fromMap[$amount] ?? null;
        if ($rotatedStates === null) return $this;
        var_dump($rotatedStates);
        $s = [];
        foreach ($rotatedStates as $k => $v) {
            $s[] = "$k=$v";
        }
        $blockFull = $idMapName . "[" . implode(",", $s) . "]";
        return BlockStatesParser::getStateByBlock(BlockStatesParser::fromString($blockFull)[0]);
    }

}