<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\session\data;

use BlockHorizons\libschematic\Schematic;
use InvalidArgumentException;
use JsonSerializable;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\TextFormat as TF;
use TypeError;
use UnexpectedValueException;
use xenialdan\libstructure\format\MCStructure;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\Loader;

class Asset implements JsonSerializable
{
	const TYPE_SCHEMATIC = 'schematic';
	const TYPE_MCSTRUCTURE = 'mcstructure';
	const TYPE_CLIPBOARD = 'clipboard';

	public Schematic|SingleClipboard|MCStructure $structure;
	public string $filename;//used as identifier
	public string $displayname;
	public bool $locked = false;
	public ?string $ownerXuid;
	private ?Item $item;

	/**
	 * Asset constructor.
	 * @param string $filename
	 * @param Schematic|MCStructure|SingleClipboard $value
	 * @param bool $locked
	 * @param string|null $ownerXuid
	 */
	public function __construct(string $filename, Schematic|SingleClipboard|MCStructure $value, bool $locked = false, ?string $ownerXuid = null)
	{
		$this->filename = $filename;
		$this->displayname = pathinfo($filename, PATHINFO_FILENAME);
		$this->structure = $value;
		$this->locked = $locked;
		$this->ownerXuid = $ownerXuid;
	}

	public function getSize(): Vector3
	{
		if ($this->structure instanceof Schematic) return new Vector3($this->structure->getWidth(), $this->structure->getHeight(), $this->structure->getLength());
		if ($this->structure instanceof MCStructure) return $this->structure->getSize();
		if ($this->structure instanceof SingleClipboard) return new Vector3($this->structure->selection->getSizeX(), $this->structure->selection->getSizeY(), $this->structure->selection->getSizeZ());
		throw new UnexpectedValueException('Invalid class as Asset');
	}

	public function getTotalCount(): int
	{
		if ($this->structure instanceof Schematic || $this->structure instanceof MCStructure) return $this->getSize()->getFloorX() * $this->getSize()->getFloorY() * $this->getSize()->getFloorZ();
		if ($this->structure instanceof SingleClipboard) return $this->structure->getTotalCount();
		throw new UnexpectedValueException('Invalid class as Asset');
	}

	public function getOrigin(): Vector3
	{
		if ($this->structure instanceof Schematic) return new Vector3(0, 0, 0);
		if ($this->structure instanceof MCStructure) return $this->structure->getStructureWorldOrigin();
		if ($this->structure instanceof SingleClipboard) return $this->structure->position;
		throw new UnexpectedValueException('Invalid class as Asset');
	}

	/**
	 * @param bool $renew
	 * @return Item
	 * @throws InvalidArgumentException
	 * @throws TypeError
	 */
	public function toItem(bool $renew = false): Item
	{
		if ($this->item !== null && !$renew) return $this->item;
		$item = ItemFactory::getInstance()->get(ItemIds::SCAFFOLDING);
		$item->addEnchantment(new EnchantmentInstance(Loader::$ench));
		[$filename, $displayname, $type, $locked, $owner] = $this->jsonSerialize();
		$item->getNamedTag()->setTag(API::TAG_MAGIC_WE_ASSET,
			CompoundTag::create()
				->setString("filename", $filename)
				->setString("displayname", $displayname)
				->setString("type", $type)
				->setByte("locked", $locked ? 1 : 0)
				->setString("owner", $owner)
		);
		$item->setCustomName(Loader::PREFIX . TF::BOLD . TF::DARK_PURPLE . $displayname);
		$item->setLore($this->generateLore());
		$this->item = $item;
		return $item;
	}

	/**
	 * @return array
	 */
	private function generateLore(): array
	{
		$return = [];
		foreach ($this->jsonSerialize() as $k => $v) {
			if ($v === '' || $v === 'displayname') continue;
			if (is_bool($v)) {
				$return[] = (ucfirst($k) . ": " . ($v ? "Yes" : "No"));
				continue;
			}
			$return[] = (ucfirst($k) . ": " . $v);
		}
		return $return;
	}

	public function jsonSerialize()
	{
		return [
			'filename' => $this->filename,
			'displayname' => $this->displayname,
			'type' => $this->structure instanceof Schematic ? self::TYPE_SCHEMATIC : ($this->structure instanceof MCStructure ? self::TYPE_MCSTRUCTURE : ($this->structure instanceof SingleClipboard ? self::TYPE_CLIPBOARD : '')),
			'locked' => $this->locked,
			'owner' => $this->ownerXuid ?? '',
		];
	}
}