<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\task\action;

use Generator;
use InvalidArgumentException;
use pocketmine\block\Block;
use xenialdan\libblockstate\BlockEntry;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\helper\AsyncWorld;
use xenialdan\MagicWE2\helper\BlockPalette;
use xenialdan\MagicWE2\helper\Progress;
use xenialdan\MagicWE2\selection\Selection;

class CutAction extends TaskAction
{
	/** @var string */
	public string $completionString = '{%name} succeed, took {%took}, {%changed} blocks out of {%total} cut.';
#	/** @var bool */
#	public $addRevert = true;
	/** @var bool */
	public bool $addClipboard = true;

	public function __construct()
	{
	}

	public static function getName(): string
	{
		return "Cut";
	}

	/**
	 * @param SingleClipboard $oldBlocksSingleClipboard blocks before the change
	 * @param string[] $messages
	 *
	 * @throws InvalidArgumentException
	 */
	public function execute(string $sessionUUID, Selection $selection, AsyncWorld &$manager, ?int &$changed, BlockPalette $newBlocks, BlockPalette $blockFilter, SingleClipboard $oldBlocksSingleClipboard, array &$messages = []) : Generator{
		$changed = 0;
		$i = 0;
		#$oldBlocks = [];
		$count = $selection->getShape()->getTotalCount();
		$lastProgress = new Progress(0, "");
		$min = $selection->getShape()->getMinVec3();
		foreach($selection->getShape()->getBlocks($manager, $blockFilter) as $block){//TODO Merged iterator
			/** @var Block $new */
			$new = $newBlocks->blocks()->current();//TODO Merged iterator
			/** @noinspection PhpInternalEntityUsedInspection */
			if($new->getFullId() === $block->getFullId()) continue;//skip same blocks
			#$oldBlocks[] = API::setComponents($manager->getBlockAt($block->getPosition()->getFloorX(), $block->getPosition()->getFloorY(), $block->getPosition()->getFloorZ()),$block->x, $block->y, $block->z);
			#$newv3 = $block->getPosition()->subtractVector($min)->floor();//TODO check if only used for clipboard
			$newv3 = $block->getPosition()->asVector3();
			$oldBlocksSingleClipboard->addEntry($newv3->getFloorX(), $newv3->getFloorY(), $newv3->getFloorZ(), BlockEntry::fromBlock($block));
			$manager->setBlockAt($block->getPosition()->getFloorX(), $block->getPosition()->getFloorY(), $block->getPosition()->getFloorZ(), $new);
			/** @noinspection PhpInternalEntityUsedInspection */
			if($manager->getBlockFullIdAt($block->getPosition()->getFloorX(), $block->getPosition()->getFloorY(), $block->getPosition()->getFloorZ()) !== $block->getFullId()){
				$changed++;
			}
			$i++;
			$progress = new Progress($i / $count, "Changed $changed blocks out of $count");
			if(floor($progress->progress * 100) > floor($lastProgress->progress * 100)){
				yield $progress;
				$lastProgress = $progress;
			}
		}
	}
}