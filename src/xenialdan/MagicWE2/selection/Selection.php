<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\selection;

use Exception;
use JsonSerializable;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\Position;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use Serializable;
use xenialdan\MagicWE2\event\MWESelectionChangeEvent;
use xenialdan\MagicWE2\exception\SelectionException;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\AsyncWorld;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\helper\SubChunkIterator;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\shape\Cuboid;
use xenialdan\MagicWE2\selection\shape\Shape;
use xenialdan\MagicWE2\session\Session;

/**
 * Class Selection
 * @package xenialdan\MagicWE2
 */
class Selection implements Serializable, JsonSerializable{
	public ?int $worldId = null;
	public ?Vector3 $pos1 = null;
	public ?Vector3 $pos2 = null;
	public UuidInterface $uuid;
	public UuidInterface $sessionUUID;
	public ?Shape $shape = null;

	private SubChunkIterator $iterator;

	public function __construct(UuidInterface $sessionUUID, World $world, ?Vector3 $minPos = null, ?Vector3 $maxPos = null, ?Shape $shape = null){
		$this->sessionUUID = $sessionUUID;
		$this->worldId = $world->getId();
		if($minPos !== null) $minPos = $minPos->floor();
		if($maxPos !== null) $maxPos = $maxPos->floor();
		$this->pos1 = $minPos;
		$this->pos2 = $maxPos;
		$this->shape = $shape;
		$this->setUUID(Uuid::uuid4());

		try{
			(new MWESelectionChangeEvent($this, MWESelectionChangeEvent::TYPE_CREATE))->call();
		}catch(RuntimeException $e){
			Loader::getInstance()->getLogger()->logException($e);
		}
	}

	public function free() : void{
		$this->iterator->invalidate();
		$manager = $this->iterator->getManager();
		if($manager instanceof AsyncWorld) $manager->cleanChunks();
	}

	/**
	 * @return World
	 * @throws SelectionException|RuntimeException
	 */
	public function getWorld() : World{
		if(is_null($this->worldId)){
			throw new SelectionException("World is not set!");
		}
		$world = Server::getInstance()->getWorldManager()->getWorld($this->worldId);
		if(is_null($world)){
			throw new SelectionException("World is not found!");
		}
		return $world;
	}

	public function setWorld(World $world) : void{
		if($this->worldId === $world->getId()){
			return;
		}
		$this->worldId = $world->getId();
		try{
			(new MWESelectionChangeEvent($this, MWESelectionChangeEvent::TYPE_WORLD))->call();
		}catch(RuntimeException $e){
			Loader::getInstance()->getLogger()->logException($e);
		}
//		$this->free();
//		$manager = $this->getIterator()->getManager();
//		if($manager instanceof AsyncWorld) $manager->copyChunks($this);
	}

	/**
	 * @return Position
	 * @throws SelectionException|RuntimeException
	 */
	public function getPos1() : Position{
		if(is_null($this->pos1)){
			throw new SelectionException("Position 1 is not set!");
		}
		return Position::fromObject($this->pos1, $this->getWorld());
	}

	/**
	 * @param Position $position
	 *
	 * @throws AssumptionFailedError
	 */
	public function setPos1(Position $position) : void{
		$this->pos1 = $position->asVector3()->floor();
		if($this->pos1->y > World::Y_MAX) $this->pos1->y = World::Y_MAX;//TODO check if this should be 255 or World::Y_MAX
		if($this->pos1->y < World::Y_MIN) $this->pos1->y = World::Y_MIN;
		if($this->worldId !== $position->getWorld()->getId()){//reset other position if in different world
			$this->pos2 = null;
		}
		$this->setWorld($position->getWorld());
		if(($this->shape === null || $this->shape instanceof Cuboid) && $this->isValid())
			$this->setShape(Cuboid::constructFromPositions($this->pos1, $this->pos2));
		try{
			$session = SessionHelper::getSessionByUUID($this->sessionUUID);
			if($session instanceof Session){
				$session->sendMessage(TF::GREEN . $session->getLanguage()->translateString('selection.pos1.set', [$this->pos1->getX(), $this->pos1->getY(), $this->pos1->getZ()]));
				try{
					(new MWESelectionChangeEvent($this, MWESelectionChangeEvent::TYPE_POS1))->call();
				}catch(RuntimeException $e){
					Loader::getInstance()->getLogger()->logException($e);
				}
//				$this->free();
//				$manager = $this->getIterator()->getManager();
//				if($manager instanceof AsyncWorld) $manager->copyChunks($this);
			}
		}catch(SessionException){
			//TODO log? kick?
		}catch(SelectionException | RuntimeException $e){
			Loader::getInstance()->getLogger()->logException($e);
		}
	}

	/**
	 * @return Position
	 * @throws SelectionException|RuntimeException
	 */
	public function getPos2() : Position{
		if(is_null($this->pos2)){
			throw new SelectionException("Position 2 is not set!");
		}
		return Position::fromObject($this->pos2, $this->getWorld());
	}

