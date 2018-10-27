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
            if (API::hasSession($event->getPlayer())) {
                $session = API::getSession($event->getPlayer());
				Loader::getInstance()->getLogger()->debug("Restored session with UUID {" . $session->getUUID() . "} for player {" . $session->getPlayer()->getName() . "}");
			}
		}
	}

	public function onInteract(PlayerInteractEvent $event){
		switch ($event->getAction()){
			case PlayerInteractEvent::RIGHT_CLICK_BLOCK: {
                try {
                    $this->onRightClickBlock($event);
                } catch (\Exception $error){
                    $event->getPlayer()->sendMessage(Loader::$prefix . TextFormat::RED . "Interaction failed!");
                    $event->getPlayer()->sendMessage(Loader::$prefix . TextFormat::RED . $error->getMessage());
                }
                break;
			}
			case PlayerInteractEvent::RIGHT_CLICK_AIR: {
				$this->onRightClickAir($event);
				break;
			}
		}
	}

	public function onBreak(BlockBreakEvent $event){
        try {
            $this->onBreakBlock($event);
        } catch (\Exception $error){
            $event->getPlayer()->sendMessage(Loader::$prefix . TextFormat::RED . "Interaction failed!");
            $event->getPlayer()->sendMessage(Loader::$prefix . TextFormat::RED . $error->getMessage());
        }
    }

    /**
     * @param BlockBreakEvent $event
     * @throws \Exception
     */
    private function onBreakBlock(BlockBreakEvent $event){
		if (!is_null($event->getItem()->getNamedTagEntry("MagicWE"))){
			$event->setCancelled();
			/** @var Session $session */
			$session = API::getSession($event->getPlayer());
			if (is_null($session)){
				throw new \Exception("No session was created - probably no permission to use " . $this->owner->getName());
			}
			switch ($event->getItem()->getId()){
				case ItemIds::WOODEN_AXE: {
					/** @var Session $session */
					if (!$session->isWandEnabled()){
						$event->getPlayer()->sendMessage(Loader::$prefix . TextFormat::RED . "The wand tool is disabled. Use //togglewand to re-enable it");//TODO #translation
						break;
					}
					$selection = $session->getLatestSelection() ?? $session->addSelection(new Selection($event->getBlock()->getLevel())); // TODO check if the selection inside of the session updates
					if (is_null($selection)){
						throw new \Error("No selection created - Check the console for errors");
					}
					$event->getPlayer()->sendMessage($selection->setPos1(new Position($event->getBlock()->x, $event->getBlock()->y, $event->getBlock()->z, $event->getBlock()->getLevel())));
					break;
				}
				case ItemIds::STICK: {
					/** @var Session $session */
					if (!$session->isDebugStickEnabled()){
						$event->getPlayer()->sendMessage(Loader::$prefix . TextFormat::RED . "The debug stick is disabled. Use //toggledebug to re-enable it");//TODO #translation
						break;
					}
					$event->getPlayer()->sendMessage($event->getBlock()->__toString() . ', variant: ' . $event->getBlock()->getVariant());
					break;
				}
			}
		}
	}

    /**
     * @param PlayerInteractEvent $event
     * @throws \Exception
     */
    private function onRightClickBlock(PlayerInteractEvent $event){
		if (!is_null($event->getItem()->getNamedTagEntry("MagicWE"))){
			$event->setCancelled();
			/** @var Session $session */
			$session = API::getSession($event->getPlayer());
			if (is_null($session)){
				throw new \Exception("No session was created - probably no permission to use " . $this->owner->getName());
			}
			switch ($event->getItem()->getId()){
				case ItemIds::WOODEN_AXE: {
					/** @var Session $session */
					if (!$session->isWandEnabled()){
						$event->getPlayer()->sendMessage(Loader::$prefix . TextFormat::RED . "The wand tool is disabled. Use //togglewand to re-enable it");//TODO #translation
						break;
					}
					$selection = $session->getLatestSelection() ?? $session->addSelection(new Selection($event->getBlock()->getLevel())); // TODO check if the selection inside of the session updates
					if (is_null($selection)){
						throw new \Error("No selection created - Check the console for errors");
					}
					$event->getPlayer()->sendMessage($selection->setPos2(new Position($event->getBlock()->x, $event->getBlock()->y, $event->getBlock()->z, $event->getBlock()->getLevel())));
					break;
				}
				case ItemIds::STICK: {
					/** @var Session $session */
					if (!$session->isDebugStickEnabled()){
						$event->getPlayer()->sendMessage(Loader::$prefix . TextFormat::RED . "The debug stick is disabled. Use //toggledebug to re-enable it");//TODO #translation
						break;
					}
					$event->getPlayer()->sendMessage($event->getBlock()->__toString() . ', variant: ' . $event->getBlock()->getVariant());
					break;
				}
				case ItemIds::BUCKET: {
					#if (){// && has perms
					API::floodArea($event->getBlock()->getSide($event->getFace()), $event->getItem()->getNamedTagEntry("MagicWE"), API::getSession($event->getPlayer()));
					#}
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