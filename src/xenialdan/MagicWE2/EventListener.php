<?php

namespace xenialdan\MagicWE2;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\item\ItemIds;
use pocketmine\level\Position;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;

class EventListener implements Listener{
	public $owner;

	public function __construct(Plugin $plugin){
		$this->owner = $plugin;
	}

	public function onLogin(PlayerLoginEvent $event){
		if ($event->getPlayer()->hasPermission("we.session")){
			if (is_null(($session = API::getSession($event->getPlayer())))){
				$session = API::addSession(new Session($event->getPlayer()));
				Loader::getInstance()->getLogger()->debug("Created new session with UUID {" . $session->getUUID() . "} for player {" . $session->getPlayer()->getName() . "}");
			} else{
				$session->setPlayer($event->getPlayer());
				Loader::getInstance()->getLogger()->debug("Restored session with UUID {" . $session->getUUID() . "} for player {" . $session->getPlayer()->getName() . "}");
			}
		}
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
					/** @var Session $session */
					if (!($session = API::getSession($event->getPlayer()))->isWandEnabled()){
						$event->getPlayer()->sendMessage(Loader::$prefix . TextFormat::RED . "The wand tool is disabled. Use //togglewand to re-enable it");//TODO #translation
						break;
					}
					$selection = $session->getLatestSelection() ?? $session->addSelection(new Selection($event->getBlock()->getLevel())); // TODO check if the selection inside of the session updates
					$event->getPlayer()->sendMessage($selection->setPos1(new Position($event->getBlock()->x, $event->getBlock()->y, $event->getBlock()->z, $event->getBlock()->getLevel())));
					break;
				}
			}
		}
	}

	private function onRightClickBlock(PlayerInteractEvent $event){
		if (!is_null($event->getItem()->getNamedTagEntry("MagicWE"))){
			$event->setCancelled();
			switch ($event->getItem()->getId()){
				/*case ItemIds::WOODEN_SHOVEL: { //TODO Open issue on pmmp, RIGHT_CLICK_BLOCK + RIGHT_CLICK_AIR are BOTH called when right clicking a block - Turns out to be a client bug
					$target = $event->getBlock();
					if (!is_null($target)){// && has perms
						API::createBrush($target, $event->getItem()->getNamedTagEntry("MagicWE"));
					}
					break;
				}*/
				case ItemIds::WOODEN_AXE: {
					/** @var Session $session */
					if (!($session = API::getSession($event->getPlayer()))->isWandEnabled()){
						$event->getPlayer()->sendMessage(Loader::$prefix . TextFormat::RED . "The wand tool is disabled. Use //togglewand to re-enable it");//TODO #translation
						break;
					}
					$selection = $session->getLatestSelection() ?? $session->addSelection(new Selection($event->getBlock()->getLevel())); // TODO check if the selection inside of the session updates
					$event->getPlayer()->sendMessage($selection->setPos2(new Position($event->getBlock()->x, $event->getBlock()->y, $event->getBlock()->z, $event->getBlock()->getLevel())));
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
						API::createBrush($target, $event->getItem()->getNamedTagEntry("MagicWE"), API::getSession($event->getPlayer()));
					}
					break;
				}
			}
		}
	}
}