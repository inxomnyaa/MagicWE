<?php

namespace xenialdan\MagicWE2;

use Error;
use Exception;
use InvalidArgumentException;
use InvalidStateException;
use JsonException;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockLegacyIds;
use pocketmine\entity\InvalidSkinException;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\ItemIds;
use pocketmine\math\Vector3;
use pocketmine\nbt\UnexpectedTagTypeException;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\Position;
use RuntimeException;
use xenialdan\customui\windows\ModalForm;
use xenialdan\libstructure\tile\StructureBlockTile;
use xenialdan\MagicWE2\event\MWESelectionChangeEvent;
use xenialdan\MagicWE2\event\MWESessionLoadEvent;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\session\data\Asset;
use xenialdan\MagicWE2\session\UserSession;
use xenialdan\MagicWE2\tool\Brush;

class EventListener implements Listener
{
	/** @var Plugin */
	public Plugin $owner;

	public function __construct(Plugin $plugin)
	{
		$this->owner = $plugin;
	}

	/**
	 * @param PlayerJoinEvent $event
	 * @throws InvalidSkinException
	 * @throws JsonException
	 * @throws RuntimeException
	 * @throws SessionException
	 */
	public function onLogin(PlayerJoinEvent $event): void
	{
		if ($event->getPlayer()->hasPermission("we.session")) {
			if (SessionHelper::hasSession($event->getPlayer()) && ($session = SessionHelper::getUserSession($event->getPlayer())) instanceof UserSession) {
				Loader::getInstance()->getLogger()->debug("Restored cached session for player {$session->getPlayer()->getName()}");
			} else if (($session = SessionHelper::loadUserSession($event->getPlayer())) instanceof UserSession) {
				Loader::getInstance()->getLogger()->debug("Restored session from file for player {$session->getPlayer()->getName()}");
			} else ($session = SessionHelper::createUserSession($event->getPlayer()));
		}
	}

	public function onSessionLoad(MWESessionLoadEvent $event): void
	{
		Loader::getInstance()->wailaBossBar->addPlayer($event->getPlayer());
		if (Loader::hasScoreboard()) {
			$session = $event->getSession();
			if ($session instanceof UserSession && $session->isSidebarEnabled())
				/** @var UserSession $session */
				$session->sidebar->handleScoreboard($session);
		}
	}

	/**
	 * @param PlayerQuitEvent $event
	 * @throws JsonException
	 * @throws SessionException
	 */
	public function onLogout(PlayerQuitEvent $event): void
	{
		if (($session = SessionHelper::getUserSession($event->getPlayer())) instanceof UserSession) {
			SessionHelper::destroySession($session);
			unset($session);
		}
	}

	/**
	 * @param PlayerInteractEvent $event
	 * @throws AssumptionFailedError
	 * @throws Error
	 */
	public function onInteract(PlayerInteractEvent $event): void
	{
		try {
			switch ($event->getAction()) {
				case PlayerInteractEvent::RIGHT_CLICK_BLOCK:
				{
					$this->onRightClickBlock($event);
					break;
				}
				case PlayerInteractEvent::LEFT_CLICK_BLOCK:
				{
					$this->onLeftClickBlock($event);
					break;
				}
			}
		} catch (Exception $error) {
			$event->getPlayer()->sendMessage(Loader::PREFIX . TF::RED . "Interaction failed!");
			$event->getPlayer()->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
		}
	}

	/**
	 * @param PlayerItemUseEvent $event
	 * @throws AssumptionFailedError
	 */
	public function onItemRightClick(PlayerItemUseEvent $event): void
	{
		try {
			$this->onRightClickAir($event);
		} catch (Exception $error) {
			$event->getPlayer()->sendMessage(Loader::PREFIX . TF::RED . "Interaction failed!");
			$event->getPlayer()->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
		}
	}

