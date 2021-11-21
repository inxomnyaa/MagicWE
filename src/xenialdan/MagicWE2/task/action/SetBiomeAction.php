<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\task\action;

use Exception;
use Generator;
use pocketmine\math\Vector2;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\helper\BlockPalette;
use xenialdan\MagicWE2\helper\Progress;
use xenialdan\MagicWE2\selection\Selection;

class SetBiomeAction extends TaskAction
{
	/** @var bool */
	public bool $addRevert = false;
	/** @var int */
	private int $biomeId;

	public function __construct(int $biomeId)
	{
		$this->biomeId = $biomeId;
	}

	public static function getName(): string
	{
		return "Set biome";
	}

	/**
	 * @param string $sessionUUID
	 * @param Selection $selection
	 * @param null|int $changed
	 * @param BlockPalette $newBlocks
	 * @param BlockPalette $blockFilter
	 * @param SingleClipboard $oldBlocksSingleClipboard blocks before the change
	 * @param string[] $messages
	 * @return Generator
	 * @throws Exception
	 */
	public function execute(string $sessionUUID, Selection $selection, ?int &$changed, BlockPalette $newBlocks, BlockPalette $blockFilter, SingleClipboard $oldBlocksSingleClipboard, array &$messages = []): Generator
	{
		$manager = $selection->getIterator()->getManager();
		$changed = 0;
		#$oldBlocks = [];
		$count = null;
		$lastProgress = new Progress(0, "");
		/** @var Vector2 $vec2 */
		foreach (($all = $selection->getShape()->getLayer($manager)) as $vec2) {
			if (is_null($count)) $count = count(iterator_to_array($all));
			$manager->getChunk($vec2->x >> 4, $vec2->y >> 4)->setBiomeId(abs($vec2->x % 16), abs($vec2->y % 16), $this->biomeId);
			$changed++;
			$progress = new Progress($changed / $count, "Changed Biome for $changed/$count blocks");
			if (floor($progress->progress * 100) > floor($lastProgress->progress * 100)) {
				yield $progress;
				$lastProgress = $progress;
			}
		}
	}
}