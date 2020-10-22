<?php /** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use GlobalLogger;
use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\UnknownBlock;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\utils\TextFormat;
use xenialdan\MagicWE2\Loader;

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
		[$id, $meta] = RuntimeBlockMapping::getInstance()->fromRuntimeId($this->runtimeId);
		if ($id === BlockLegacyIds::INFO_UPDATE) {
			return false;
		}
		if ($this->nbt instanceof CompoundTag && !$this->nbt->valid()) {
			return false;
		}
		return true;
	}

	public function __toString()
	{
		[$id, $meta] = RuntimeBlockMapping::getInstance()->fromRuntimeId($this->runtimeId);
		$str = __CLASS__ . " " . $this->runtimeId . " [$id:$meta]";
		if ($this->nbt instanceof CompoundTag) {
			$str .= " " . str_replace("\n", "", $this->nbt->toString());
		}
		return $str;
	}

	public function toBlock(): Block
	{
		BlockFactory::getInstance();
		[$id, $meta] = RuntimeBlockMapping::getInstance()->fromRuntimeId($this->runtimeId);
		try {
			return BlockFactory::getInstance()->get($id, $meta);
		} catch (InvalidArgumentException $e) {
			GlobalLogger::get()->debug(Loader::PREFIX . TextFormat::GRAY . " Couldn't find a registered block for $id:$meta, trying UnknownBlock!");
		}
		return new UnknownBlock(new BlockIdentifier($id, $meta));
	}

	public static function fromBlock(Block $block): self
	{
		BlockFactory::getInstance();
		return new BlockEntry(RuntimeBlockMapping::getInstance()->toRuntimeId($block->getFullId()));
	}

}