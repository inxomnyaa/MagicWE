<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\session\data;

use Exception;
use InvalidArgumentException;
use JsonException;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\UnexpectedTagTypeException;
use TypeError;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\exception\ActionNotFoundException;
use xenialdan\MagicWE2\exception\BrushException;
use xenialdan\MagicWE2\exception\ShapeNotFoundException;
use xenialdan\MagicWE2\session\UserSession;
use xenialdan\MagicWE2\tool\Brush;
use xenialdan\MagicWE2\tool\BrushProperties;
use function json_decode;
use const JSON_THROW_ON_ERROR;

final class BrushCollection
{

	/** @var array<string, Brush> */
	public array $brushes;
	private UserSession $session;

	public function __construct(UserSession $session)
	{
		$this->session = $session;
	}

	/**
	 * @return UserSession
	 */
	public function getSession(): UserSession
	{
		return $this->session;
	}

	/** @return Brush[] */
	public function getAll(): array
	{
		return $this->brushes;
	}

	/**
	 * TODO exception for not a brush
	 * @param Item $item
	 * @return Brush
	 * @throws Exception
	 */
	public function getBrushFromItem(Item $item): Brush
	{
		if ((($entry = $item->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE_BRUSH))) instanceof CompoundTag) {
			$version = $entry->getInt("version", 0);
			if ($version !== BrushProperties::VERSION) {
				throw new BrushException("Brush can not be restored - version mismatch");
			}
			/** @var BrushProperties $properties */
			$properties = json_decode($entry->getString("properties"), false, 512, JSON_THROW_ON_ERROR);
			$brush = $this->getBrush($properties->uuid);
			if ($brush instanceof Brush) {
				return $brush;
			}
			$brush = new Brush($properties);
			$this->addBrush($brush);
			return $brush;
		}
		throw new BrushException("The item is not a valid brush!");
	}

	public function getBrush(string $id): ?Brush
	{
		return $this->brushes[$id];//TODO allow finding by custom name
	}

	/**
	 * TODO exception for not a brush
	 * @param Brush $brush UuidInterface will be set automatically
	 * @return void
	 */
	public function addBrush(Brush $brush): void
	{
		$this->brushes[$brush->properties->uuid] = $brush;
		$this->getSession()->sendMessage($this->getSession()->getLanguage()->translateString('session.brush.added', [$brush->getName()]));
	}

	/**
	 * @param Brush $brush UuidInterface will be set automatically
	 * @param bool $delete If true, it will be removed from the session brushes
	 * @return void
	 * @throws UnexpectedTagTypeException
	 */
	public function removeBrush(Brush $brush, bool $delete = false): void
	{
		if ($delete) unset($this->brushes[$brush->properties->uuid]);
		foreach ($this->getSession()->getPlayer()->getInventory()->getContents() as $slot => $item) {
			if (($entry = $item->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE_BRUSH)) instanceof CompoundTag) {
				if ($entry->getString("id") === $brush->properties->uuid) {
					$this->getSession()->getPlayer()->getInventory()->clear($slot);
				}
			}
		}
		if ($delete) $this->getSession()->sendMessage($this->getSession()->getLanguage()->translateString('session.brush.deleted', [$brush->getName(), $brush->properties->uuid]));
		else $this->getSession()->sendMessage($this->getSession()->getLanguage()->translateString('session.brush.removed', [$brush->getName(), $brush->properties->uuid]));
	}

	/**
	 * TODO exception for not a brush
	 * @param Brush $brush UuidInterface will be set automatically
	 * @return void
	 * @throws ActionNotFoundException
	 * @throws InvalidArgumentException
	 * @throws JsonException
	 * @throws ShapeNotFoundException
	 * @throws TypeError
	 * @throws UnexpectedTagTypeException
	 */
	public function replaceBrush(Brush $brush): void
	{
		$this->brushes[$brush->properties->uuid] = $brush;
		$new = $brush->toItem();
		foreach ($this->getSession()->getPlayer()->getInventory()->getContents() as $slot => $item) {
			if (($entry = $item->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE_BRUSH)) instanceof CompoundTag) {
				if ($entry->getString("id") === $brush->properties->uuid) {
					$this->getSession()->getPlayer()->getInventory()->setItem($slot, $new);
				}
			}
		}
	}
}