<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\selection;

use Exception;
use JsonSerializable;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use pocketmine\uuid\UUID;
use pocketmine\world\Position;
use pocketmine\world\World;
use RuntimeException;
use Serializable;
use xenialdan\MagicWE2\event\MWESelectionChangeEvent;
use xenialdan\MagicWE2\exception\SelectionException;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\selection\shape\Cuboid;
use xenialdan\MagicWE2\selection\shape\Shape;
use xenialdan\MagicWE2\session\Session;

/**
 * Class Selection
 * @package xenialdan\MagicWE2
 */
class Selection implements Serializable, JsonSerializable
{
	/** @var int|null */
	public $worldId;
	/** @var Vector3|null */
	public $pos1;
	/** @var Vector3|null */
	public $pos2;
	/** @var UUID */
	public $uuid;
	/** @var UUID */
	public $sessionUUID;
	/** @var Shape|null */
	public $shape;

	/**
	 * Selection constructor.
	 * @param UUID $sessionUUID
	 * @param World $world
	 * @param ?int $minX
	 * @param ?int $minY
	 * @param ?int $minZ
	 * @param ?int $maxX
	 * @param ?int $maxY
	 * @param ?int $maxZ
	 * @param ?Shape $shape
	 */
	public function __construct(UUID $sessionUUID, World $world, $minX = null, $minY = null, $minZ = null, $maxX = null, $maxY = null, $maxZ = null, ?Shape $shape = null)
	{
		$this->sessionUUID = $sessionUUID;
		$this->worldId = $world->getId();
		if (isset($minX, $minY, $minZ)) {
			$this->pos1 = (new Vector3($minX, $minY, $minZ))->floor();
		}
		if (isset($maxX, $maxY, $maxZ)) {
			$this->pos2 = (new Vector3($maxX, $maxY, $maxZ))->floor();
		}
		if ($shape !== null) $this->shape = $shape;
		$this->setUUID(UUID::fromRandom());
	}

	/**
	 * @return World
	 * @throws Exception
	 */
	public function getWorld(): World
	{
		if (is_null($this->worldId)) {
			throw new SelectionException("World is not set!");
		}
		$world = Server::getInstance()->getWorldManager()->getWorld($this->worldId);
		if (is_null($world)) {
			throw new SelectionException("World is not found!");
		}
		return $world;
	}

	/**
	 * @param World $world
	 */
	public function setWorld(World $world): void
	{
		$this->worldId = $world->getId();
		try {
			(new MWESelectionChangeEvent($this, MWESelectionChangeEvent::TYPE_WORLD))->call();
		} catch (RuntimeException $e) {
		}
	}

	/**
	 * @return Position
	 * @throws Exception
	 */
	public function getPos1(): Position
	{
		if (is_null($this->pos1)) {
			throw new SelectionException("Position 1 is not set!");
		}
		return Position::fromObject($this->pos1, $this->getWorld());
	}

	/**
	 * @param Position $position
	 * @throws AssumptionFailedError
	 */
	public function setPos1(Position $position): void
	{
		$this->pos1 = $position->asVector3()->floor();
		if ($this->pos1->y >= World::Y_MAX) $this->pos1->y = World::Y_MAX;
		if ($this->pos1->y < 0) $this->pos1->y = 0;
		if ($this->worldId !== $position->getWorld()->getId()) {//reset other position if in different world
			$this->pos2 = null;
		}
		$this->setWorld($position->getWorld());
		if (($this->shape instanceof Cuboid || $this->shape === null) && $this->isValid())//TODO test change
			$this->setShape(Cuboid::constructFromPositions($this->pos1, $this->pos2));
		try {
			$session = SessionHelper::getSessionByUUID($this->sessionUUID);
			if ($session instanceof Session) {
				$session->sendMessage(TF::GREEN . $session->getLanguage()->translateString('selection.pos1.set', [$this->pos1->getX(), $this->pos1->getY(), $this->pos1->getZ()]));
				try {
					(new MWESelectionChangeEvent($this, MWESelectionChangeEvent::TYPE_POS1))->call();
				} catch (RuntimeException $e) {
				}
			}
		} catch (SessionException $e) {
			//TODO log? kick?
		}
	}