	/**
	 * @param Position $position
	 */
	public function setPos2(Position $position) : void{
		$this->pos2 = $position->asVector3()->floor();
		if($this->pos2->y > World::Y_MAX) $this->pos2->y = World::Y_MAX;
		if($this->pos2->y < World::Y_MIN) $this->pos2->y = World::Y_MIN;
		if($this->worldId !== $position->getWorld()->getId()){
			$this->pos1 = null;
		}
		$this->setWorld($position->getWorld());
		if(($this->shape === null || $this->shape instanceof Cuboid) && $this->isValid())
			$this->setShape(Cuboid::constructFromPositions($this->pos1, $this->pos2));
		try{
			$session = SessionHelper::getSessionByUUID($this->sessionUUID);
			if($session instanceof Session){
				$session->sendMessage(TF::GREEN . $session->getLanguage()->translateString('selection.pos2.set', [$this->pos2->getX(), $this->pos2->getY(), $this->pos2->getZ()]));
				try{
					(new MWESelectionChangeEvent($this, MWESelectionChangeEvent::TYPE_POS2))->call();
				}catch(RuntimeException $e){
					Loader::getInstance()->getLogger()->logException($e);
				}
//				$this->free();
//				$manager = $this->getIterator()->getManager();
//				if($manager instanceof AsyncWorld) $manager->copyChunks($this);
			}
		}catch(SessionException | SelectionException | RuntimeException $e){//TODO log? kick?
			Loader::getInstance()->getLogger()->logException($e);
		}
	}

	/**
	 * @return Shape
	 * @throws SelectionException
	 */
	public function getShape() : Shape{
		if(!$this->shape instanceof Shape) throw new SelectionException("Shape is not valid");
		return $this->shape;
	}

	public function setShape(Shape $shape) : void{
//		var_dump($shape);
		$this->shape = $shape;
		try{
			(new MWESelectionChangeEvent($this, MWESelectionChangeEvent::TYPE_SHAPE))->call();//might cause duplicated call
//			$this->free();
//			$manager = $this->getIterator()->getManager();
//			if($manager instanceof AsyncWorld) $manager->copyChunks($this);
		}catch(RuntimeException | SelectionException $e){
			Loader::getInstance()->getLogger()->debug($e->getMessage());
		}
	}

	/**
	 * Checks if a Selection is valid. It is not valid if:
	 * - The world is not set
	 * - Any of the positions are not set
	 * - The shape is not set / not a shape
	 * - The positions are not in the same world
	 * @return bool
	 */
	public function isValid() : bool{
		try{
//			var_dump("World: " . $this->getWorld()->getId() . " Pos1: " . $this->pos1 . " Pos2: " . $this->pos2 . " Shape: " . $this->shape?->serialize());
			//$this->getShape();
			$this->getWorld();
			$this->getPos1();
			$this->getPos2();
		}catch(Exception $e){
			Loader::getInstance()->getLogger()->debug($e->getMessage());
			return false;
		}
		return true;
	}

	public function getSizeX() : int{
		return (int) (abs($this->pos1->x - $this->pos2->x) + 1);
	}

	public function getSizeY() : int{
		return (int) (abs($this->pos1->y - $this->pos2->y) + 1);
	}

	public function getSizeZ() : int{
		return (int) (abs($this->pos1->z - $this->pos2->z) + 1);
	}

	public function setUUID(UuidInterface $uuid) : void{
		$this->uuid = $uuid;
	}

	public function getUUID() : UuidInterface{
		return $this->uuid;
	}

	public function getIterator(bool $copyChunks = true) : SubChunkIterator{
		$manager = new AsyncWorld();
		if($copyChunks) $manager->copyChunks($this);
		return new SubChunkIterator($manager);
	}

	/**
	 * String representation of object
	 * @link http://php.net/manual/en/serializable.serialize.php
	 * @return string the string representation of the object or null
	 * @since 5.1.0
	 */
	public function serialize() : string{
		return serialize([
			$this->worldId,
			$this->pos1,
			$this->pos2,
			$this->uuid,
			$this->sessionUUID,
			$this->shape,
//			$this->iterator,
		]);
	}

	/**
	 * Constructs the object
	 * @link http://php.net/manual/en/serializable.unserialize.php
	 *
	 * @param string $data <p>
	 * The string representation of the object.
	 * </p>
	 *
	 * @return void
	 * @since 5.1.0
	 */
	public function unserialize(string $data){
		[
			$this->worldId,
			$this->pos1,
			$this->pos2,
			$this->uuid,
			$this->sessionUUID,
			$this->shape,
//			$this->iterator
		] = unserialize($data/*, ['allowed_classes' => [__CLASS__, Vector3::class,UuidInterface::class,Shape::class]]*/);//TODO test pm4
	}

	/**
	 * Specify data which should be serialized to JSON
	 * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
	 * @return array data which can be serialized by <b>json_encode</b>,
	 * which is a value of any type other than a resource.
	 * @since 5.4.0
	 */
	public function jsonSerialize() : array{
		$arr = (array) $this;
		if($this->shape !== null)
			$arr["shapeClass"] = get_class($this->shape);
		return $arr;
	}
}