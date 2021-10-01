<?php

namespace xenialdan\MagicWE2\task;

use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\scheduler\AsyncTask;
use pocketmine\world\Position;
use Ramsey\Uuid\Uuid;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\Progress;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\session\UserSession;

abstract class MWEAsyncTask extends AsyncTask
{
	/** @var string */
	public string $sessionUUID;
	/** @var float */
	public float $start;

	public function onProgressUpdate($progress): void
	{
		if (!$progress instanceof Progress) {//TODO Temp fix until all async tasks are modified
			$progress = new Progress($progress[0] / 100, $progress[1]);
		}
		try {
			$session = SessionHelper::getSessionByUUID(Uuid::fromString($this->sessionUUID));
			/** @var Progress $progress */
			if ($session instanceof UserSession) $session->getBossBar()->setPercentage($progress->progress)->setSubTitle(str_replace("%", "%%%%", $progress->string . " | " . floor($progress->progress * 100) . "%"));
			else $session->sendMessage($progress->string . " | " . floor($progress->progress * 100) . "%");//TODO remove, debug
		} catch (SessionException $e) {
			//TODO log?
		}
	}

	public function generateTookString(): string
	{
		return date("i:s:", (int)(microtime(true) - $this->start)) . round(microtime(true) - $this->start, 1, PHP_ROUND_HALF_DOWN);
	}

	/**
	 * Turns A block into an array that doesn't get fucked by anonymous classes when serialized
	 * @param Block $block
	 * @param Position|null $position
	 * @return array{int, Position|null}
	 */
	public static function singleBlockToData(Block $block, ?Position $position = null): array
	{
		/** @noinspection PhpInternalEntityUsedInspection */
		return [$block->getFullId(), $position ?? $block->getPosition()];
	}

	/**
	 * Turns ALL blocks into an array that doesn't get fucked by anonymous classes when serialized
	 * @param Block[] $blocks
	 * @return array<array{int, Position|null}>
	 */
	public static function multipleBlocksToData(array $blocks): array
	{
		$a = [];
		foreach ($blocks as $block)
			$a[] = self::singleBlockToData($block);
		return $a;
	}

	/**
	 * Turns a SINGLE array from singleBlockToData back into a block
	 * @param array{int, Position|null} $data
	 * @return Block
	 */
	protected static function singleDataToBlock(array $data): Block
	{
		$block = BlockFactory::getInstance()->fromFullBlock($data[0]);
		/** @var Position $pos */
		$pos = $data[1];
		$block->getPosition()->world = $pos->world;
		$block->getPosition()->x = $pos->x;
		$block->getPosition()->y = $pos->y;
		$block->getPosition()->z = $pos->z;
		return $block;
	}

	/**
	 * Turns back MULTIPLE data from singleBlockToData into blocks
	 * @param array<array{int, Position|null}> $hackedBlockData
	 * @return Block[]
	 */
	public static function multipleDataToBlocks(array $hackedBlockData): array
	{
		$a = [];
		foreach ($hackedBlockData as $datum) {
			$a[] = self::singleDataToBlock($datum);
		}
		return $a;
	}
}