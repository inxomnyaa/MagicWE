<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIds;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\RuntimeBlockMapping;

class BlockEntry
{
    /** @var int BlockRuntimeId TODO check if RuntimeBlockMapping is okay for that */
    public $runtimeId;
    /** @var CompoundTag|null */
    public $nbt;

    /**
     * BlockEntry constructor.
     * @param int $runtimeId
     * @param CompoundTag|null $nbt
     */
    public function __construct(int $runtimeId, ?CompoundTag $nbt = null)
    {
        $this->runtimeId = $runtimeId;
        $this->nbt = $nbt;
    }

    public function validate(): bool
    {
        [$id, $meta] = RuntimeBlockMapping::fromStaticRuntimeId($this->runtimeId);
        if ($id === BlockIds::INFO_UPDATE) return false;
        if ($this->nbt instanceof CompoundTag && !$this->nbt->valid()) return false;
        return true;
    }

    public function __toString()
    {
        [$id, $meta] = RuntimeBlockMapping::fromStaticRuntimeId($this->runtimeId);
        $str = __CLASS__ . " " . $this->runtimeId . " [$id:$meta]";
        if ($this->nbt instanceof CompoundTag) {
            $str .= " " . str_replace("\n", "", $this->nbt->toString());
        }
        return $str;
    }

    public function toBlock(): Block
    {
        if (!BlockFactory::isInit()) BlockFactory::init();
        [$id, $meta] = RuntimeBlockMapping::fromStaticRuntimeId($this->runtimeId);
        return BlockFactory::get($id, $meta);
    }

    public static function fromBlock(Block $block): self
    {
        if (!BlockFactory::isInit()) BlockFactory::init();
        return new BlockEntry(RuntimeBlockMapping::toStaticRuntimeId($block->getId(), $block->getDamage()));
    }

}