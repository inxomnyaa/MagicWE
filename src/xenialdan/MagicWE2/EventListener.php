<?php

namespace xenialdan\MagicWE2;

use Error;
use Exception;
use InvalidArgumentException;
use InvalidStateException;
use JsonException;
use pocketmine\entity\InvalidSkinException;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\ItemIds;
use pocketmine\nbt\UnexpectedTagTypeException;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\Position;
use RuntimeException;
use xenialdan\customui\windows\ModalForm;
use xenialdan\MagicWE2\event\MWESelectionChangeEvent;
use xenialdan\MagicWE2\event\MWESessionLoadEvent;
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
     * @throws AssumptionFailedError
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
            } elseif (($session = SessionHelper::loadUserSession($event->getPlayer())) instanceof UserSession) {
                Loader::getInstance()->getLogger()->debug("Restored session from file for player {$session->getPlayer()->getName()}");
            } else ($session = SessionHelper::createUserSession($event->getPlayer()));
        }
    }

    public function onSessionLoad(MWESessionLoadEvent $event): void
    {
        Loader::getInstance()->wailaBossBar->addPlayer($event->getPlayer());
        if (Loader::hasScoreboard()) {
            try {
                /** @var UserSession $session */
                if (($session = $event->getSession()) instanceof UserSession && $session->isSidebarEnabled()) {
                    $session->sidebar->handleScoreboard($session);
                }
            } catch (InvalidArgumentException $e) {
                Loader::getInstance()->getLogger()->logException($e);
            }
        }
    }

    /**
     * @param PlayerQuitEvent $event
     * @throws SessionException
     * @throws JsonException
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

    /**
     * TODO use tool classes
     * @param BlockBreakEvent $event
     * @throws Error
     * @throws SessionException
     * @throws InvalidArgumentException
     * @throws AssumptionFailedError
     */
    private function onBreakBlock(BlockBreakEvent $event): void
    {
        $session = SessionHelper::getUserSession($event->getPlayer());
        if (!$session instanceof UserSession) {
            return;
        }
        switch ($event->getItem()->getId()) {
            case ItemIds::WOODEN_AXE:
            {
                if (!$session->isWandEnabled()) {
                    $session->sendMessage(TF::RED . $session->getLanguage()->translateString("tool.wand.disabled"));
                    break;
                }
                $selection = $session->getLatestSelection() ?? $session->addSelection(new Selection($session->getUUID(), $event->getBlock()->getPos()->getWorld())); // TODO check if the selection inside of the session updates
                if (is_null($selection)) {
                    throw new Error("No selection created - Check the console for errors");
                }
                $selection->setPos1(new Position($event->getBlock()->getPos()->x, $event->getBlock()->getPos()->y, $event->getBlock()->getPos()->z, $event->getBlock()->getPos()->getWorld()));
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
     * @throws Error
     * @throws InvalidStateException
     * @throws SessionException
     * @throws InvalidArgumentException
     * @throws AssumptionFailedError
     */
    private function onRightClickBlock(PlayerInteractEvent $event): void
    {
        if (!is_null($event->getItem()->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE))) {
            $event->cancel();
            $session = SessionHelper::getUserSession($event->getPlayer());
            if (!$session instanceof UserSession) {
                return;
            }
            switch ($event->getItem()->getId()) {
                case ItemIds::WOODEN_AXE:
                {
                    if (!$session->isWandEnabled()) {
                        $session->sendMessage(TF::RED . $session->getLanguage()->translateString("tool.wand.disabled"));
                        break;
                    }
                    $selection = $session->getLatestSelection() ?? $session->addSelection(new Selection($session->getUUID(), $event->getBlock()->getPos()->getWorld())); // TODO check if the selection inside of the session updates
                    if (is_null($selection)) {
                        throw new Error("No selection created - Check the console for errors");
                    }
                    $selection->setPos2(new Position($event->getBlock()->getPos()->x, $event->getBlock()->getPos()->y, $event->getBlock()->getPos()->z, $event->getBlock()->getPos()->getWorld()));
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
     * @param PlayerInteractEvent $event
     * @throws Error
     * @throws InvalidStateException
     * @throws SessionException
     * @throws InvalidArgumentException
     * @throws UnexpectedTagTypeException
     * @throws AssumptionFailedError
     */
    private function onLeftClickBlock(PlayerInteractEvent $event): void
    {
        if (!is_null($event->getItem()->getNamedTag()->getTag(API::TAG_MAGIC_WE))) {
            $event->cancel();
            $session = SessionHelper::getUserSession($event->getPlayer());
            if (!$session instanceof UserSession) {
                return;
            }
            switch ($event->getItem()->getId()) {
                case ItemIds::WOODEN_AXE:
                {
                    if (!$session->isWandEnabled()) {
                        $session->sendMessage(TF::RED . $session->getLanguage()->translateString("tool.wand.disabled"));
                        break;
                    }
                    $selection = $session->getLatestSelection() ?? $session->addSelection(new Selection($session->getUUID(), $event->getBlock()->getPos()->getWorld())); // TODO check if the selection inside of the session updates
                    if (is_null($selection)) {
                        throw new Error("No selection created - Check the console for errors");
                    }
                    $selection->setPos1(new Position($event->getBlock()->getPos()->x, $event->getBlock()->getPos()->y, $event->getBlock()->getPos()->z, $event->getBlock()->getPos()->getWorld()));
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
            if (!$session instanceof UserSession) {
                return;
            }
            $target = $event->getPlayer()->getTargetBlock(Loader::getInstance()->getToolDistance());
            $brush = $session->getBrushFromItem($event->getItem());
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
                if (!$session instanceof UserSession) {
                    return;
                }
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
            } elseif (!is_null($event->getItem()->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE))) {
                $event->cancel();
                $event->getPlayer()->getInventory()->remove($event->getItem());
            }
        } catch (Exception $e) {
        }
    }

    public function onSelectionChange(MWESelectionChangeEvent $event): void
    {
        Loader::getInstance()->getLogger()->debug("Called " . $event->getEventName());
        if (($session = $event->getSession()) instanceof UserSession && ($player = $event->getPlayer()) !== null) {
            /** @var UserSession $session */
            $session->sidebar->handleScoreboard($session);
        }
    }
}
