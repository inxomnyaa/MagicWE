<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\session;

use pocketmine\plugin\Plugin;
use Ramsey\Uuid\Uuid;
use SplDoublyLinkedList;
use xenialdan\MagicWE2\Loader;

class PluginSession extends Session
{
	/** @var Plugin */
	private Plugin $plugin;

	public function __construct(Plugin $plugin)
	{
		$this->plugin = $plugin;
		$this->setUUID(Uuid::uuid4());
		$this->undoHistory = new SplDoublyLinkedList();
		$this->redoHistory = new SplDoublyLinkedList();
	}

	public function getPlugin(): Plugin
	{
		return $this->plugin;
	}

	public function __toString()
	{
		return __CLASS__ .
			" UUID: " . $this->getUUID()->__toString() .
			" Plugin: " . $this->getPlugin()->getName() .
			" Selections: " . count($this->getSelections()) .
			" Latest: " . $this->getLatestSelectionUUID() .
			" Clipboards: " . count($this->getClipboards()) .
			" Current: " . $this->getCurrentClipboardIndex() .
			" Undos: " . count($this->undoHistory) .
			" Redos: " . count($this->redoHistory);
	}

	public function sendMessage(string $message): void
	{
		$this->plugin->getLogger()->info(Loader::PREFIX . $message);
	}
}