<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\tool;

use pocketmine\block\Block;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\Stick;
use pocketmine\item\VanillaItems;
use pocketmine\lang\Language;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\Tag;
use pocketmine\nbt\UnexpectedTagTypeException;
use pocketmine\utils\TextFormat as TF;
use TypeError;
use xenialdan\libblockstate\BlockStatesParser;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\ArrayUtils;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\session\UserSession;
use function array_key_exists;
use function current;
use function var_dump;

class Debug extends WETool{
	// tag:{DebugProperty:{"minecraft:jungle_leaves":"waterlogged","minecraft:waxed_cut_copper_stairs":"half","minecraft:stripped_jungle_log":"axis"}}


	public const TAG_DEBUG_PROPERTY = "DebugProperty";

	/**
	 * @var string[][]
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
	 * @throws UnexpectedTagTypeException
	 */
	public static function fromItem(Stick $item) : self{
		$debug = new self();
		$compoundTag = $item->getNamedTag()->getCompoundTag(API::TAG_MAGIC_WE_DEBUG);
		if($compoundTag !== null){
			var_dump(API::compoundToArray($compoundTag));
			//blockIdentifier is the namespaced name of the block
			//state is the most recently modified state of the block
			foreach($compoundTag->getValue() as $blockIdentifier => $state){
				$possibleBlockstates = Loader::getInstance()->getPossibleBlockstates($state->getValue());
				if(array_key_exists($state->getValue(), $possibleBlockstates)){
					$debug->states[$blockIdentifier][$state->getValue()] = $possibleBlockstates[$state->getValue()];//TODO
				}
			}
		}
		var_dump($debug->states);
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
				TF::RESET . $lang->translateString('tool.debug.lore.1'),//TODO change lore strings
				TF::RESET . $lang->translateString('tool.debug.lore.2'),
				TF::RESET . $lang->translateString('tool.debug.lore.3')
			]);
		$compound = CompoundTag::create();
		foreach($this->states as $blockIdentifier => $state){
			$compound->setString($blockIdentifier, (string) current($state));//TODO
		}
		$item->getNamedTag()->setTag(API::TAG_MAGIC_WE_DEBUG, $compound);
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

	public function getCurrentValue(string $blockIdentifier) : mixed{
		if(array_key_exists($blockIdentifier, $this->states)){
			return current($this->states[$blockIdentifier]);
		}
		return null;
	}

	public function getName() : string{
		return "Debug";//TODO Loader::PREFIX . TF::BOLD . TF::LIGHT_PURPLE . $lang->translateString('tool.debug')
	}

	public function useSecondary(UserSession $session, Block $block){
		//cycle values
		/** @var BlockStatesParser $blockStatesParser */
		$blockStatesParser = BlockStatesParser::getInstance();
		$blockState = $blockStatesParser->getFromBlock($block);
		$stringId = $blockState->state->getId();

		$array = Loader::getInstance()->getPossibleBlockstates($stringId);

		/** @var Tag $state */
		$state = current($this->states[$stringId]);
		var_dump($state->toString());
	}

	public function usePrimary(UserSession $session, Block $block){
		//cycle states
		/** @var BlockStatesParser $blockStatesParser */
		$blockStatesParser = BlockStatesParser::getInstance();
		$blockState = $blockStatesParser->getFromBlock($block);
		$stringId = $blockState->state->getId();

		//TODO add sneak
		var_dump($this->getCurrentState($stringId));
		var_dump($this->getCurrentValue($stringId));
		var_dump(__LINE__, $this->states);
		//$array = ($this->states[$stringId] ??= $this->stateToArray($blockState->state->getBlockState()->getCompoundTag("states")));
		$this->states[$stringId] ??= $this->stateToArray($blockState->state->getBlockState()->getCompoundTag("states"));
		$array = $this->states[$stringId];
		var_dump(__LINE__, $this->states);
		var_dump($this->getCurrentState($stringId));
		var_dump($this->getCurrentValue($stringId));
		var_dump(ArrayUtils::advanceWrap($array));
	}

	private function stateToArray(CompoundTag $cstate) : array{
		$array = [];
		foreach($cstate->getValue() as $state => $value2){
			$possibleBlockstates = Loader::getInstance()->getPossibleBlockstates($state);
			var_dump(__LINE__, $state, $value2, $possibleBlockstates);
			ArrayUtils::setPointerToValue($possibleBlockstates, $value2->getValue());
			$array[$state] = $possibleBlockstates;
		}
		var_dump($array);
		return $array;
	}

	//TODO add rightClickAir and leftClickAir to Tool
}