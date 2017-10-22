<?php

namespace xenialdan\MagicWE2;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\ItemIds;
use pocketmine\level\Position;
use pocketmine\plugin\Plugin;

class EventListener implements Listener{
	public $owner;

	public function __construct(Plugin $plugin){
		$this->owner = $plugin;
	}

	public function onInteract(PlayerInteractEvent $event){
		switch ($event->getAction()){
			case PlayerInteractEvent::RIGHT_CLICK_BLOCK: {
				$this->onRightClickBlock($event);
				break;
			}
			case PlayerInteractEvent::RIGHT_CLICK_AIR: {
				$this->onRightClickAir($event);
				break;
			}
		}
	}

	public function onBreak(BlockBreakEvent $event){
		$this->onBreakBlock($event);
	}

	private function onBreakBlock(BlockBreakEvent $event){
		if (!is_null($event->getItem()->getNamedTagEntry("MagicWE"))){
			$event->setCancelled();
			switch ($event->getItem()->getId()){
				case ItemIds::WOODEN_AXE: {
					if (!isset(Loader::$selections[$event->getPlayer()->getLowerCaseName()])) Loader::$selections[$event->getPlayer()->getLowerCaseName()] = new Selection($event->getBlock()->getLevel());
					$event->getPlayer()->sendMessage(Loader::$selections[$event->getPlayer()->getLowerCaseName()]->setPos1(new Position($event->getBlock()->x, $event->getBlock()->y, $event->getBlock()->z, $event->getBlock()->getLevel())));
					break;
				}
			}
		}
	}

	private function onRightClickBlock(PlayerInteractEvent $event){
		if (!is_null($event->getItem()->getNamedTagEntry("MagicWE"))){
			$event->setCancelled();
			switch ($event->getItem()->getId()){
				/*case ItemIds::WOODEN_SHOVEL: { //TODO Open issue on pmmp, RIGHT_CLICK_BLOCK + RIGHT_CLICK_AIR are BOTH called when right clicking a block
					$target = $event->getBlock();
					if (!is_null($target)){// && has perms
						API::createBrush($target, $event->getItem()->getNamedTagEntry("MagicWE"));
					}
					break;
				}*/
				case ItemIds::WOODEN_AXE: {
					if (!isset(Loader::$selections[$event->getPlayer()->getLowerCaseName()])) Loader::$selections[$event->getPlayer()->getLowerCaseName()] = new Selection($event->getBlock()->getLevel());
					$event->getPlayer()->sendMessage(Loader::$selections[$event->getPlayer()->getLowerCaseName()]->setPos2(new Position($event->getBlock()->x, $event->getBlock()->y, $event->getBlock()->z, $event->getBlock()->getLevel())));
					break;
				}
			}
		}
	}

	private function onRightClickAir(PlayerInteractEvent $event){
		if (!is_null($event->getItem()->getNamedTagEntry("MagicWE"))){
			$event->setCancelled();
			switch ($event->getItem()->getId()){
				case ItemIds::WOODEN_SHOVEL: {
					$target = $event->getPlayer()->getTargetBlock(100);
					if (!is_null($target)){// && has perms
						API::createBrush($target, $event->getItem()->getNamedTagEntry("MagicWE"));
					}
					break;
				}
			}
		}
	}
}