	/**
	 * @return Position
	 * @throws Exception
	 */
	public function getPos2(): Position
	{
		if (is_null($this->pos2)) {
			throw new SelectionException("Position 2 is not set!");
		}
		return Position::fromObject($this->pos2, $this->getWorld());
	}

	/**
	 * @param Position $position
	 * @throws AssumptionFailedError
	 */
	public function setPos2(Position $position): void
	{
		$this->pos2 = $position->asVector3()->floor();
		if ($this->pos2->y >= World::Y_MAX) $this->pos2->y = World::Y_MAX;
		if ($this->pos2->y < 0) $this->pos2->y = 0;
		if ($this->worldId !== $position->getWorld()->getId()) {
			$this->pos1 = null;
		}
		$this->setWorld($position->getWorld());
		if (($this->shape instanceof Cuboid || $this->shape === null) && $this->isValid())
			$this->setShape(Cuboid::constructFromPositions($this->pos1, $this->pos2));
		try {
			$session = SessionHelper::getSessionByUUID($this->sessionUUID);
			if ($session instanceof Session) {
				$session->sendMessage(TF::GREEN . $session->getLanguage()->translateString('selection.pos2.set', [$this->pos2->getX(), $this->pos2->getY(), $this->pos2->getZ()]));
				try {
					(new MWESelectionChangeEvent($this, MWESelectionChangeEvent::TYPE_POS2))->call();
				} catch (RuntimeException $e) {
				}
			}
		} catch (SessionException $e) {
			//TODO log? kick?
		}
	}

	/**
	 * @return Shape
	 * @throws Exception
	 */
	public function getShape(): Shape
	{
		if (!$this->shape instanceof Shape) throw new SelectionException("Shape is not valid");
		return $this->shape;
	}

	/**
	 * @param Shape $shape
	 */
	public function setShape(Shape $shape): void
	{
		$this->shape = $shape;
		try {
			(new MWESelectionChangeEvent($this, MWESelectionChangeEvent::TYPE_SHAPE))->call();
		} catch (RuntimeException $e) {
		}//might cause duplicated call
	}

	/**
	 * Checks if a Selection is valid. It is not valid if:
	 * - The world is not set
	 * - Any of the positions are not set
	 * - The shape is not set / not a shape
	 * - The positions are not in the same world
	 * @return bool
	 */
	public function isValid(): bool
	{
		try {
			#$this->getShape();
			$this->getWorld();
			$this->getPos1();
			$this->getPos2();
		} catch (Exception $e) {
			return false;
		}
		return true;
	}

	/**
	 * @return int
	 */
	public function getSizeX(): int
	{
		return (int)(abs($this->pos1->x - $this->pos2->x) + 1);
	}

	/**
	 * @return int
	 */
	public function getSizeY(): int
	{
		return (int)(abs($this->pos1->y - $this->pos2->y) + 1);
	}

	/**
	 * @return int
	 */
	public function getSizeZ(): int
	{
		return (int)(abs($this->pos1->z - $this->pos2->z) + 1);
	}

	/**
	 * @param UUID $uuid
	 */
	public function setUUID(UUID $uuid): void
	{
		$this->uuid = $uuid;
	}

	/**
	 * @return UUID
	 */
	public function getUUID(): UUID
	{
		return $this->uuid;
	}

	/**
	 * String representation of object
	 * @link http://php.net/manual/en/serializable.serialize.php
	 * @return string the string representation of the object or null
	 * @since 5.1.0
	 */
	public function serialize()
	{
		return serialize([
			$this->worldId,
			$this->pos1,
			$this->pos2,
			$this->uuid,
			$this->sessionUUID,
			$this->shape
		]);
	}

	/**
	 * Constructs the object
	 * @link http://php.net/manual/en/serializable.unserialize.php
	 * @param string $serialized <p>
	 * The string representation of the object.
	 * </p>
	 * @return void
	 * @since 5.1.0
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function unserialize($serialized)
	{
		var_dump($serialized);
		[
			$this->worldId,
			$this->pos1,
			$this->pos2,
			$this->uuid,
			$this->sessionUUID,
			$this->shape
		] = unserialize($serialized/*, ['allowed_classes' => [__CLASS__, Vector3::class,UUID::class,Shape::class]]*/);//TODO test pm4
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
		$arr = (array)$this;
		if ($this->shape !== null)
			$arr["shapeClass"] = get_class($this->shape);
		return $arr;
	}
}