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
        } catch (\Throwable $e) {
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

}