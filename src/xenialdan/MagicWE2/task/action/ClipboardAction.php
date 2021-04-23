<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\task\action;

use Generator;
use pocketmine\math\Vector3;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\selection\Selection;

abstract class ClipboardAction
{
	/** @var string */
	public string $prefix = "";
	/** @var string */
	public string $completionString = '{%name} succeed, took {%took}, {%changed} entries out of {%total} changed.';
	/** @var bool */
	public bool $addClipboard = false;
	/** @var null|Vector3 */
	public ?Vector3 $clipboardVector = null;

	/**
	 * @param string $sessionUUID
	 * @param Selection $selection
	 * @param null|int $changed
	 * @param SingleClipboard $clipboard
	 * @param string[] $messages
	 * @return Generator
	 */
	abstract public function execute(string $sessionUUID, Selection $selection, ?int &$changed, SingleClipboard $clipboard, array &$messages = []): Generator;

	abstract public static function getName(): string;

	/**
	 * @param Vector3|null $clipboardVector
	 */
	public function setClipboardVector(?Vector3 $clipboardVector): void
	{
		if ($clipboardVector instanceof Vector3) $clipboardVector = $clipboardVector->asVector3()->floor();
		$this->clipboardVector = $clipboardVector;
	}
}