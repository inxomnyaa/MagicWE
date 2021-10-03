<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\session\data;

use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\tile\Spawnable;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\player\Player;
use pocketmine\world\Position;
use ReflectionException;
use ReflectionProperty;
use xenialdan\libstructure\tile\StructureBlockTile;
use xenialdan\MagicWE2\selection\Selection;
use function get_class;

class Outline
{
	private Selection $selection;
	private Player $player;
	private Position $position;
	private Block $fakeBlock;
	private StructureBlockTile $fakeTile;

	public function __construct(Selection $selection, Player $player)
	{
		$this->selection = $selection;
		$this->player = $player;
		$this->fakeBlock = BlockFactory::getInstance()->get(BlockLegacyIds::STRUCTURE_BLOCK, 0);
		$this->position = $this->updateBlockPosition();
		$this->fakeTile = new StructureBlockTile($this->position->getWorld(), $this->position);
		$this->fakeTile->setShowBoundingBox(true)->setFromV3($selection->getPos1())->setToV3($selection->getPos2());
		$this->send();
	}

	public function getSelection(): Selection
	{
		return $this->selection;
	}

	public function setSelection(Selection $selection): self
	{
		$this->selection = $selection;
		$this->remove();
		$this->updatePosition();
		//TODO change position of fakeTile using reflection
		#$this->fakeTile->setDirty();
		$this->fakeTile->setShowBoundingBox(true)->setFromV3($selection->getPos1())->setToV3($selection->getPos2());
		$this->send();
		return $this;
	}

	public function send(): void
	{
		$this->player->getNetworkSession()->sendDataPacket(UpdateBlockPacket::create($this->position->x, $this->position->y, $this->position->z, RuntimeBlockMapping::getInstance()->toRuntimeId($this->fakeBlock->getFullId())));
		if ($this->fakeTile instanceof Spawnable) {
			$this->player->getNetworkSession()->sendDataPacket(BlockActorDataPacket::create($this->position->x, $this->position->y, $this->position->z, $this->fakeTile->getSerializedSpawnCompound()), true);
		}
	}

	public function remove(): void
	{
		$network = $this->player->getNetworkSession();
		$world = $this->player->getWorld();
		$runtime_block_mapping = RuntimeBlockMapping::getInstance();
		$block = $world->getBlockAt($this->position->x, $this->position->y, $this->position->z);
		$network->sendDataPacket(UpdateBlockPacket::create($this->position->x, $this->position->y, $this->position->z, $runtime_block_mapping->toRuntimeId($block->getFullId())), true);

		$tile = $world->getTileAt($this->position->x, $this->position->y, $this->position->z);
		if ($tile instanceof Spawnable) {
			$network->sendDataPacket(BlockActorDataPacket::create($this->position->x, $this->position->y, $this->position->z, $tile->getSerializedSpawnCompound()), true);
		}
	}

	public function __toString(): string
	{
		return 'Outline';
	}

	/**
	 * @throws ReflectionException
	 */
	private function updatePosition(): Position
	{
		$this->position = $this->updateBlockPosition();
		$reflection = new ReflectionProperty(get_class($this->fakeTile), 'position');
		$reflection->setAccessible(true);
		$reflection->setValue($this->fakeTile, $this->position);
		return $this->position;
	}

	private function updateBlockPosition(): Position
	{
		return Position::fromObject($this->player->getPosition()->withComponents(null, $this->player->getPosition()->getWorld()->getMinY(), null)->floor(), $this->player->getWorld());
	}
}