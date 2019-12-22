<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use Ds\Map;
use Exception;
use InvalidArgumentException;
use InvalidStateException;
use pocketmine\entity\Skin;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\UUID;
use RuntimeException;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\selection\shape\Cuboid;
use xenialdan\MagicWE2\session\PluginSession;
use xenialdan\MagicWE2\session\Session;
use xenialdan\MagicWE2\session\UserSession;
use xenialdan\MagicWE2\tool\Brush;
use xenialdan\MagicWE2\tool\BrushProperties;

class SessionHelper
{
    /** @var Map */
    private static $userSessions;
    /** @var Map */
    private static $pluginSessions;

    public static function init()
    {
        @mkdir(Loader::getInstance()->getDataFolder() . "sessions");
        self::$userSessions = new Map();
        self::$pluginSessions = new Map();
    }

    /**
     * @param Session $session
     * @throws InvalidStateException
     */
    public static function addSession(Session $session): void
    {
        if ($session instanceof UserSession) {
            self::$userSessions->put($session->getUUID(), $session);
            if (!empty(Loader::getInstance()->donatorData) && (($player = $session->getPlayer())->hasPermission("we.donator") || in_array($player->getName(), Loader::getInstance()->donators))) {
                $oldSkin = $player->getSkin();
                $newSkin = new Skin($oldSkin->getSkinId(), $oldSkin->getSkinData(), Loader::getInstance()->donatorData, $oldSkin->getGeometryName(), $oldSkin->getGeometryData());
                $player->setSkin($newSkin);
                $player->sendSkin();
            }
        } else if ($session instanceof PluginSession) self::$pluginSessions->put($session->getUUID(), $session);
    }

    /**
     * Destroys a session and removes it from cache. Saves to file if $save is true
     * @param Session $session
     * @param bool $save
     */
    public static function destroySession(Session $session, bool $save = true)
    {
        if ($session instanceof UserSession) {
            $session->cleanupInventory();
            self::$userSessions->remove($session->getUUID());
        } else if ($session instanceof PluginSession) self::$pluginSessions->remove($session->getUUID());
        /** @noinspection PhpStatementHasEmptyBodyInspection */
        if ($save && $session instanceof UserSession) {
            $session->save();
        }
        unset($session);
    }

    /**
     * Creates an UserSession used to execute MagicWE2's functions
     * @param Player $player
     * @param bool $add If true, the session will be cached in SessionHelper
     * @return UserSession
     * @throws InvalidStateException
     * @throws SessionException
     */
    public static function createUserSession(Player $player, bool $add = true): UserSession
    {
        if (!$player->hasPermission("we.session")) throw new SessionException(TF::RED . "You do not have the permission \"magicwe.session\"");
        $session = new UserSession($player);
        if ($add) self::addSession($session);
        return $session;
    }

    /**
     * Creates a PluginSession used to call API functions via a plugin
     * @param Plugin $plugin
     * @param bool $add If true, the session will be cached in SessionHelper
     * @return PluginSession
     * @throws InvalidStateException
     */
    public static function createPluginSession(Plugin $plugin, bool $add = true): PluginSession
    {
        $session = new PluginSession($plugin);
        if ($add) self::addSession($session);
        return $session;
    }

    /**
     * @param Player $player
     * @return bool
     */
    public static function hasSession(Player $player): bool
    {
        try {
            return self::getUserSession($player) instanceof UserSession;
        } catch (SessionException $exception) {
            return false;
        }
    }

    /**
     * @param Player $player
     * @return null|UserSession
     * @throws SessionException
     */
    public static function getUserSession(Player $player): ?UserSession
    {
        if (self::$userSessions->isEmpty()) return null;
        $filtered = self::$userSessions->filter(function (UUID $uuid, Session $session) use ($player) {
            return $session instanceof UserSession && $session->getPlayer() === $player;
        });
        if ($filtered->isEmpty()) return null;
        if (count($filtered) > 1) throw new SessionException("Multiple sessions found for player {$player->getName()}. This should never happen!");
        return $filtered->values()->first();
    }

