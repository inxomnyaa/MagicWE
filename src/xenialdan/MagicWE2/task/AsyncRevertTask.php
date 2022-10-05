<?php

namespace xenialdan\MagicWE2\task;

use Exception;
use Generator;
use InvalidArgumentException;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\Position;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use xenialdan\MagicWE2\clipboard\RevertClipboard;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\AsyncWorld;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\session\UserSession;
use function count;
use function igbinary_serialize;
use function igbinary_unserialize;
use function iterator_to_array;

class AsyncRevertTask extends MWEAsyncTask{

	public const TYPE_UNDO = 0;
	public const TYPE_REDO = 1;

	/** @var string */
	private string $clipboard;
	/** @var int */
	private int $type;

	/**
	 * AsyncRevertTask constructor.
	 *
	 * @param UuidInterface   $sessionUUID
	 * @param RevertClipboard $clipboard
	 * @param int             $type The type of clipboard pasting.
	 */
	public function __construct(UuidInterface $sessionUUID, RevertClipboard $clipboard, int $type = self::TYPE_UNDO){
		$this->sessionUUID = $sessionUUID->toString();
		$this->start = microtime(true);
		$this->clipboard = igbinary_serialize($clipboard);
		$this->type = $type;
		$this->manager = AsyncWorld::fromRevertClipboard($clipboard);
		$world = Server::getInstance()->getWorldManager()->getWorld($clipboard->worldId);
		foreach($clipboard->chunks as $hash => $chunk){
			World::getXZ($hash, $x, $z);
			if($type === self::TYPE_REDO){
				$this->manager->setChunk($x, $z, $world->getChunk($x, $z));
			}
		}
	}

	/**
	 * Actions to execute when run
	 *
	 * @return void
	 * @throws Exception
	 */
	public function onRun() : void{
		$this->publishProgress([0, "Start"]);
		/** @var RevertClipboard $clipboard */
		$clipboard = igbinary_unserialize($this->clipboard/*, ['allowed_classes' => [RevertClipboard::class]]*/);//TODO test pm4

		$manager = $this->manager;
		$oldBlocks = [];
		if($this->type === self::TYPE_UNDO)
			$oldBlocks = iterator_to_array($this->undoChunks($manager, $clipboard));
		if($this->type === self::TYPE_REDO){
			$oldBlocks = iterator_to_array($this->redoChunks($manager, $clipboard));
		}
		$chunks = $manager->getChunks();
		$this->setResult([$chunks, $oldBlocks]);
	}

	/**
	 * @param AsyncWorld      $manager
	 * @param RevertClipboard $clipboard
	 *
	 * @return Generator
	 * @throws InvalidArgumentException
	 * @phpstan-return Generator<int, array{int, Position|null}, void, void>
	 */
	private function undoChunks(AsyncWorld &$manager, RevertClipboard $clipboard) : Generator{
		$count = count($clipboard->blocksAfter);
		$changed = 0;
		$this->publishProgress([0, "Reverted $changed blocks out of $count"]);
		//$block is "data" array
		foreach($clipboard->blocksAfter as $block){
			$block = self::singleDataToBlock($block);//turn data into real block
			$original = $manager->getBlockAt($block->getPosition()->x, $block->getPosition()->y, $block->getPosition()->z);
			yield self::singleBlockToData($original, $block->getPosition());
			$manager->setBlockAt($block->getPosition()->getFloorX(), $block->getPosition()->getFloorY(), $block->getPosition()->getFloorZ(), $block);
			$changed++;
			$this->publishProgress([$changed / $count, "Reverted $changed blocks out of $count"]);
		}
	}

	/**
	 * @param AsyncWorld      $manager
	 * @param RevertClipboard $clipboard
	 *
	 * @return Generator
	 * @throws InvalidArgumentException
	 * @phpstan-return Generator<int, array{int, Position|null}, void, void>
	 */
	private function redoChunks(AsyncWorld &$manager, RevertClipboard $clipboard) : Generator{
		$count = count($clipboard->blocksAfter);
		$changed = 0;
		$this->publishProgress([0, "Redone $changed blocks out of $count"]);
		//$block is "data" array
		foreach($clipboard->blocksAfter as $block){
			$block = self::singleDataToBlock($block);//turn data into real block
			$original = $manager->getBlockAt($block->getPosition()->x, $block->getPosition()->y, $block->getPosition()->z);
			yield self::singleBlockToData($original, $block->getPosition());
			$manager->setBlockAt($block->getPosition()->getFloorX(), $block->getPosition()->getFloorY(), $block->getPosition()->getFloorZ(), $block);
			$changed++;
			$this->publishProgress([$changed / $count, "Redone $changed blocks out of $count"]);
		}
	}

	/**
	 * @throws AssumptionFailedError
	 */
	public function onCompletion() : void{
		try{
			$session = SessionHelper::getSessionByUUID(Uuid::fromString($this->sessionUUID));
			if($session instanceof UserSession) $session->getBossBar()->hideFromAll();
		}catch(SessionException $e){
			Loader::getInstance()->getLogger()->logException($e);
			$session = null;
		}
		[$chunks, $oldBlocks] = $this->getResult();
		/** @var RevertClipboard $clipboard */
		$clipboard = igbinary_unserialize($this->clipboard/*, ['allowed_classes' => [RevertClipboard::class]]*/);//TODO test pm4
		$clipboard->chunks = $chunks;
		$totalCount = $changed = count($clipboard->blocksAfter);
		$clipboard->blocksAfter = $oldBlocks;//already is a array of data
		$world = $clipboard->getWorld();
		foreach($clipboard->chunks as $hash => $chunk){
			World::getXZ($hash, $x, $z);
			$world->setChunk($x, $z, $chunk);
		}
		if(!is_null($session)){
			switch($this->type){
				case self::TYPE_UNDO:
				{
					$session->sendMessage(TF::GREEN . $session->getLanguage()->translateString('task.revert.undo.success', [$this->generateTookString(), $changed, $totalCount]));
					$session->redoHistory->push($clipboard);
					break;
				}
				case self::TYPE_REDO:
				{
					$session->sendMessage(TF::GREEN . $session->getLanguage()->translateString('task.revert.redo.success', [$this->generateTookString(), $changed, $totalCount]));
					$session->undoHistory->push($clipboard);
					break;
				}
			}
		}
	}
}