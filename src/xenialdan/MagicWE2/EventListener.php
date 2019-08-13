<?php

namespace xenialdan\MagicWE2;

use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerAnimationEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\item\ItemIds;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\session\Session;
use xenialdan\MagicWE2\session\UserSession;

class EventListener implements Listener
{
    public $owner;

    public function __construct(Plugin $plugin)
    {
        $this->owner = $plugin;
    }

    public function onLogin(PlayerLoginEvent $event)
    {
        if ($event->getPlayer()->hasPermission("we.session")) {
            if (($session = API::findSession($event->getPlayer())) instanceof UserSession) {
                Loader::getInstance()->getLogger()->debug("Restored session with UUID {" . $session->getUUID() . "} for player {" . $session->getPlayer()->getName() . "}");
            }
        }
    }

    /**
     * @param PlayerInteractEvent $event
     * @throws \Exception
     */
    public function onInteract(PlayerInteractEvent $event)
    {
        switch ($event->getAction()) {
            case PlayerInteractEvent::RIGHT_CLICK_BLOCK:
                {
                    try {
                        $this->onRightClickBlock($event);
                    } catch (\Exception $error) {
                        $event->getPlayer()->sendMessage(Loader::PREFIX . TF::RED . "Interaction failed!");
                        $event->getPlayer()->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
                    }
                    break;
                }
            case PlayerInteractEvent::RIGHT_CLICK_AIR:
                {
                    try {
                        $this->onRightClickAir($event);
                    } catch (\Exception $error) {
                        $event->getPlayer()->sendMessage(Loader::PREFIX . TF::RED . "Interaction failed!");
                        $event->getPlayer()->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
                    }
                    break;
                }
            case PlayerInteractEvent::LEFT_CLICK_AIR://BROKEN CLIENT VISE
                {
                    $this->onLeftClickAir($event);
                    break;
                }
        }
    }

    /**
     * @param PlayerAnimationEvent $event
     * @throws \Exception
     */
    public function playerAnimate(PlayerAnimationEvent $event)
    {//TEMP FIX client does not send PlayerInteractEvent on left click
        if ($event->getAnimationType() === AnimatePacket::ACTION_SWING_ARM) {
            $ev = new PlayerInteractEvent($event->getPlayer(), $event->getPlayer()->getInventory()->getItemInHand(), null, null, 0, PlayerInteractEvent::LEFT_CLICK_AIR);
            $ev->call();
        }
    }

    public function onBreak(BlockBreakEvent $event)
    {
        try {
            $this->onBreakBlock($event);
        } catch (\Exception $error) {
            $event->getPlayer()->sendMessage(Loader::PREFIX . TF::RED . "Interaction failed!");
            $event->getPlayer()->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
        }
    }