    /**
     * TODO cleanup or optimize
     * @param UUID $uuid
     * @return null|Session
     * @throws SessionException
     */
    public static function getSessionByUUID(UUID $uuid): ?Session
    {
        $v = null;
        if (self::$userSessions->hasKey($uuid)) {
            $v = self::$userSessions->get($uuid, null);
        } else if (self::$pluginSessions->hasKey($uuid)) {
            $v = self::$pluginSessions->get($uuid, null);
        } else {
            /*
             * Sadly, this part is necessary. If you use UUID::fromString, the object "id" in the map does not match anymore
             */
            $userFiltered = self::$userSessions->filter(function (UUID $uuid2, Session $session) use ($uuid) {
                return $uuid2->equals($uuid);
            });
            if (!$userFiltered->isEmpty()) $v = $userFiltered->values()->first();
            else {
                $pluginFiltered = self::$pluginSessions->filter(function (UUID $uuid2, Session $session) use ($uuid) {
                    return $uuid2->equals($uuid);
                });
                if (!$pluginFiltered->isEmpty()) $v = $pluginFiltered->values()->first();
            }
        }
        if (!$v instanceof Session) throw new SessionException("Session with uuid {$uuid->toString()} not found");
        return $v;
    }

    /**
     * @return array|UserSession[]
     */
    public static function getUserSessions(): array
    {
        return self::$userSessions->values()->toArray();
    }

    /**
     * @return array|PluginSession[]
     */
    public static function getPluginSessions(): array
    {
        return self::$pluginSessions->values()->toArray();
    }

    /**
     * @param Player $player
     * @return UserSession|null
     * @throws InvalidStateException
     */
    public static function loadUserSession(Player $player): ?UserSession
    {
        $path = Loader::getInstance()->getDataFolder() . "sessions" . DIRECTORY_SEPARATOR .
            $player->getName() . ".json";
        if (!file_exists($path)) return null;
        $contents = file_get_contents($path);
        if ($contents === false) return null;
        $data = json_decode($contents, true);
        if (is_null($data) || json_last_error() !== JSON_ERROR_NONE) {
            Loader::getInstance()->getLogger()->error("Could not load user session from json file {$path}: " . json_last_error_msg());
            #unlink($path);//TODO make safe
            return null;
        }
        $session = new UserSession($player);
        try {
            $session->setUUID(UUID::fromString($data["uuid"]));
            $session->setWandEnabled($data["wandEnabled"]);
            $session->setDebugToolEnabled($data["debugToolEnabled"]);
            $session->setLanguage($data["language"]);
            foreach ($data["brushes"] as $brushUUID => $brushJson) {
                try {
                    $properties = BrushProperties::fromJson($brushJson["properties"]);
                    $brush = new Brush($properties);
                    $session->addBrush($brush);
                } catch (InvalidArgumentException $e) {
                    continue;
                }
            };
            if (!is_null(($latestSelection = $data["latestSelection"] ?? null))) {
                try {
                    $selection = new Selection(
                        $session->getUUID(),
                        Server::getInstance()->getLevel($latestSelection["levelid"]),
                        $latestSelection["pos1"]["x"],
                        $latestSelection["pos1"]["y"],
                        $latestSelection["pos1"]["z"],
                        $latestSelection["pos2"]["x"],
                        $latestSelection["pos2"]["y"],
                        $latestSelection["pos2"]["z"]
                    );
                    $shapeClass = $latestSelection["shapeClass"] ?? Cuboid::class;
                    $pasteVector = $latestSelection["shape"]["pasteVector"];
                    unset($latestSelection["shape"]["pasteVector"]);
                    if (!is_null($pasteVector)) {
                        $pasteV = new Vector3(...array_values($pasteVector));
                        $shape = new $shapeClass($pasteV, ...array_values($latestSelection["shape"]));
                        $selection->setShape($shape);
                        $session->addSelection($selection);
                    }
                } catch (RuntimeException $e) {
                }
            }
            //TODO clipboard
        } catch (Exception $exception) {
            return null;
        }
        self::addSession($session);
        return $session;
    }

}