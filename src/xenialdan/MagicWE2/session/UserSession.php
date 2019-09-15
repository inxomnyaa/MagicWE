<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\session;

use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\UUID;
use xenialdan\apibossbar\BossBar;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\tool\Brush;
use xenialdan\MagicWE2\tool\BrushProperties;

class UserSession extends Session implements \JsonSerializable
{
    /** @var Player|null */
    private $player = null;
    /** @var BossBar */
    private $bossBar;
    /** @var bool */
    private $wandEnabled = true;
    /** @var bool */
    private $debugStickEnabled = true;
    /** @var Brush[] */
    private $brushes = [];

    public function __construct(Player $player)
    {
        $this->setPlayer($player);
        $this->setUUID($player->getUniqueId());
        $this->bossBar = (new BossBar())->addPlayer($player);
        $this->bossBar->hideFrom([$player]);
        $this->undoHistory = new \Ds\Deque();
        $this->redoHistory = new \Ds\Deque();
        Loader::getInstance()->getLogger()->debug("Created new session for player {$player->getName()}");
    }

    public function __destruct()
    {
        Loader::getInstance()->getLogger()->debug("Destructing session {$this->getUUID()} for user " . $this->getPlayer()->getName());
        $this->bossBar->removeAllPlayers();
    }

    /**
     * @param null|Player $player
     */
    public function setPlayer($player)
    {
        $this->player = $player;
    }

    /**
     * @return null|Player
     */
    public function getPlayer()
    {
        return $this->player;
    }

    /**
     * @return bool
     */
    public function isWandEnabled(): bool
    {
        return $this->wandEnabled;
    }

    /**
     * @param bool $wandEnabled
     * @return string
     */
    public function setWandEnabled(bool $wandEnabled)
    {
        $this->wandEnabled = $wandEnabled;
        return Loader::PREFIX . "The wand tool is now " . ($wandEnabled ? TF::GREEN . "enabled" : TF::RED . "disabled") . TF::RESET . "!";//TODO #translation
    }

    /**
     * @return bool
     */
    public function isDebugStickEnabled(): bool
    {
        return $this->debugStickEnabled;
    }

    /**
     * @param bool $debugStick
     * @return string
     */
    public function setDebugStickEnabled(bool $debugStick)
    {
        $this->debugStickEnabled = $debugStick;
        return Loader::PREFIX . "The debug stick is now " . ($debugStick ? TF::GREEN . "enabled" : TF::RED . "disabled") . TF::RESET . "!";//TODO #translation
    }

    /**
     * @return BossBar
     */
    public function getBossBar(): BossBar
    {
        return $this->bossBar;
    }

    /**
     * TODO exception for not a brush
     * @param Item $item
     * @return null|Brush
     * @throws \Exception
     */
    public function getBrushFromItem(Item $item): ?Brush
    {
        /** @var CompoundTag $entry */
        if (!is_null(($entry = $item->getNamedTagEntry(API::TAG_MAGIC_WE_BRUSH)))) {
            #var_dump(API::compoundToArray($entry));
            $version = $entry->getInt("version", 0);
            if ($version !== BrushProperties::VERSION) {
                throw new \Exception("Brush can not be restored - version mismatch");
            }
            /** @var BrushProperties $properties */
            $properties = json_decode($entry->getString("properties"));
            $uuid = UUID::fromString($properties->uuid);
            $brush = $this->getBrush($uuid);
            if ($brush instanceof Brush) {
                return $brush;
            }
            $brush = new Brush($properties);
            $this->addBrush($brush);
            if ($brush instanceof Brush) {
                return $brush;
            }
        } else {
            throw new \Exception("The item is not a valid brush!");
        }
        return null;
    }

    /**
     * TODO exception for not a brush
     * @param UUID $uuid
     * @return null|Brush
     */
    public function getBrush(UUID $uuid): ?Brush
    {
        return $this->brushes[$uuid->toString()] ?? null;
    }

    /**
     * TODO exception for not a brush
     * @param Brush $brush UUID will be set automatically
     * @return void
     */
    public function addBrush(Brush $brush): void
    {
        $this->brushes[$brush->properties->uuid] = $brush;
        $this->sendMessage("Added {$brush->getName()} to session (UUID {$brush->properties->uuid})");
    }

    /**
     * TODO exception for not a brush
     * @param Brush $brush UUID will be set automatically
     * @return void
     * @throws \InvalidArgumentException
     * @throws \xenialdan\MagicWE2\exception\ActionNotFoundException
     * @throws \xenialdan\MagicWE2\exception\ShapeNotFoundException
     */
    public function replaceBrush(Brush $brush): void
    {
        $this->brushes[$brush->properties->uuid] = $brush;
        $new = $brush->toItem();
        foreach ($this->getPlayer()->getInventory()->getContents() as $slot => $item) {
            /** @var CompoundTag $entry */
            if (!is_null(($entry = $item->getNamedTagEntry(API::TAG_MAGIC_WE_BRUSH)))) {
                if ($entry->getString("id") === $brush->properties->uuid) {
                    $this->getPlayer()->getInventory()->setItem($slot, $new);
                }
            }
        }
    }

    public function __toString()
    {
        return __CLASS__ .
            " UUID: " . $this->getUUID()->__toString() .
            " Player: " . $this->getPlayer()->getName() .
            " Wand tool enabled: " . ($this->isWandEnabled() ? "enabled" : "disabled") .
            " Debug tool enabled: " . ($this->isDebugStickEnabled() ? "enabled" : "disabled") .
            " BossBar: " . $this->getBossBar()->entityId .
            " Selections: " . count($this->getSelections()) .
            " Latest: " . $this->getLatestSelectionUUID() .
            " Clipboards: " . count($this->getClipboards()) .
            " Current: " . $this->getCurrentClipboardIndex() .
            " Undos: " . count($this->undoHistory) .
            " Redos: " . count($this->redoHistory) .
            " Brushes: " . count($this->brushes);
    }

    public function sendMessage(string $message)
    {
        $this->player->sendMessage(Loader::PREFIX . $message);
    }

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return [
            "uuid" => $this->getUUID()->toString(),
            "wandEnabled" => $this->wandEnabled,
            "debugStickEnabled" => $this->debugStickEnabled,
            "brushes" => $this->brushes,
            "latestSelection" => $this->getLatestSelection(),
            "currentClipboard" => $this->getCurrentClipboard()
        ];
    }

    public function save(): void
    {
        file_put_contents(Loader::getInstance()->getDataFolder() . "sessions" . DIRECTORY_SEPARATOR .
            $this->getPlayer()->getName() . ".json",
            json_encode($this, JSON_PRETTY_PRINT)
        );
    }
}