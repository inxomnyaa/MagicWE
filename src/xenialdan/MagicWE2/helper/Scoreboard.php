<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use jackmd\scorefactory\ScoreFactory;
use jackmd\scorefactory\ScoreFactoryException;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\utils\TextFormat as TF;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\exception\SelectionException;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\session\UserSession;

class Scoreboard{
	public function handleScoreboard(UserSession $session) : void{
		$player = $session->getPlayer();
		if($session->isSidebarEnabled()){
			try{
				ScoreFactory::setObjective($player, Loader::PREFIX . TF::BOLD . TF::LIGHT_PURPLE . "Sidebar", SetDisplayObjectivePacket::SORT_ORDER_ASCENDING, SetDisplayObjectivePacket::DISPLAY_SLOT_SIDEBAR, "MWE_sidebar_default");
				ScoreFactory::sendObjective($player);
				if(($selection = $session->getLatestSelection()) !== null){
					$line = 0;
					ScoreFactory::setScoreLine($player, ++$line, TF::GOLD . $session->getLanguage()->translateString("spacer", ["Selection"]));
					try{
						ScoreFactory::setScoreLine($player, ++$line, TF::BOLD . " Position: " . TF::RESET . API::vecToString($selection->getPos1()->asVector3()) . " » " . API::vecToString($selection->getPos2()->asVector3()));
					}catch(SelectionException | RuntimeException | ScoreFactoryException){
					}
					ScoreFactory::setScoreLine($player, ++$line, TF::BOLD . " World: " . TF::RESET . $selection->getWorld()->getFolderName());
					if($selection->shape === null)
						ScoreFactory::setScoreLine($player, ++$line, TF::BOLD . " Shape: " . TF::RESET . 'N/A');
					else{
						ScoreFactory::setScoreLine($player, ++$line, TF::BOLD . " Shape: " . TF::RESET . (new ReflectionClass($selection->shape))->getShortName());
						ScoreFactory::setScoreLine($player, ++$line, TF::BOLD . " Size: " . TF::RESET . API::vecToString(new Vector3($selection->getSizeX(), $selection->getSizeY(), $selection->getSizeZ())) . " ({$selection->shape->getTotalCount()})");
					}

					ScoreFactory::setScoreLine($player, ++$line, TF::GOLD . $session->getLanguage()->translateString("spacer", ["Settings"]));
					ScoreFactory::setScoreLine($player, ++$line, TF::BOLD . " Tool Range: " . TF::RESET . Loader::getInstance()->getToolDistance());
					$editLimit = Loader::getInstance()->getEditLimit();
					ScoreFactory::setScoreLine($player, ++$line, TF::BOLD . " Limit: " . TF::RESET . ($editLimit === -1 ? API::boolToString(false) : $editLimit));
					ScoreFactory::setScoreLine($player, ++$line, TF::BOLD . " Wand Tool: " . TF::RESET . API::boolToString($session->isWandEnabled()));
					ScoreFactory::setScoreLine($player, ++$line, TF::BOLD . " Debug Tool: " . TF::RESET . API::boolToString($session->isDebugToolEnabled()));
					ScoreFactory::setScoreLine($player, ++$line, TF::BOLD . " WAILA: " . TF::RESET . API::boolToString($session->isWailaEnabled()));
					ScoreFactory::setScoreLine($player, ++$line, TF::BOLD . " Outline: " . TF::RESET . API::boolToString($session->isOutlineEnabled()));

					if(($cb = $session->getCurrentClipboard()) instanceof SingleClipboard){
						ScoreFactory::setScoreLine($player, ++$line, TF::GOLD . $session->getLanguage()->translateString("spacer", ["Clipboard"]));
						/** @var SingleClipboard $cb */
						if($cb->customName !== "")
							ScoreFactory::setScoreLine($player, ++$line, TF::BOLD . " Name: " . TF::RESET . $cb->customName);
						if($cb->selection instanceof Selection){
							if($cb->selection->shape === null)
								ScoreFactory::setScoreLine($player, ++$line, TF::BOLD . " Shape: " . TF::RESET . 'N/A');
							else
								ScoreFactory::setScoreLine($player, ++$line, TF::BOLD . " Shape: " . TF::RESET . (new ReflectionClass($cb->selection->shape))->getShortName());
							ScoreFactory::setScoreLine($player, ++$line, TF::BOLD . " Size: " . TF::RESET . API::vecToString(new Vector3($cb->selection->getSizeX(), $cb->selection->getSizeY(), $cb->selection->getSizeZ())) . " ({$cb->getTotalCount()})");
						}
					}
					//todo current block palette, schematics, brushes
				}
				ScoreFactory::sendLines($player);
			}catch(ReflectionException | ScoreFactoryException){
			}
		}
	}
}