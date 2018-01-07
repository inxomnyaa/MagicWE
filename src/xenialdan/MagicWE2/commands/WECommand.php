<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands;

use pocketmine\command\PluginCommand;
use pocketmine\plugin\Plugin;

abstract class WECommand extends PluginCommand{

	public function __construct($name, Plugin $owner){
		parent::__construct($name, $owner);
		$this->setPermission("we.command");
	}
}
