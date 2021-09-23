<?php /** @noinspection PhpPrivateFieldCanBeLocalVariableInspection */

namespace xenialdan\MagicWE2\tool;

use Exception;
use Generator;
use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\math\Facing;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\World;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\AsyncChunkManager;
use xenialdan\MagicWE2\helper\BlockPalette;

class Flood extends WETool
{
	/** @var int */
	private int $limit;
	/** @var Block[] */
	private array $walked = [];
	/** @var Block[] */
	private array $nextToCheck = [];
	/** @var int */
	private int $y;

	/**
	 * Square constructor.
	 * @param int $limit
	 */
	public function __construct(int $limit)
	{
		$this->limit = $limit;
	}

	/**
	 * Returns the blocks by their actual position
	 * @param World|AsyncChunkManager $manager The world or AsyncChunkManager
	 * @param BlockPalette $filterblocks If not empty, applying a filter on the block list
	 * @param int $flags
	 * @return Generator
	 * @throws Exception
	 */
	public function getBlocks(AsyncChunkManager|World $manager, BlockPalette $filterblocks, int $flags = API::FLAG_BASE): Generator
	{
		$this->validateChunkManager($manager);
		$this->y = $this->getCenter()->getFloorY();
		$block = $manager->getBlockAt($this->getCenter()->getFloorX(), $this->getCenter()->getFloorY(), $this->getCenter()->getFloorZ());
		//$block = API::setComponents($block,$this->getCenter()->getFloorX(), $this->getCenter()->getFloorY(), $this->getCenter()->getFloorZ());
		$this->walked[] = $block;
		$this->nextToCheck = $this->walked;
		foreach ($this->walk($manager) as $block) {
			yield $block;
		}
	}

	/**
	 * Returns a flat layer of all included x z positions in selection
	 * @param World|AsyncChunkManager $manager The world or AsyncChunkManager
	 * @param int $flags
	 * @return Generator
	 * @throws Exception
	 */
	public function getLayer(AsyncChunkManager|World $manager, int $flags = API::FLAG_BASE): Generator
	{
		$this->validateChunkManager($manager);
		foreach ($this->getBlocks($manager, BlockPalette::CREATE()) as $block) {
			yield new Vector2($block->getPosition()->x, $block->getPosition()->z);
		}
	}

	/**
	 * @param World|AsyncChunkManager $manager
	 * @return Block[]
	 * @throws InvalidArgumentException
	 * @noinspection SlowArrayOperationsInLoopInspection
	 */
	private function walk(AsyncChunkManager|World $manager): array
	{
		$this->validateChunkManager($manager);
		/** @var Block[] $walkTo */
		$walkTo = [];
		foreach ($this->nextToCheck as $next) {
			$sides = iterator_to_array($this->getHorizontalSides($manager, $next->getPosition()));
			$walkTo = array_merge($walkTo, array_filter($sides, function (Block $side) use ($walkTo) {
				return $side->getId() === 0 && !in_array($side, $walkTo, true) && !in_array($side, $this->walked, true) && !in_array($side, $this->nextToCheck, true) && $side->getPosition()->distanceSquared($this->getCenter()) <= ($this->limit / M_PI);
			}));
		}
		$this->walked = array_merge($this->walked, $walkTo);
		$this->nextToCheck = $walkTo;
		if (!empty($this->nextToCheck)) $this->walk($manager);
		return $this->walked;
	}

	/**
	 * @param World|AsyncChunkManager $manager
	 * @param Vector3 $vector3
	 * @return Generator
	 * @throws InvalidArgumentException
	 */
	private function getHorizontalSides(AsyncChunkManager|World $manager, Vector3 $vector3): Generator
	{
		$this->validateChunkManager($manager);
		foreach ([Facing::NORTH, Facing::SOUTH, Facing::WEST, Facing::EAST] as $vSide) {
			$side = $vector3->getSide($vSide);
			if ($manager->getChunk($side->x >> 4, $side->z >> 4) === null) continue;
			//$block = API::setComponents($block,$side->x, $side->y, $side->z);
			yield $manager->getBlockAt($side->getFloorX(), $side->getFloorY(), $side->getFloorZ());
		}
	}

	public function getTotalCount(): int
	{
		return $this->limit;
	}

	/**
	 * @param World|AsyncChunkManager $chunkManager
	 * @return array
	 * @throws InvalidArgumentException
	 */
	public function getTouchedChunks(AsyncChunkManager|World $chunkManager): array
	{
		$this->validateChunkManager($chunkManager);
		$maxRadius = sqrt($this->limit / M_PI);
		$v2center = new Vector2($this->getCenter()->x, $this->getCenter()->z);
		$cv2center = new Vector2($this->getCenter()->x >> 4, $this->getCenter()->z >> 4);
		$maxX = ($v2center->x + $maxRadius) >> 4;
		$minX = ($v2center->x - $maxRadius) >> 4;
		$maxZ = ($v2center->y + $maxRadius) >> 4;
		$minZ = ($v2center->y - $maxRadius) >> 4;
		$cmaxRadius = $cv2center->distanceSquared($minX - 0.5, $minZ - 0.5);
		#print "from $minX:$minZ to $maxX:$maxZ" . PHP_EOL;
		$touchedChunks = [];
		for ($x = $minX - 1; $x <= $maxX + 1; $x++) {
			for ($z = $minZ - 1; $z <= $maxZ + 1; $z++) {
				if ($cv2center->distanceSquared($x, $z) > $cmaxRadius) continue;
				$chunk = $chunkManager->getChunk($x, $z);
				if ($chunk === null) {
					continue;
				}
				#print "Touched Chunk at: $x:$z" . PHP_EOL;
				$touchedChunks[World::chunkHash($x, $z)] = FastChunkSerializer::serialize($chunk);
			}
		}
		#print "Touched chunks count: " . count($touchedChunks) . PHP_EOL;;
		return $touchedChunks;
	}

	public function getName(): string
	{
		return "Flood Fill";
	}

	/**
	 * @param mixed $manager
	 * @throws InvalidArgumentException
	 */
	public function validateChunkManager($manager): void
	{
		if (!$manager instanceof World && !$manager instanceof AsyncChunkManager) throw new InvalidArgumentException(get_class($manager) . " is not an instance of World or AsyncChunkManager");
	}

	private function getCenter(): Vector3
	{
		//UGLY HACK TO IGNORE ERRORS FOR NOW
		return new Vector3(0, 0, 0);
	}

	/**
	 * Creates a chunk manager used for async editing
	 * @param Chunk[] $chunks
	 * @phpstan-param array<int, Chunk> $chunks
	 * @return AsyncChunkManager
	 */
	public static function getChunkManager(array $chunks): AsyncChunkManager
	{
		$manager = new AsyncChunkManager(0, World::Y_MAX);
		foreach ($chunks as $hash => $chunk) {
			World::getXZ($hash, $x, $z);
			$manager->setChunk($x, $z, $chunk);
		}
		return $manager;
	}
}