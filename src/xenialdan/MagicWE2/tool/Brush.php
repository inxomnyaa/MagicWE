<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\tool;

use Exception;
use InvalidArgumentException;
use jojoe77777\FormAPI\CustomForm;
use JsonException;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\biome\BiomeRegistry;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use TypeError;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\exception\ActionNotFoundException;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\exception\ShapeNotFoundException;
use xenialdan\MagicWE2\helper\BlockPalette;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\shape\ShapeRegistry;
use xenialdan\MagicWE2\session\UserSession;
use xenialdan\MagicWE2\task\action\ActionRegistry;
use function array_flip;
use function array_intersect_key;
use function array_keys;
use function array_replace;
use function array_search;
use function array_slice;
use function array_values;
use function array_walk;
use function gettype;
use function is_bool;
use function is_int;
use function trim;
use function ucfirst;

class Brush extends WETool
{
	public const TAG_BRUSH_ID = "id";
	public const TAG_BRUSH_PROPERTIES = "properties";

	/** @var BrushProperties */
	public BrushProperties $properties;

	/**
	 * Brush constructor.
	 * @param BrushProperties $properties
	 */
	public function __construct(BrushProperties $properties)
	{
		$this->properties = $properties;
	}

	public function getName(): string
	{
		return $this->properties->getName();
	}

	/**
	 * @return Item
	 * @throws ActionNotFoundException
	 * @throws InvalidArgumentException
	 * @throws ShapeNotFoundException
	 * @throws JsonException
	 * @throws TypeError
	 */
	public function toItem(): Item
	{
		$item = VanillaItems::WOODEN_SHOVEL();
		$item->addEnchantment(new EnchantmentInstance(Loader::$ench));
		$uuid = $this->properties->uuid ?? Uuid::uuid4()->toString();
		$this->properties->uuid = $uuid;
		$properties = json_encode($this->properties, JSON_THROW_ON_ERROR);
		if (!is_string($properties)) throw new InvalidArgumentException("Brush properties could not be decoded");
		$item->getNamedTag()->setTag(API::TAG_MAGIC_WE_BRUSH,
			CompoundTag::create()
				->setString("id", $uuid)
				->setInt("version", $this->properties->version)
				->setString("properties", $properties)
		);
		$item->setCustomName(Loader::PREFIX . TF::BOLD . TF::LIGHT_PURPLE . $this->getName());
		$item->setLore($this->properties->generateLore());
		$item->setUnbreakable();
		return $item;
	}

