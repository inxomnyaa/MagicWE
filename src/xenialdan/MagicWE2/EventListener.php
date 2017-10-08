<?php

namespace xenialdan\MagicWE2;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\plugin\Plugin;

class EventListener implements Listener{
	public $owner;

	public function __construct(Plugin $plugin){
		$this->owner = $plugin;
	}

	public function onJoin(PlayerJoinEvent $event){
		#if (!isset(Loader::$selections[$event->getPlayer()->getLowerCaseName()])) Loader::$selections[$event->getPlayer()->getLowerCaseName()] = new Selection($event->getPlayer()->getLevel(), 0, 0, 0, 0, 0, 0);
	}
}