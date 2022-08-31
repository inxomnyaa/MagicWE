<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\tool;

use InvalidArgumentException;
use JsonSerializable;
use pocketmine\block\Block;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\Stick;
use pocketmine\item\VanillaItems;
use pocketmine\lang\Language;
use pocketmine\nbt\NoSuchTagException;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\UnexpectedTagTypeException;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use TypeError;
use xenialdan\libblockstate\BlockStatesParser;
use xenialdan\libblockstate\exception\BlockQueryParsingFailedException;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\ArrayUtils;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\session\UserSession;
use function array_key_exists;
use function current;
use function key;

class Debug extends WETool implements JsonSerializable{
	// tag:{DebugProperty:{"minecraft:jungle_leaves":"waterlogged","minecraft:waxed_cut_copper_stairs":"half","minecraft:stripped_jungle_log":"axis"}}


	public const TAG_DEBUG_PROPERTY = "DebugProperty";

	/**
	 * @var string[][][]
	 * key: "minecraft:stripped_jungle_log"
	 * key: "axis"
	 * value: ["x","y","z"]
	 */
	public array $states = [];

	public function __construct(){
	}

	/**
	 * @param Stick $item
	 *
	 * @return Debug
	 * @throws UnexpectedTagTypeException|NoSuchTagException
	 */
	public static function fromItem(Stick $item) : self{
		$debug = new self();
		if($item->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE) === null) throw new NoSuchTagException("Tag " . API::TAG_MAGIC_WE . " not found");
		if($item->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE_DEBUG) === null) throw new NoSuchTagException("Tag " . API::TAG_MAGIC_WE_DEBUG . " not found");
		$compoundTag = $item->getNamedTag()->getCompoundTag(self::TAG_DEBUG_PROPERTY);
		if($compoundTag !== null){
			//blockIdentifier is the namespaced name of the block
			//state is the most recently modified state of the block
			foreach($compoundTag->getValue() as $blockIdentifier => $state){
				$possibleBlockstates = Loader::getInstance()->getPossibleBlockstates($state->getValue());
				if(!empty($possibleBlockstates)){
					$debug->states[$blockIdentifier][$state->getValue()] = $possibleBlockstates;//TODO
				}
			}
		}
		return $debug;
	}

	/**
	 * @throws TypeError
	 */
	public function toItem(Language $lang) : Item{
		$item = VanillaItems::STICK()
			->addEnchantment(new EnchantmentInstance(Loader::$ench))
			->setCustomName(Loader::PREFIX . TF::BOLD . TF::LIGHT_PURPLE . $lang->translateString('tool.debug'))
			->setLore([
				TF::RESET . $lang->translateString('tool.debug.lore.1'),//TODO change lore strings in other languages than english
				TF::RESET . $lang->translateString('tool.debug.lore.2'),
				TF::RESET . $lang->translateString('tool.debug.lore.3'),
				TF::RESET . $lang->translateString('tool.debug.lore.4')
			]);
		$compound = CompoundTag::create();
		foreach($this->states as $blockIdentifier => $state){
			$compound->setString($blockIdentifier, (string) key($state));//TODO
		}
		#var_dump($compound);
		$item->getNamedTag()->setTag(API::TAG_MAGIC_WE_DEBUG, CompoundTag::create());
		$item->getNamedTag()->setTag(API::TAG_MAGIC_WE, CompoundTag::create());
		$item->getNamedTag()->setTag(self::TAG_DEBUG_PROPERTY, $compound);
		#var_dump($item->getNamedTag());
		return $item;
	}

	public function getCurrentState(string $blockIdentifier) : ?string{
		if(array_key_exists($blockIdentifier, $this->states)){
			return key($this->states[$blockIdentifier]);
		}
		return null;
	}

	public function advanceState(string $blockIdentifier, bool $reverse = false) : ?string{
		if(array_key_exists($blockIdentifier, $this->states)){
			return $reverse ? ArrayUtils::regressWrap($this->states[$blockIdentifier])[0] : ArrayUtils::advanceWrap($this->states[$blockIdentifier])[0];
		}
		return null;
	}

	public function getName() : string{
		return "Debug";//TODO Loader::PREFIX . TF::BOLD . TF::LIGHT_PURPLE . $lang->translateString('tool.debug')
	}

	/**
	 * @throws UnexpectedTagTypeException
	 * @throws InvalidArgumentException
	 * @throws AssumptionFailedError
	 */
	public function useSecondary(UserSession $session, Block $block){
		//cycle values
		/** @var BlockStatesParser $blockStatesParser */
		$blockStatesParser = BlockStatesParser::getInstance();
		$blockState = $blockStatesParser->getFromBlock($block);
		$stringId = $blockState->state->getId();

		#var_dump($this->states, $stringId);
		#var_dump(Loader::getInstance()->getPossibleBlockstates($stringId));

		$stateName = $this->getCurrentState($stringId);
		#var_dump($current);

		if($stateName === null){
			$session->sendMessage(TF::RED . "States uninitialized, left click the block first");
			return;
		}

		//$possibleBlockstates = Loader::getInstance()->getPossibleBlockstates($stringId);
		$blockStateTag = $blockState->state->getBlockState()->getCompoundTag("states")->getTag($stateName);
		#var_dump(__LINE__, $blockStateTag);
		$possibleBlockstates = $this->states[$stringId][$stateName];
		#var_dump(__LINE__, $possibleBlockstates, current($possibleBlockstates));
		ArrayUtils::setPointerToValue($possibleBlockstates, $blockStateTag->getValue());
		#var_dump(__LINE__, $possibleBlockstates, current($possibleBlockstates));
		//TODO add sneaking to reverse
		$session->getPlayer()->isSneaking() ? ArrayUtils::regressWrap($possibleBlockstates) : ArrayUtils::advanceWrap($possibleBlockstates);
		$newValue = current($possibleBlockstates);
		#var_dump($next);
		try{
			$newBlockState = $blockState->replaceBlockStateValues([$stateName => $newValue]);
			$block->getPosition()->getWorld()->setBlock($block->getPosition(), $newBlockState->getBlock());
			$session->getPlayer()->sendMessage(TF::GREEN . "State changed to " . $newValue);
		}catch(UnexpectedTagTypeException | BlockQueryParsingFailedException $e){
			$session->sendMessage(TF::RED . "Error occurred whilst changing $stateName to " . $newValue . ": " . $e->getMessage());
		}
	}

	public function usePrimary(UserSession $session, Block $block){
		//cycle states
		/** @var BlockStatesParser $blockStatesParser */
		$blockStatesParser = BlockStatesParser::getInstance();
		$blockState = $blockStatesParser->getFromBlock($block);
		$stringId = $blockState->state->getId();

		#var_dump($this->getCurrentState($stringId));
		#var_dump(__LINE__, $this->states);
		//$array = ($this->states[$stringId] ??= $this->stateToArray($blockState->state->getBlockState()->getCompoundTag("states")));
		$cstate = $blockState->state->getBlockState()->getCompoundTag("states");
		if($cstate === null || $cstate->count() === 0){
			$session->sendMessage(TF::RED . "Block has no states");
			return;
		}
		$this->states[$stringId] ??= $this->stateToArray($cstate);
		$array = &$this->states[$stringId];
		#var_dump(__LINE__, $this->states);
		#var_dump($this->getCurrentState($stringId));
		//advance state
		$session->getPlayer()->isSneaking() ? ArrayUtils::regressWrap($array) : ArrayUtils::advanceWrap($array);
		#$session->getPlayer()->sendTip("Targeted blockstate: " . $this->getCurrentState($stringId));
		$session->getPlayer()->sendActionBarMessage("Targeted blockstate: " . $this->getCurrentState($stringId));
	}

	private function stateToArray(CompoundTag $cstate) : array{
		$array = [];
		foreach($cstate->getValue() as $state => $value2){
			$possibleBlockstates = Loader::getInstance()->getPossibleBlockstates($state);
			#var_dump(__LINE__, $state, $value2, $possibleBlockstates);
			ArrayUtils::setPointerToValue($possibleBlockstates, $value2->getValue());
			$array[$state] = $possibleBlockstates;
		}
		#var_dump($array);
		return $array;
	}

	//TODO add rightClickAir and leftClickAir to Tool
	public function jsonSerialize() : array{
		return $this->toItem(Loader::getInstance()->getLanguage())->jsonSerialize();
	}
}