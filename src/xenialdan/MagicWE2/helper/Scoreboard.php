<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use BadFunctionCallException;
use jackmd\scorefactory\ScoreFactory;
use OutOfBoundsException;
use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat as TF;
use ReflectionClass;
use ReflectionException;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\Selection;
use xenialdan\MagicWE2\session\UserSession;

class Scoreboard
{
	public function handleScoreboard(UserSession $session): void
	{
		$player = $session->getPlayer();
		if ($session->sidebarEnabled) {
			ScoreFactory::setScore($player, Loader::PREFIX . TF::BOLD . TF::LIGHT_PURPLE . "Sidebar");
			try {
				if ($session->getLatestSelection() !== null) {
					$line = 0;
					$selection = $session->getLatestSelection();
					ScoreFactory::setScoreLine($player, ++$line, TF::GOLD . $session->getLanguage()->translateString("spacer", ["Selection"]));
					ScoreFactory::setScoreLine($player, ++$line, TF::ITALIC . "Position: " . TF::RESET . "{$this->vecToString($selection->getPos1()->asVector3())} » {$this->vecToString($selection->getPos2()->asVector3())}");
					ScoreFactory::setScoreLine($player, ++$line, TF::ITALIC . "World: " . TF::RESET . $selection->getWorld()->getFolderName());
					ScoreFactory::setScoreLine($player, ++$line, TF::ITALIC . "Shape: " . TF::RESET . (new ReflectionClass($selection->shape))->getShortName());
					ScoreFactory::setScoreLine($player, ++$line, TF::ITALIC . "Size: " . TF::RESET . "{$this->vecToString(new Vector3($selection->getSizeX(),$selection->getSizeY(),$selection->getSizeZ()))} ({$selection->getShape()->getTotalCount()})");

					ScoreFactory::setScoreLine($player, ++$line, TF::GOLD . $session->getLanguage()->translateString("spacer", ["Settings"]));
					ScoreFactory::setScoreLine($player, ++$line, TF::ITALIC . "Tool Range: " . TF::RESET . Loader::getInstance()->getToolDistance());
					$editLimit = Loader::getInstance()->getEditLimit();
					ScoreFactory::setScoreLine($player, ++$line, TF::ITALIC . "Limit: " . TF::RESET . ($editLimit === -1 ? $this->boolToString(false) : $editLimit));
					ScoreFactory::setScoreLine($player, ++$line, TF::ITALIC . "Wand Tool: " . TF::RESET . $this->boolToString($session->isWandEnabled()));
					ScoreFactory::setScoreLine($player, ++$line, TF::ITALIC . "Debug Tool: " . TF::RESET . $this->boolToString($session->isDebugToolEnabled()));
					ScoreFactory::setScoreLine($player, ++$line, TF::ITALIC . "WAILA: " . TF::RESET . $this->boolToString($session->isWailaEnabled()));

					if (($cb = $session->getCurrentClipboard()) instanceof SingleClipboard) {
						ScoreFactory::setScoreLine($player, ++$line, TF::GOLD . $session->getLanguage()->translateString("spacer", ["Clipboard"]));
						/** @var SingleClipboard $cb */
						if ($cb->customName !== "")
							ScoreFactory::setScoreLine($player, ++$line, TF::ITALIC . "Name: " . TF::RESET . $cb->customName);
						if ($cb->selection instanceof Selection) {
							ScoreFactory::setScoreLine($player, ++$line, TF::ITALIC . "Shape: " . TF::RESET . (new ReflectionClass($cb->selection->shape))->getShortName());
							ScoreFactory::setScoreLine($player, ++$line, TF::ITALIC . "Size: " . TF::RESET . "{$this->vecToString(new Vector3($cb->selection->getSizeX(),$cb->selection->getSizeY(),$cb->selection->getSizeZ()))} ({$cb->getTotalCount()})");
						}
					}
					//todo current block palette, schematics, brushes
				}
			} catch (BadFunctionCallException | OutOfBoundsException | ReflectionException $e) {
			}
		}
	}

	private function vecToString(Vector3 $v): string
	{
		return TF::RESET . "[" . TF::RED . $v->getFloorX() . TF::RESET . ":" . TF::GREEN . $v->getFloorY() . TF::RESET . ":" . TF::BLUE . $v->getFloorZ() . TF::RESET . "]";
	}

	private function boolToString(bool $b): string
	{
		return $b ? TF::RESET . TF::GREEN . "On" . TF::RESET : TF::RESET . TF::RED . "Off" . TF::RESET;
	}
}