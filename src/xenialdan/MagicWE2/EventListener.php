<?php

namespace xenialdan\MagicWE2;

use Error;
use Exception;
use InvalidStateException;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\ItemIds;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\Position;
use xenialdan\customui\windows\ModalForm;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\session\UserSession;
use xenialdan\MagicWE2\tool\Brush;

class EventListener implements Listener
{
    /** @var Plugin */
    public $owner;

    public function __construct(Plugin $plugin)
    {
        $this->owner = $plugin;
    }

    /**
     * @param PlayerJoinEvent $event
     * @throws InvalidStateException
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

    /**
     * @param PlayerQuitEvent $event
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
     * @throws Exception
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
                case PlayerInteractEvent::RIGHT_CLICK_AIR:
                {
                    $this->onRightClickAir($event);
                    break;
                }
            }
        } catch (Exception $error) {
            $event->getPlayer()->sendMessage(Loader::PREFIX . TF::RED . "Interaction failed!");
            $event->getPlayer()->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
        }
    }

    /**
     * @param BlockBreakEvent $event
     */
    public function onBreak(BlockBreakEvent $event): void
    {
        if (!is_null($event->getItem()->getNamedTagEntry(API::TAG_MAGIC_WE)) || !is_null($event->getItem()->getNamedTagEntry(API::TAG_MAGIC_WE_BRUSH))) {
            $event->setCancelled();
            try {
                $this->onBreakBlock($event);
            } catch (Exception $error) {
                $event->getPlayer()->sendMessage(Loader::PREFIX . TF::RED . "Interaction failed!");
                $event->getPlayer()->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
            }
        }
    }

    /**
     * TODO use tool classes
     * @param BlockBreakEvent $event
     * @throws Exception
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
                $selection = $session->getLatestSelection() ?? $session->addSelection(new Selection($session->getUUID(), $event->getBlock()->getWorld())); // TODO check if the selection inside of the session updates
                if (is_null($selection)) {
                    throw new Error("No selection created - Check the console for errors");
                }
                $selection->setPos1(new Position($event->getBlock()->x, $event->getBlock()->y, $event->getBlock()->z, $event->getBlock()->getWorld()));
                break;
            }
            case ItemIds::STICK:
            {
                if (!$session->isDebugToolEnabled()) {
                    $session->sendMessage(TF::RED . $session->getLanguage()->translateString("tool.debug.disabled"));
                    break;
                }
                $event->getPlayer()->sendMessage($event->getBlock()->__toString() . ', variant: ' . $event->getBlock()->getVariant());
                break;
            }
        }
    }

    /**
     * TODO use tool classes
     * @param PlayerInteractEvent $event
     * @throws Exception
     */
    private function onRightClickBlock(PlayerInteractEvent $event): void
    {
        if (!is_null($event->getItem()->getNamedTagEntry(API::TAG_MAGIC_WE))) {
            $event->setCancelled();
            $session = SessionHelper::getUserSession($event->getPlayer());
            if (!$session instanceof UserSession) return;
            switch ($event->getItem()->getId()) {
                case ItemIds::WOODEN_AXE:
                {
                    if (!$session->isWandEnabled()) {
                        $session->sendMessage(TF::RED . $session->getLanguage()->translateString("tool.wand.disabled"));
                        break;
                    }
                    $selection = $session->getLatestSelection() ?? $session->addSelection(new Selection($session->getUUID(), $event->getBlock()->getWorld())); // TODO check if the selection inside of the session updates
                    if (is_null($selection)) {
                        throw new Error("No selection created - Check the console for errors");
                    }
                    $selection->setPos2(new Position($event->getBlock()->x, $event->getBlock()->y, $event->getBlock()->z, $event->getBlock()->getWorld()));
                    break;
                }
                case ItemIds::STICK:
                {
                    if (!$session->isDebugToolEnabled()) {
                        $session->sendMessage(TF::RED . $session->getLanguage()->translateString("tool.debug.disabled"));
                        break;
                    }
                    $event->getPlayer()->sendMessage($event->getBlock()->__toString() . ', variant: ' . $event->getBlock()->getVariant());
                    break;
                }
                case ItemIds::BUCKET:
                {
                    #if (){// && has perms
                    API::floodArea($event->getBlock()->getSide($event->getFace()), $event->getItem()->getNamedTag()->getTag(API::TAG_MAGIC_WE), $session);
                    #}
                    break;
                }
            }
        }
    }

    /**
     * @param PlayerInteractEvent $event
     * @throws Exception
     */
    private function onLeftClickBlock(PlayerInteractEvent $event): void
    {
        if (!is_null($event->getItem()->getNamedTag()->getTag(API::TAG_MAGIC_WE))) {
            $event->setCancelled();
            $session = SessionHelper::getUserSession($event->getPlayer());
            if (!$session instanceof UserSession) return;
            switch ($event->getItem()->getId()) {
                case ItemIds::WOODEN_AXE:
                {
                    if (!$session->isWandEnabled()) {
                        $session->sendMessage(TF::RED . $session->getLanguage()->translateString("tool.wand.disabled"));
                        break;
                    }
                    $selection = $session->getLatestSelection() ?? $session->addSelection(new Selection($session->getUUID(), $event->getBlock()->getWorld())); // TODO check if the selection inside of the session updates
                    if (is_null($selection)) {
                        throw new Error("No selection created - Check the console for errors");
                    }
                    $selection->setPos1(new Position($event->getBlock()->x, $event->getBlock()->y, $event->getBlock()->z, $event->getBlock()->getWorld()));
                    break;
                }
                case ItemIds::STICK:
                {
                    if (!$session->isDebugToolEnabled()) {
                        $session->sendMessage(TF::RED . $session->getLanguage()->translateString("tool.debug.disabled"));
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
     * @throws Exception
     */
    private function onRightClickAir(PlayerInteractEvent $event): void
    {
        if (!is_null($event->getItem()->getNamedTagEntry(API::TAG_MAGIC_WE_BRUSH))) {
            $event->setCancelled();
            $session = SessionHelper::getUserSession($event->getPlayer());
            if (!$session instanceof UserSession) return;
            $target = $event->getPlayer()->getTargetBlock(Loader::getInstance()->getToolDistance());
            $brush = $session->getBrushFromItem($event->getItem());
            var_dump(json_encode($brush));
            if (!is_null($target) && $brush instanceof Brush) {// && has perms
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
            if (!is_null($event->getItem()->getNamedTagEntry(API::TAG_MAGIC_WE_BRUSH))) {
                $event->setCancelled();
                $session = SessionHelper::getUserSession($event->getPlayer());
                if (!$session instanceof UserSession) return;
                $brush = $session->getBrushFromItem($event->getItem());
                if ($brush instanceof Brush) {
                    $form = new ModalForm(TF::BOLD . $brush->getName(), TF::RED .
                        "Delete" . TF::WHITE . " brush from session or " . TF::GREEN . "remove" . TF::WHITE . " from Inventory?" . TF::EOL .
                        implode(TF::EOL, $event->getItem()->getLore()), TF::BOLD . TF::DARK_RED . "Delete", TF::BOLD . TF::DARK_GREEN . "Remove");
                    $form->setCallable(function (Player $player, $data) use ($session, $brush) {
                        $session->removeBrush($brush, $data);
                    });
                    $event->getPlayer()->sendForm($form);
                }
            } else if (!is_null($event->getItem()->getNamedTagEntry(API::TAG_MAGIC_WE))) {
                $event->setCancelled();
                $event->getPlayer()->getInventory()->remove($event->getItem());
            }
        } catch (Exception $e) {
        }
    }
}