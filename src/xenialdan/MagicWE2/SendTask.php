<?php

namespace xenialdan\MagicWE2;

use pocketmine\level\particle\AngryVillagerParticle;
use pocketmine\level\particle\HeartParticle;
use pocketmine\level\particle\InkParticle;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\PluginTask;
use pocketmine\Server;

class SendTask extends PluginTask{

	public function __construct(Plugin $owner){
		parent::__construct($owner);
	}

	public function onRun(int $currentTick){
		foreach (Loader::$selections as $name => $selection){
			Server::getInstance()->getPlayer($name)->getLevel()->addParticle(new HeartParticle($selection->getMinVec3()));
			Server::getInstance()->getPlayer($name)->getLevel()->addParticle(new AngryVillagerParticle($selection->getMinVec3()->add($selection->getSizeX(), 0, 0)));
			Server::getInstance()->getPlayer($name)->getLevel()->addParticle(new AngryVillagerParticle($selection->getMinVec3()->add(0, $selection->getSizeY(), 0)));
			Server::getInstance()->getPlayer($name)->getLevel()->addParticle(new AngryVillagerParticle($selection->getMinVec3()->add($selection->getSizeX(), $selection->getSizeY(), 0)));
			Server::getInstance()->getPlayer($name)->getLevel()->addParticle(new AngryVillagerParticle($selection->getMinVec3()->add(0, 0, $selection->getSizeZ())));
			Server::getInstance()->getPlayer($name)->getLevel()->addParticle(new AngryVillagerParticle($selection->getMinVec3()->add($selection->getSizeX(), 0, $selection->getSizeZ())));
			Server::getInstance()->getPlayer($name)->getLevel()->addParticle(new InkParticle($selection->getMinVec3()->add($selection->getSizeX(), $selection->getSizeY(), $selection->getSizeZ())));
			Server::getInstance()->getPlayer($name)->getLevel()->addParticle(new AngryVillagerParticle($selection->getMinVec3()->add(0, $selection->getSizeY(), $selection->getSizeZ())));
		}
	}

	public function cancel(){
		$this->getHandler()->cancel();
	}
}