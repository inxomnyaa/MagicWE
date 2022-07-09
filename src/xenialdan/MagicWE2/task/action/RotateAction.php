<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\task\action;

use Exception;
use Generator;
use InvalidArgumentException;
use pocketmine\block\BlockFactory;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\helper\Progress;
use xenialdan\MagicWE2\selection\Selection;

class RotateAction extends ClipboardAction
{
	public const ROTATE_90 = 90;
	public const ROTATE_180 = 180;
	public const ROTATE_270 = 270;
	/** @var bool */
	public bool $addClipboard = true;
	/** @var string */
	public string $completionString = '{%name} succeed, took {%took}, rotated {%changed} blocks out of {%total}';
	/** @var int */
	private int $rotation;
	/** @var bool */
	public bool $aroundOrigin = true;

	public function __construct(int $rotation, bool $aroundOrigin = true)
	{
		if ($rotation !== self::ROTATE_90 && $rotation !== self::ROTATE_180 && $rotation !== self::ROTATE_270) throw new InvalidArgumentException("Invalid rotation $rotation given");
		$this->rotation = $rotation;
		$this->addClipboard = $aroundOrigin;
	}

	public static function getName(): string
	{
		return "Rotate";
	}

	/**
	 * @param string $sessionUUID
	 * @param Selection $selection
	 * @param null|int $changed
	 * @param SingleClipboard $clipboard
	 * @param string[] $messages
	 * @return Generator
	 * @throws Exception
	 */
	public function execute(string $sessionUUID, Selection $selection, ?int &$changed, SingleClipboard &$clipboard, array &$messages = []) : Generator{
		//TODO modify position. For now, just flip the blocks around their own axis
		$changed = 0;
		#$oldBlocks = [];
		$count = $clipboard->getTotalCount();
		yield new Progress(0, "");
		BlockFactory::getInstance();
//		/** @var BlockStatesParser $blockStatesParser */
//		$blockStatesParser = BlockStatesParser::getInstance();

		$clipboard = API::rotate($clipboard, $this->rotation);

		yield new Progress($changed / $count, "$changed/$count");
	}
}