    /**
     * @param BlockBreakEvent $event
     * @throws \Exception
     */
    private function onBreakBlock(BlockBreakEvent $event)
    {
        if (!is_null($event->getItem()->getNamedTagEntry(API::TAG_MAGIC_WE))) {
            $event->setCancelled();
            /** @var UserSession $session */
            $session = API::getSession($event->getPlayer());
            if (is_null($session)) return;
            switch ($event->getItem()->getId()) {
                case ItemIds::WOODEN_AXE:
                    {
                        if (!$session->isWandEnabled()) {
                            $event->getPlayer()->sendMessage(Loader::PREFIX . TF::RED . "The wand tool is disabled. Use //togglewand to re-enable it");//TODO #translation
                            break;
                        }
                        $selection = $session->getLatestSelection() ?? $session->addSelection(new Selection($event->getBlock()->getLevel())); // TODO check if the selection inside of the session updates
                        if (is_null($selection)) {
                            throw new \Error("No selection created - Check the console for errors");
                        }
                        $event->getPlayer()->sendMessage($selection->setPos1(new Position($event->getBlock()->x, $event->getBlock()->y, $event->getBlock()->z, $event->getBlock()->getLevel())));
                        break;
                    }
                case ItemIds::STICK:
                    {
                        if (!$session->isDebugStickEnabled()) {
                            $event->getPlayer()->sendMessage(Loader::PREFIX . TF::RED . "The debug stick is disabled. Use //toggledebug to re-enable it");//TODO #translation
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
    private function onRightClickBlock(PlayerInteractEvent $event)
    {
        if (!is_null($event->getItem()->getNamedTagEntry(API::TAG_MAGIC_WE))) {
            $event->setCancelled();
            /** @var UserSession $session */
            $session = API::getSession($event->getPlayer());
            if (is_null($session)) return;
            switch ($event->getItem()->getId()) {
                case ItemIds::WOODEN_AXE:
                    {
                        if (!$session->isWandEnabled()) {
                            $event->getPlayer()->sendMessage(Loader::PREFIX . TF::RED . "The wand tool is disabled. Use //togglewand to re-enable it");//TODO #translation
                            break;
                        }
                        $selection = $session->getLatestSelection() ?? $session->addSelection(new Selection($event->getBlock()->getLevel())); // TODO check if the selection inside of the session updates
                        if (is_null($selection)) {
                            throw new \Error("No selection created - Check the console for errors");
                        }
                        $event->getPlayer()->sendMessage($selection->setPos2(new Position($event->getBlock()->x, $event->getBlock()->y, $event->getBlock()->z, $event->getBlock()->getLevel())));
                        break;
                    }
                case ItemIds::STICK:
                    {
                        if (!$session->isDebugStickEnabled()) {
                            $event->getPlayer()->sendMessage(Loader::PREFIX . TF::RED . "The debug stick is disabled. Use //toggledebug to re-enable it");//TODO #translation
                            break;
                        }
                        $event->getPlayer()->sendMessage($event->getBlock()->__toString() . ', variant: ' . $event->getBlock()->getVariant());
                        break;
                    }
                case ItemIds::BUCKET:
                    {
                        #if (){// && has perms
                        API::floodArea($event->getBlock()->getSide($event->getFace()), $event->getItem()->getNamedTagEntry(API::TAG_MAGIC_WE), $session);
                        #}
                        break;
                    }
            }
        }
    }

    /**
     * @param PlayerInteractEvent $event
     * @throws \Exception
     */
    private function onRightClickAir(PlayerInteractEvent $event)
    {
        if (!is_null($event->getItem()->getNamedTagEntry(API::TAG_MAGIC_WE))) {
            $event->setCancelled();
            /** @var Session $session */
            $session = API::getSession($event->getPlayer());
            if (is_null($session)) return;
            switch ($event->getItem()->getId()) {
                case ItemIds::WOODEN_SHOVEL:
                    {
                        $target = $event->getPlayer()->getTargetBlock(100);
                        if (!is_null($target)) {// && has perms
                            API::createBrush($target, $event->getItem()->getNamedTagEntry(API::TAG_MAGIC_WE), $session);
                        }
                        break;
                    }
            }
        }
    }

    /**
     * @param PlayerInteractEvent $event
     * @throws \Exception
     */
    private function onLeftClickAir(PlayerInteractEvent $event)
    {
        if (!is_null($event->getItem()->getNamedTagEntry(API::TAG_MAGIC_WE))) {
            $event->setCancelled();
            /** @var UserSession $session */
            $session = API::getSession($event->getPlayer());
            if (is_null($session)) return;
            switch ($event->getItem()->getId()) {
                case ItemIds::WOODEN_AXE:
                    {
                        $event->setCancelled();
                        if (!$session->isWandEnabled()) {
                            $event->getPlayer()->sendMessage(Loader::PREFIX . TF::RED . "The wand tool is disabled. Use //togglewand to re-enable it");//TODO #translation
                            break;
                        }
                        $selection = $session->getLatestSelection() ?? $session->addSelection(new Selection($event->getPlayer()->getLevel())); // TODO check if the selection inside of the session updates
                        if (is_null($selection)) {
                            throw new \Error("No selection created - Check the console for errors");
                        }
                        /** @var Block|null $target */
                        $target = $event->getPlayer()->getTargetBlock(100);
                        if ($target === null) {
                            $event->getPlayer()->sendMessage(Loader::PREFIX . TF::RED . "No target block found");
                            return;
                        }
                        $event->getPlayer()->sendMessage($selection->setPos1($target));
                        break;
                    }
            }
        }
    }
}