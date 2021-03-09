<?php /** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockLegacyIds;
use pocketmine\nbt\tag\CompoundTag;

class BlockEntry
{
	/** @var int BlockFullId */
	public $fullId;
	/** @var CompoundTag|null */
	public $nbt;

	/**
	 * BlockEntry constructor.
	 * @param int $fullId
	 * @param CompoundTag|null $nbt
	 */
	public function __construct(int $fullId, ?CompoundTag $nbt = null)
	{
		$this->fullId = $fullId;
		$this->nbt = $nbt;
	}

	public function validate(): bool
	{
		/** @var BlockFactory $instance */
		$instance = BlockFactory::getInstance();
		$block = $instance->fromFullBlock($this->fullId);
		[$id, $meta] = [$block->getId(), $block->getMeta()];
		if ($id === BlockLegacyIds::INFO_UPDATE) {
			return false;
		}
		return true;
	}

	public function __toString()
	{
		/** @var BlockFactory $instance */
		$instance = BlockFactory::getInstance();
		$block = $instance->fromFullBlock($this->fullId);
		$str = __CLASS__ . " " . $this->fullId . " [{$block->getId()}:{$block->getMeta()}]";
		if ($this->nbt instanceof CompoundTag) {
			$str .= " " . str_replace("\n", "", $this->nbt->toString());
		}
		return $str;
	}

	public function toBlock(): Block
	{
		/** @var BlockFactory $instance */
		$instance = BlockFactory::getInstance();
		return $instance->fromFullBlock($this->fullId);
	}

	public static function fromBlock(Block $block): self
	{
		BlockFactory::getInstance();
		return new BlockEntry($block->getFullId());
	}

}