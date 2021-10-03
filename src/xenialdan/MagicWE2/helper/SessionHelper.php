<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use Exception;
use InvalidArgumentException;
use JsonException;
use pocketmine\entity\InvalidSkinException;
use pocketmine\entity\Skin;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use Ramsey\Uuid\Rfc4122\UuidV4;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use xenialdan\MagicWE2\event\MWESessionLoadEvent;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\selection\shape\Cuboid;
use xenialdan\MagicWE2\session\PluginSession;
use xenialdan\MagicWE2\session\Session;
use xenialdan\MagicWE2\session\UserSession;
use xenialdan\MagicWE2\tool\Brush;
use xenialdan\MagicWE2\tool\BrushProperties;
use function array_filter;
use function array_values;
use function count;

class SessionHelper
{
	/** @var array<string,UserSession> */
	private static array $userSessions = [];
	/** @var array<string,PluginSession> */
	private static array $pluginSessions = [];

	public static function init(): void
	{
		if (!@mkdir($concurrentDirectory = Loader::getInstance()->getDataFolder() . "sessions") && !is_dir($concurrentDirectory)) {
			throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
		}
	}

	/**
	 * @param Session $session
	 * @throws InvalidSkinException
	 */
	public static function addSession(Session $session): void
	{
		if ($session instanceof UserSession) {
			self::$userSessions[$session->getUUID()->toString()] = $session;
			if (!empty(Loader::getInstance()->donatorData) && (($player = $session->getPlayer())->hasPermission("we.donator") || in_array($player->getName(), Loader::getInstance()->donators))) {
				$oldSkin = $player->getSkin();
				$newSkin = new Skin($oldSkin->getSkinId(), $oldSkin->getSkinData(), Loader::getInstance()->donatorData, $oldSkin->getGeometryName(), $oldSkin->getGeometryData());
				$player->setSkin($newSkin);
				$player->sendSkin();
			}
		} else if ($session instanceof PluginSession) self::$pluginSessions[$session->getUUID()->toString()] = $session;
	}

	/**
	 * Destroys a session and removes it from cache. Saves to file if $save is true
	 * @param Session $session
	 * @param bool $save
	 * @throws JsonException
	 */
	public static function destroySession(Session $session, bool $save = true): void
	{
		if ($session instanceof UserSession) {
			$session->cleanupInventory();
			unset(self::$userSessions[$session->getUUID()->toString()]);
		} else if ($session instanceof PluginSession) unset(self::$pluginSessions[($session->getUUID()->toString())]);
		if ($save && $session instanceof UserSession) {
			$session->save();
		}
	}

	/**
	 * Creates an UserSession used to execute MagicWE2's functions
	 * @param Player $player
	 * @param bool $add If true, the session will be cached in SessionHelper
	 * @return UserSession
	 * @throws InvalidSkinException
	 * @throws RuntimeException
	 * @throws SessionException
	 */
	public static function createUserSession(Player $player, bool $add = true): UserSession
	{
		if (!$player->hasPermission("we.session")) throw new SessionException(TF::RED . "You do not have the permission \"magicwe.session\"");
		$session = new UserSession($player);
		if ($add) {
			self::addSession($session);
			(new MWESessionLoadEvent(Loader::getInstance(), $session))->call();
		}
		return $session;
	}

	/**
	 * Creates a PluginSession used to call API functions via a plugin
	 * @param Plugin $plugin
	 * @param bool $add If true, the session will be cached in SessionHelper
	 * @return PluginSession
	 * @throws InvalidSkinException
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
		if (count(self::$userSessions) === 0) return null;
		$filtered = array_filter(self::$userSessions, function (Session $session) use ($player) {
			return $session instanceof UserSession && $session->getPlayer() === $player;
		});
		if (count($filtered) === 0) return null;
		if (count($filtered) > 1) throw new SessionException("Multiple sessions found for player {$player->getName()}. This should never happen!");
		return array_values($filtered)[0];
	}

	/**
	 * TODO cleanup or optimize
	 * @param UuidInterface $uuid
	 * @return null|Session
	 * @throws SessionException
	 */
	public static function getSessionByUUID(UuidInterface $uuid): ?Session
	{
		$v = self::$userSessions[$uuid->toString()] ?? self::$pluginSessions[$uuid->toString()] ?? null;
		if (!$v instanceof Session) throw new SessionException("Session with uuid {$uuid->toString()} not found");
		return $v;
	}

