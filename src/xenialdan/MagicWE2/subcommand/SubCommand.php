<?php

namespace xenialdan\MagicWE2\subcommand;

use pocketmine\command\CommandSender;
use pocketmine\plugin\Plugin;

abstract class SubCommand{
	/** @var Plugin */
	private $plugin;

	/**
	 * @param Plugin $plugin
	 */
	public function __construct(Plugin $plugin){
		$this->plugin = $plugin;
	}

	/**
	 * @return Plugin
	 */
	public final function getPlugin(){
		return $this->plugin;
	}

	/**
	 * @param CommandSender $sender
	 * @return bool
	 */
	public abstract function canUse(CommandSender $sender);

	/**
	 * @return string
	 */
	public abstract function getUsage();

	/**
	 * @return string
	 */
	public abstract function getName();

	/**
	 * @return string
	 */
	public abstract function getDescription();

	/**
	 * @return string[]
	 */
	public abstract function getAliases();

	/**
	 * @param CommandSender $sender
	 * @param string[] $args
	 * @return bool
	 */
	public abstract function execute(CommandSender $sender, array $args);
}