	/**
	 * @param bool $new true if creating new brush
	 * @param array $errors
	 * @return CustomForm
	 * @throws Exception
	 * @throws AssumptionFailedError
	 */
	public function getForm(bool $new = true, array $errors = []): CustomForm
	{
		try {
			$errors = array_map(static function ($value): string {
				return TF::EOL . TF::RED . $value;
			}, $errors);
			$brushProperties = $this->properties ?? new BrushProperties();

			$dropdownShapeOptions = [];
			if ($new) {
				foreach (Loader::getShapeRegistry()::getShapes() as $name => $class) {
					if ($name === ShapeRegistry::CUSTOM) continue;
					$dropdownShapeOptions[(string)$name] = $class === $brushProperties->shape;
				}
			}
			$dropdownActionOptions = [];
			foreach (ActionRegistry::getActions() as $name => $class) {
				$dropdownActionOptions[$name] = $class === $brushProperties->action;
			}
			$dropdownBiomeOptions = [];
			foreach ((new ReflectionClass(BiomeIds::class))->getConstants() as $name => $value) {
				if ($value === BiomeIds::HELL) continue;
				$dropdownBiomeOptions[BiomeRegistry::getInstance()->getBiome($value)->getName()] = $value === $brushProperties->biomeId;
			}

			$form = (new CustomForm(function (Player $player, $data) use ($new, $dropdownShapeOptions, $dropdownActionOptions, $dropdownBiomeOptions) {
				if ($data === null) return;
				#var_dump(__LINE__, $data);
				#$data = array_slice($data, 0, 7);
				[$shape, $action, $name, $blocks, $filter, $biome, $hollow] = $data;
				if ($new) $shape = array_keys($dropdownShapeOptions)[$shape];
				//else $shape = array_keys($data)[0]??get_class($this->properties->shape);
				$action = array_keys($dropdownActionOptions)[$action];
				$biome = array_keys($dropdownBiomeOptions)[$biome];//TODO throw exception if not valid

				$extraData = [];
				#var_dump(__LINE__, array_slice($data, 7));
				$base = ShapeRegistry::getDefaultShapeProperties(($new ? ShapeRegistry::getShape($shape) : $this->properties->shape));
				$slice = array_values(array_slice($data, 7, null, true));//TODO use label?
				$j = 0;
				foreach ($base as $i => $value) {
					$extraData[$i] = is_int($value) ? (int)$slice[$j] : $slice[$j];//TODO enhance
					$j++;
				}
				#var_dump(__LINE__, $extraData);
				//prepare data
				$blocks = trim(TF::clean($blocks));
				$filter = trim(TF::clean($filter));

				$biomeNames = (new ReflectionClass(BiomeIds::class))->getConstants();
				$biomeNames = array_flip($biomeNames);
				unset($biomeNames[BiomeIds::HELL]);
				array_walk($biomeNames, static function (&$value, $key) {
					$value = BiomeRegistry::getInstance()->getBiome($key)->getName();
				});
				$biomeId = array_search($biome, $biomeNames, true);

				//error checks
				$error = [];
				try {
					$p = BlockPalette::fromString($blocks);
					if ($p->empty()) throw new AssumptionFailedError("Blocks cannot be empty!");
				} catch (Exception $ex) {
					$error['blocks'] = $ex->getMessage();
				}
				try {
					BlockPalette::fromString($filter);
				} catch (Exception $ex) {
					$error['filter'] = $ex->getMessage();
				}
				try {
					$shape = ($new ? ShapeRegistry::getShape($shape) : $this->properties->shape);
				} catch (Exception $ex) {
					$error['shape'] = $ex->getMessage();
				}
				try {
					$action = Loader::getActionRegistry()::getAction($action);
				} catch (Exception $ex) {
					$error['action'] = $ex->getMessage();
				}
				try {
					if (!is_int($biomeId)) throw new AssumptionFailedError("Biome not found");
				} catch (Exception $ex) {
					$error['biome'] = $ex->getMessage();
				}

				//Set properties (called before resending, so form contains errors)
				if (!empty(trim(TF::clean($name)))) $this->properties->customName = $name;
				if (!isset($error['shape'])) {
					$this->properties->shape = $shape;
					if (!$new && !empty($extraData))
						$this->properties->shapeProperties = $extraData;
				}
				if (!isset($error['action'])) $this->properties->action = $action;
				/*if (!isset($error['blocks']))*/
				$this->properties->blocks = $blocks;
				/*if (!isset($error['filter']))*/
				$this->properties->filter = $filter;
				$this->properties->hollow = $hollow;

				//Resend form upon error
				if (!empty($error)) {
					$player->sendForm($this->getForm($new, $error));
					return;
				}

				//Debug
				#print_r($extraData);
				try {
					$brush = $this;
					$session = SessionHelper::getUserSession($player);
					if (!$session instanceof UserSession) {
						throw new SessionException(Loader::getInstance()->getLanguage()->translateString('error.nosession', [Loader::getInstance()->getName()]));
					}
					if (!$new) {
						$session->getBrushes()->replaceBrush($brush);
					} else {
						$player->sendForm($this->getExtradataForm($this->properties->shape));
					}
				} catch (Exception $ex) {
					$player->sendMessage($ex->getMessage());
					Loader::getInstance()->getLogger()->logException($ex);
				}
			}))
				->setTitle("Brush settings");
			// Shape
			#$form->addElement(new Label((isset($errors['shape']) ? TF::RED : "") . "Shape" . ($errors['shape'] ?? "")));
			if ($new) {
				$form->addDropdown((isset($errors['shape']) ? TF::RED : "") . "Shape" . ($errors['shape'] ?? ""), array_keys($dropdownShapeOptions));
			} else {
				$form->addLabel($brushProperties->getShapeName());
			}
			// Action
			$form->addDropdown("Action", array_keys($dropdownActionOptions));
			// Name
			$form->addInput("Name", "Name", $new ? "" : $this->getName());
			// Blocks
			$form->addInput((isset($errors['blocks']) ? TF::RED : "") . "Blocks" . ($errors['blocks'] ?? ""), "grass,stone:1", $brushProperties->blocks);
			// Filter
			$form->addInput((isset($errors['filter']) ? TF::RED : "") . "Filter" . ($errors['filter'] ?? ""), "air", $brushProperties->filter);
			// Biome
			$form->addDropdown((isset($errors['biome']) ? TF::RED : "") . "Biome" . ($errors['biome'] ?? ""), array_keys($dropdownBiomeOptions));
			// Hollow
			$form->addToggle("Hollow", $brushProperties->hollow);
			// Extra properties
			if (!$new) {
				$form = $this->getExtradataForm($brushProperties->shape, $form);//TODO check if elements are added
			}
			// Function
			return $form;
		} catch (Exception $e) {
			throw new AssumptionFailedError("Could not create brush form");
		}
	}

	private function getExtradataForm(string $shapeClass, ?CustomForm $form = null): CustomForm
	{
		#foreach (($defaultReplaced = array_merge(ShapeRegistry::getDefaultShapeProperties($shapeClass), $this->properties->shapeProperties)) as $name => $value) {
		$base = ShapeRegistry::getDefaultShapeProperties($shapeClass);
		$defaultReplaced = array_replace($base, array_intersect_key($this->properties->shapeProperties, $base));
		$form = ($form ?? new CustomForm(function (Player $player, $data) use ($defaultReplaced, $base) {
				//TODO validation, resending etc.
				$extraData = [];
				$names = array_keys($defaultReplaced);
				foreach ($data as $index => $value) {
					if (is_int($base[$names[$index]])) $value = (int)$value;
					$extraData[$names[$index]] = $value;
				}
				$this->properties->shapeProperties = $extraData;

				$brush = $this;
				$session = SessionHelper::getUserSession($player);
				if (!$session instanceof UserSession) {
					throw new SessionException(Loader::getInstance()->getLanguage()->translateString('error.nosession', [Loader::getInstance()->getName()]));
				}
				$this->properties->uuid = Uuid::uuid4()->toString();
				$session->getBrushes()->addBrush($brush);
				$player->getInventory()->addItem($brush->toItem());
			}))
			->setTitle("Shape settings");
		foreach ($defaultReplaced as $name => $value) {
			if (is_bool($value)) $form->addToggle(ucfirst($name), $value);
			else $form->addInput(ucfirst($name), $name . " (" . gettype($value) . ")", (string)$value);
		}
		#var_dump($this->properties->shapeProperties);
		#var_dump('Base', $base);
		#var_dump('Default Replaced', $defaultReplaced);
		return $form;
	}
}