	/**
	 * @param BlockBreakEvent $event
	 * @throws AssumptionFailedError
	 * @throws Error
	 * @throws UnexpectedTagTypeException
	 */
	public function onBreak(BlockBreakEvent $event): void
	{
		if (!is_null($event->getItem()->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE)) || !is_null($event->getItem()->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE_BRUSH))) {
			$event->cancel();
			try {
				$this->onBreakBlock($event);
			} catch (Exception $error) {
				$event->getPlayer()->sendMessage(Loader::PREFIX . TF::RED . "Interaction failed!");
				$event->getPlayer()->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
			}
		}
	}

//	/**
//	 * @param BlockPlaceEvent $event
//	 * @throws AssumptionFailedError
//	 * @throws Error
//	 */
//	public function onPlace(BlockPlaceEvent $event): void
//	{
//		if (!is_null($event->getItem()->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE)) || !is_null($event->getItem()->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE_BRUSH))) {
//			$event->cancel();
//			try {
//				$this->onBreakBlock($event);
//			} catch (Exception $error) {
//				$event->getPlayer()->sendMessage(Loader::PREFIX . TF::RED . "Interaction failed!");
//				$event->getPlayer()->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
//			}
//		}
//	}

	/**
	 * TODO use tool classes
	 * @param BlockBreakEvent $event
	 * @throws AssumptionFailedError
	 * @throws Error
	 * @throws InvalidArgumentException
	 * @throws SessionException
	 */
	private function onBreakBlock(BlockBreakEvent $event): void
	{
		$session = SessionHelper::getUserSession($event->getPlayer());
		if (!$session instanceof UserSession) return;
		switch ($event->getItem()->getId()) {
			case ItemIds::WOODEN_AXE:
			{
				if (!$session->isWandEnabled()) {
					$session->sendMessage(TF::RED . $session->getLanguage()->translateString("tool.wand.disabled"));
					break;
				}
				$selection = $session->getLatestSelection() ?? $session->addSelection(new Selection($session->getUUID(), $event->getBlock()->getPosition()->getWorld())); // TODO check if the selection inside of the session updates
				if (is_null($selection)) {
					throw new Error("No selection created - Check the console for errors");
				}
				$selection->setPos1(new Position($event->getBlock()->getPosition()->x, $event->getBlock()->getPosition()->y, $event->getBlock()->getPosition()->z, $event->getBlock()->getPosition()->getWorld()));
				break;
			}
			case ItemIds::STICK:
			{
				if (!$session->isDebugToolEnabled()) {
					$session->sendMessage(TF::RED . $session->getLanguage()->translateString("tool.debug.disabled"));
					break;
				}
				$event->getPlayer()->sendMessage($event->getBlock()->__toString() . ', variant: ' . $event->getBlock()->getIdInfo()->getVariant());
				break;
			}
		}
	}

	/**
	 * TODO use tool classes
	 * @param PlayerInteractEvent $event
	 * @throws AssumptionFailedError
	 * @throws Error
	 * @throws InvalidArgumentException
	 * @throws InvalidStateException
	 * @throws SessionException
	 * @throws UnexpectedTagTypeException
	 */
	private function onRightClickBlock(PlayerInteractEvent $event): void
	{
		if (!is_null($event->getItem()->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE)) || !is_null($event->getItem()->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE_ASSET))) {
			$event->cancel();
			$session = SessionHelper::getUserSession($event->getPlayer());
			if (!$session instanceof UserSession) return;
			switch ($event->getItem()->getId()) {
				case ItemIds::WOODEN_AXE:
				{
					if (!$session->isWandEnabled()) {
						$session->sendMessage(TF::RED . $session->getLanguage()->translateString("tool.wand.disabled"));
						break;
					}
					$selection = $session->getLatestSelection() ?? $session->addSelection(new Selection($session->getUUID(), $event->getBlock()->getPosition()->getWorld())); // TODO check if the selection inside of the session updates
					if (is_null($selection)) {
						throw new Error("No selection created - Check the console for errors");
					}
					$selection->setPos2(new Position($event->getBlock()->getPosition()->x, $event->getBlock()->getPosition()->y, $event->getBlock()->getPosition()->z, $event->getBlock()->getPosition()->getWorld()));
					break;
				}
				case ItemIds::STICK:
				{
					if (!$session->isDebugToolEnabled()) {
						$session->sendMessage(TF::RED . $session->getLanguage()->translateString("tool.debug.disabled"));
						break;
					}
					$event->getPlayer()->sendMessage($event->getBlock()->__toString() . ', variant: ' . $event->getBlock()->getIdInfo()->getVariant());
					break;
				}
				case ItemIds::BUCKET:
				{
					#if (){// && has perms
					API::floodArea($event->getBlock()->getSide($event->getFace()), $event->getItem()->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE), $session);
					#}
					break;
				}
				case ItemIds::SCAFFOLDING:
				{
					$tag = $event->getItem()->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE_ASSET);
					if ($tag !== null) {
						$filename = $tag->getString('filename');
						$asset = Loader::$assetCollection->assets[$filename];//TODO allow private assets again
						$target = $event->getBlock()->getSide($event->getFace())->getPosition();
						if (API::placeAsset($target, $asset, $tag, $session)) {
							$event->getPlayer()->sendMessage("Asset placed!");
						} else {
							$event->getPlayer()->sendMessage("Asset not placed!");
						}
					}
					break;
				}
				default:
				{
					var_dump($event->getItem());
					$event->cancel();
					break;
				}
			}
		}
	}

	/**
	 * @param PlayerInteractEvent $event
	 * @throws AssumptionFailedError
	 * @throws Error
	 * @throws InvalidArgumentException
	 * @throws InvalidStateException
	 * @throws SessionException
	 * @throws UnexpectedTagTypeException
	 */
	private function onLeftClickBlock(PlayerInteractEvent $event): void
	{
		if (!is_null($event->getItem()->getNamedTag()->getTag(API::TAG_MAGIC_WE))) {
			$event->cancel();
			$session = SessionHelper::getUserSession($event->getPlayer());
			if (!$session instanceof UserSession) return;
			switch ($event->getItem()->getId()) {
				case ItemIds::WOODEN_AXE:
				{
					if (!$session->isWandEnabled()) {
						$session->sendMessage(TF::RED . $session->getLanguage()->translateString("tool.wand.disabled"));
						break;
					}
					$selection = $session->getLatestSelection() ?? $session->addSelection(new Selection($session->getUUID(), $event->getBlock()->getPosition()->getWorld())); // TODO check if the selection inside of the session updates
					if (is_null($selection)) {
						throw new Error("No selection created - Check the console for errors");
					}
					$selection->setPos1(new Position($event->getBlock()->getPosition()->x, $event->getBlock()->getPosition()->y, $event->getBlock()->getPosition()->z, $event->getBlock()->getPosition()->getWorld()));
					break;
				}
				case ItemIds::STICK:
				{
					if (!$session->isDebugToolEnabled()) {
						$session->sendMessage(TF::RED . $session->getLanguage()->translateString("tool.debug.disabled"));
						break;
					}
					$event->getPlayer()->sendMessage($event->getBlock()->__toString() . ', variant: ' . $event->getBlock()->getIdInfo()->getVariant());
					break;
				}
				case ItemIds::BUCKET:
				{
					#if (){// && has perms
					API::floodArea($event->getBlock()->getSide($event->getFace()), $event->getItem()->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE), $session);
					#}
					break;
				}
			}
		}
	}

	/**
	 * @param PlayerItemUseEvent $event
	 * @throws AssumptionFailedError
	 * @throws InvalidArgumentException
	 * @throws SessionException
	 * @throws JsonException
	 * @throws RuntimeException
	 * @throws Exception
	 */
	private function onRightClickAir(PlayerItemUseEvent $event): void
	{
		if (!is_null($event->getItem()->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE_BRUSH))) {
			$event->cancel();
			$session = SessionHelper::getUserSession($event->getPlayer());
			if (!$session instanceof UserSession) return;
			$target = $event->getPlayer()->getTargetBlock(Loader::getInstance()->getToolDistance());
			$brush = $session->getBrushes()->getBrushFromItem($event->getItem());
			var_dump(json_encode($brush, JSON_THROW_ON_ERROR));
			if ($brush instanceof Brush && !is_null($target)) {// && has perms
				API::createBrush($target, $brush, $session);
			}
		}
	}

	/**
	 * @param PlayerDropItemEvent $event
	 */
	public function onDropItem(PlayerDropItemEvent $event): void
	{
		try {
			if (!is_null($event->getItem()->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE_BRUSH))) {
				$event->cancel();
				$session = SessionHelper::getUserSession($event->getPlayer());
				if (!$session instanceof UserSession) return;
				$brush = $session->getBrushes()->getBrushFromItem($event->getItem());
				if ($brush instanceof Brush) {
					$form = new ModalForm(TF::BOLD . $brush->getName(), TF::RED .
						"Delete" . TF::WHITE . " brush from session or " . TF::GREEN . "remove" . TF::WHITE . " from Inventory?" . TF::EOL .
						implode(TF::EOL, $event->getItem()->getLore()), TF::BOLD . TF::DARK_RED . "Delete", TF::BOLD . TF::DARK_GREEN . "Remove");
					$form->setCallable(function (Player $player, $data) use ($session, $brush) {
						$session->getBrushes()->removeBrush($brush, $data);
					});
					$event->getPlayer()->sendForm($form);
				}
			} else if (!is_null($event->getItem()->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE))) {
				$event->cancel();
				$event->getPlayer()->getInventory()->remove($event->getItem());
			}
		} catch (Exception $e) {
		}
	}

	public function onSelectionChange(MWESelectionChangeEvent $event): void
	{
		Loader::getInstance()->getLogger()->debug("Called " . $event->getEventName());
		if (($session = $event->getSession()) instanceof UserSession && $event->getPlayer() !== null) {
			/** @var UserSession $session */
			$session->sidebar->handleScoreboard($session);
		}
	}

	/**
	 * TODO use tool classes
	 * @param PlayerItemHeldEvent $event
	 * @throws SessionException
	 * @throws UnexpectedTagTypeException
	 */
	public function onChangeSlot(PlayerItemHeldEvent $event): void
	{
		var_dump($event->getSlot());
		$player = $event->getPlayer();
		#$item = $player->getInventory()->getItemInHand();
		$item = $player->getInventory()->getItem($event->getSlot());
		$session = SessionHelper::getUserSession($player);
		if (!is_null(($tag = $item->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE_ASSET)))) {
			if (!$session instanceof UserSession) return;
			if ($item->getId() === ItemIds::SCAFFOLDING) {
				$filename = $tag->getString('filename');
				$asset = Loader::$assetCollection->assets[$filename];//TODO allow private assets again
				var_dump($filename, $asset);
				#$assets = AssetCollection::getInstance()->getPlayerAssets($player->getXuid());
				$session->displayOutline = true;
				#foreach ($assets as $asset) {
				$backwards = $player->getDirectionVector()->normalize()->multiply(-1);
				$target = $player->getTargetBlock(10)->getPosition();
				$target->addVector($backwards);//this selects the block before raytrace
				$target->subtract(0, 1, 0);//one block down
				$target = Position::fromObject($target, $player->getWorld());
				if (/*$session->displayOutline && */ self::sendOutline($player, $target, $asset, $session)) {
					$player->sendMessage("Added asset outline for {$asset->filename}!");
				} else {
					$player->sendMessage("Did not add asset outline!");
				}
				#}
			} else {
				$session->displayOutline = false;
			}
		} else {
			$session->displayOutline = false;
		}
	}

	public static function sendOutline(Player $player, Position $target, Asset $asset, UserSession $session): bool
	{
		var_dump(__METHOD__);
		#$start = clone $target->asVector3()->floor()->addVector($asset->getOrigin())->floor();//start pos of paste//TODO if using rotate, this fails
		#$end = $start->addVector($asset->getSize()->subtractVector($asset->getOrigin()));//add size
		//[$target->x,$target->y,$target->z] = [($v=$target->asVector3())->getFloorX(),$v->getFloorY(),$v->getFloorZ()];
		$start = clone $target->asVector3();//start pos of paste//TODO if using rotate, this fails
		$end = $start->addVector($asset->getSize()->subtract(1, 1, 1));//add size, remove 1 because structure block outline always covers 1 block
		$offset = new Vector3($asset->getSize()->getX() / 2, 0, $asset->getSize()->getZ() / 2);
		$start = $start->subtractVector($offset);
		$end = $end->subtractVector($offset);
		[$ix, $iy, $iz, $ax, $ay, $az] = [Vector3::minComponents($start, $end)->getFloorX(), Vector3::minComponents($start, $end)->getFloorY(), Vector3::minComponents($start, $end)->getFloorZ(), Vector3::maxComponents($start, $end)->getFloorX(), Vector3::maxComponents($start, $end)->getFloorY(), Vector3::maxComponents($start, $end)->getFloorZ()];

		$minComponents = new Vector3($ix, $iy, $iz);
		$maxComponents = new Vector3($ax, $ay, $az);
		/** @noinspection PhpDeprecationInspection */
		$block = BlockFactory::getInstance()->get(BlockLegacyIds::STRUCTURE_BLOCK, 0/*StructureEditorData::TYPE_SAVE*/);
		var_dump((string)$block);
		$target->world->setBlock($target->asVector3(), $block);
		var_dump("placing block", __METHOD__, (string)$target->world->getBlock($target->asVector3()));
		/** @var null|StructureBlockTile $tile */
		$tile = $target->world->getTile($target->asVector3());
		if ($tile instanceof StructureBlockTile) {
			var_dump($tile->getSpawnCompound()->toString());
			$tile
				->setFromV3($minComponents)
				->setToV3($maxComponents)
				//TODO add setOffsetV3
				->setTitle($asset->displayname)
				->setShowBlocks(true)
				->setShowBoundingBox(true)
				->setShowEntities(false)
				->setShowPlayers(false)
				->setHideStructureBlock(false);
			$tile->saveNBT();
			#$target->getWorld()->scheduleDelayedBlockUpdate($target, 1);
			var_dump($tile->getSpawnCompound()->toString());
		} else {
			var_dump("no tile");
			return false;
		}

		return true;
	}

	public function onStructureBlockClick(PlayerInteractEvent $event): void
	{
		//$player = $event->getPlayer();
		$blockTouched = $event->getBlock();
		if ($blockTouched->getId() === BlockLegacyIds::STRUCTURE_BLOCK) {
			var_dump("Clicked Structure Block", (string)$blockTouched);
			$tile = $blockTouched->getPosition()->getWorld()->getTile($blockTouched->getPosition()->asVector3());
			if ($tile instanceof StructureBlockTile) {
				var_dump("Is Structure Block Tile", $tile->getSpawnCompound()->toString());
//				$item = $player->getInventory()->getItemInHand();
//				if (!is_null(($tag = $item->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE_ASSET)))) {
//					$session = SessionHelper::getUserSession($player);
//					if (!$session instanceof UserSession) return;
//					if ($item->getId() === ItemIds::SCAFFOLDING) {
//						$filename = $tag->getString('filename');
//						$asset = AssetCollection::getInstance()->assets->get($filename);
//						/** @var StructureBlockInventory $inventory */
//						$inventory = $tile->getInventory();
//						$pk = new StructureBlockUpdatePacket();
//						$pk->structureEditorData = $tile->getStructureEditorData($asset);
//						[$pk->x, $pk->y, $pk->z] = [$blockTouched->getPosition()->getFloorX(), $blockTouched->getPosition()->getFloorY(), $blockTouched->getPosition()->getFloorZ()];
//						$pk->isPowered = false;
//						$player->getNetworkSession()->sendDataPacket($pk);
//						$tile->sendInventory($player);
//						var_dump($tile->getSpawnCompound()->toString(), $inventory);
//					}
//				}
				var_dump($blockTouched);
			}
		}

	}
}