	/**
	 * @return array
	 */
	public static function getUserSessions(): array
	{
		return self::$userSessions;
	}

	/**
	 * @return array
	 */
	public static function getPluginSessions(): array
	{
		return self::$pluginSessions;
	}

	/**
	 * @param Player $player
	 * @return UserSession|null
	 * @throws InvalidSkinException
	 * @throws JsonException
	 * @throws RuntimeException
	 */
	public static function loadUserSession(Player $player): ?UserSession
	{
		$path = Loader::getInstance()->getDataFolder() . "sessions" . DIRECTORY_SEPARATOR .
			$player->getName() . ".json";
		if (!file_exists($path)) return null;
		$contents = file_get_contents($path);
		if ($contents === false) return null;
		$data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
		if (is_null($data) || json_last_error() !== JSON_ERROR_NONE) {
			Loader::getInstance()->getLogger()->error("Could not load user session from json file $path: " . json_last_error_msg());
			#unlink($path);//TODO make safe
			return null;
		}
		$session = new UserSession($player);
		try {
			$session->setUUID(UuidV4::fromString($data["uuid"]));
			$session->setWandEnabled($data["wandEnabled"]);
			$session->setDebugToolEnabled($data["debugToolEnabled"]);
			$session->setWailaEnabled($data["wailaEnabled"]);
			$session->setSidebarEnabled($data["sidebarEnabled"]);
			$session->setLanguage($data["language"]);
			foreach ($data["brushes"] as $brushUUID => $brushJson) {
				try {
					$properties = BrushProperties::fromJson($brushJson["properties"]);
					$brush = new Brush($properties);
					$session->getBrushes()->addBrush($brush);
				} catch (InvalidArgumentException $e) {
					continue;
				}
			}
			if (!is_null(($latestSelection = $data["latestSelection"] ?? null))) {
				try {
					$world = Server::getInstance()->getWorldManager()->getWorld($latestSelection["worldId"]);
					if (is_null($world)) {
						$session->sendMessage(TF::RED . "The world of the saved sessions selection is not loaded, the last selection was not restored.");//TODO translate better
					} else {
						$shapeClass = $latestSelection["shapeClass"] ?? Cuboid::class;
						$pasteVector = $latestSelection["shape"]["pasteVector"];
						unset($latestSelection["shape"]["pasteVector"]);
						if (!is_null($pasteVector)) {
							$pasteV = new Vector3(...array_values($pasteVector));
							$shape = new $shapeClass($pasteV, ...array_values($latestSelection["shape"]));
						}
						$selection = new Selection(
							$session->getUUID(),
							Server::getInstance()->getWorldManager()->getWorld($latestSelection["worldId"]),
							$latestSelection["pos1"]["x"],
							$latestSelection["pos1"]["y"],
							$latestSelection["pos1"]["z"],
							$latestSelection["pos2"]["x"],
							$latestSelection["pos2"]["y"],
							$latestSelection["pos2"]["z"],
							$shape ?? null
						);
						if ($selection->isValid()) {
							$session->addSelection($selection);
						}
					}
				} catch (RuntimeException $e) {
				}
			}
			//TODO clipboard
		} catch (Exception $exception) {
			return null;
		}
		self::addSession($session);
		(new MWESessionLoadEvent(Loader::getInstance(), $session))->call();
		return $session;
	}

}