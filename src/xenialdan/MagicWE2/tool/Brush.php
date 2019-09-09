<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\tool;

use pocketmine\item\Durable;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\level\biome\Biome;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\UUID;
use xenialdan\customui\elements\Dropdown;
use xenialdan\customui\elements\Input;
use xenialdan\customui\elements\Label;
use xenialdan\customui\elements\Toggle;
use xenialdan\customui\elements\UIElement;
use xenialdan\customui\windows\CustomForm;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\selection\shape\ShapeRegistry;
use xenialdan\MagicWE2\session\UserSession;
use xenialdan\MagicWE2\task\action\ActionRegistry;

class Brush extends WETool
{
    public const TAG_BRUSH_ID = "id";
    public const TAG_BRUSH_PROPERTIES = "properties";

    /** @var BrushProperties */
    public $properties;

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

    public function toItem(): Item
    {
        /** @var Durable $item */
        $item = Item::get(Item::WOODEN_SHOVEL);
        $item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Loader::FAKE_ENCH_ID)));
        $uuid = $this->properties->uuid ?? UUID::fromRandom()->toString();
        $this->properties->uuid = $uuid;
        $item->setNamedTagEntry(new CompoundTag(API::TAG_MAGIC_WE_BRUSH, [
            new StringTag("id", $uuid),
            new IntTag("version", $this->properties->version),
            new StringTag("properties", json_encode($this->properties))
        ]));
        $item->setCustomName(Loader::PREFIX . TF::BOLD . TF::DARK_PURPLE . $this->getName());
        $item->setLore($this->properties->generateLore());
        $item->setUnbreakable();
        return $item;
    }

    /**
     * @param bool $new true if creating new brush
     * @param array $errors
     * @return CustomForm
     * @throws \Exception
     */
    public function getForm(bool $new = true, array $errors = []): CustomForm
    {
        try {
            $errors = array_map(function ($value): string {
                return TF::EOL . TF::RED . $value;
            }, $errors);
            $brushProperties = $this->properties ?? new BrushProperties();
            $form = new CustomForm("Brush settings");
            // Shape
            #$form->addElement(new Label((isset($errors['shape']) ? TF::RED : "") . "Shape" . ($errors['shape'] ?? "")));
            if ($new) {
                $dropdownShape = new Dropdown((isset($errors['shape']) ? TF::RED : "") . "Shape" . ($errors['shape'] ?? ""));
                foreach (Loader::getShapeRegistry()::getShapes() as $name => $class) {
                    if ($name === ShapeRegistry::CUSTOM) continue;
                    $dropdownShape->addOption($name, $class === $brushProperties->shape);
                }
                $form->addElement($dropdownShape);
            } else {
                $form->addElement(new Label($brushProperties->getShapeName()));
            }
            // Action
            $dropdownAction = new Dropdown("Action");
            foreach (ActionRegistry::getActions() as $name => $class) {
                $dropdownAction->addOption($name, $class === $brushProperties->action);
            }
            $form->addElement($dropdownAction);
            // Name
            $form->addElement(new Input("Name", "Name", $new ? "" : $this->getName()));
            // Blocks
            $form->addElement(new Input((isset($errors['blocks']) ? TF::RED : "") . "Blocks" . ($errors['blocks'] ?? ""), "grass,stone:1", $brushProperties->blocks));
            // Filter
            $form->addElement(new Input((isset($errors['filter']) ? TF::RED : "") . "Filter" . ($errors['filter'] ?? ""), "air", $brushProperties->filter));
            // Biome
            $dropdownBiome = new Dropdown((isset($errors['biome']) ? TF::RED : "") . "Biome" . ($errors['biome'] ?? ""));
            foreach ((new \ReflectionClass(Biome::class))->getConstants() as $name => $value) {
                if ($value === Biome::MAX_BIOMES || $value === Biome::HELL) continue;
                $dropdownBiome->addOption(Biome::getBiome($value)->getName(), $value === $brushProperties->biomeId);
            }
            $form->addElement($dropdownBiome);
            // Hollow
            $form->addElement(new Toggle("Hollow", $brushProperties->hollow));
            // Extra properties
            if (!$new) {
                /** @var UIElement $element */
                foreach ($this->getExtradataForm($brushProperties->shape)->getContent() as $element) {
                    $form->addElement($element);
                }
            }
            // Function
            $form->setCallable(function (Player $player, $data) use ($form, $new) {
                #var_dump(__LINE__, $data);
                #$data = array_slice($data, 0, 7);
                [$shape, $action, $name, $blocks, $filter, $biome, $hollow] = $data;
                $extraData = [];
                #var_dump(__LINE__, array_slice($data, 7));
                $base = ShapeRegistry::getDefaultShapeProperties(ShapeRegistry::getShape($shape));
                foreach (array_slice($data, 7, null, true) as $i => $value) {
                    #var_dump($i, $value, gettype($value), gettype($base[lcfirst($form->getElement($i)->getText())]));
                    if (is_int($base[lcfirst($form->getElement($i)->getText())])) $value = intval($value);
                    $extraData[lcfirst($form->getElement($i)->getText())] = $value;//TODO
                }
                #var_dump(__LINE__, $extraData);
                //prepare data
                $blocks = trim(TF::clean($blocks));
                $filter = trim(TF::clean($filter));

                $biomeNames = (new \ReflectionClass(Biome::class))->getConstants();
                $biomeNames = array_flip($biomeNames);
                unset($biomeNames[Biome::MAX_BIOMES], $biomeNames[Biome::HELL]);
                array_walk($biomeNames, function (&$value, $key) {
                    $value = Biome::getBiome($key)->getName();
                });
                $biomeId = array_search($biome, $biomeNames);

                //error checks
                $error = [];
                try {
                    $m = [];
                    $e = false;
                    API::blockParser($blocks, $m, $e);
                    if ($e) throw new \Exception(implode(TF::EOL, $m));
                    if (empty($blocks)) throw new \Exception("Blocks cannot be empty!");
                } catch (\Exception $ex) {
                    $error['blocks'] = $ex->getMessage();
                }
                try {
                    $m = [];
                    $e = false;
                    API::blockParser($filter, $m, $e);
                    if ($e) throw new \Exception(implode(TF::EOL, $m));
                } catch (\Exception $ex) {
                    $error['filter'] = $ex->getMessage();
                }
                try {
                    $shape = Loader::getShapeRegistry()::getShape($shape);
                } catch (\Exception $ex) {
                    $error['shape'] = $ex->getMessage();
                }
                try {
                    $action = Loader::getActionRegistry()::getAction($action);
                } catch (\Exception $ex) {
                    $error['action'] = $ex->getMessage();
                }
                try {
                    if (!is_int($biomeId)) throw new \Exception("Biome not found");
                } catch (\Exception $ex) {
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
                    $session = API::getSession($player);
                    if (!$session instanceof UserSession) {
                        throw new \Exception("No session was created - probably no permission to use " . Loader::getInstance()->getName());
                    }
                    if (!$new) {
                        $session->replaceBrush($brush);
                    } else {
                        $player->sendForm($this->getExtradataForm($this->properties->shape));
                    }
                } catch (\Exception $ex) {
                    $player->sendMessage($ex->getMessage());
                    Loader::getInstance()->getLogger()->logException($ex);
                }
            });
            return $form;
        } catch (\Exception $e) {
            throw new \Exception("Could not create brush form");
        }
    }

    private function getExtradataForm(string $shapeClass): CustomForm
    {
        $form = new CustomForm("Shape settings");
        #foreach (($defaultReplaced = array_merge(ShapeRegistry::getDefaultShapeProperties($shapeClass), $this->properties->shapeProperties)) as $name => $value) {
        $base = ShapeRegistry::getDefaultShapeProperties($shapeClass);
        foreach (($defaultReplaced = array_replace($base, array_intersect_key($this->properties->shapeProperties, $base))) as $name => $value) {
            if (is_bool($value)) $form->addElement(new Toggle(ucfirst($name), (bool)$value));
            else $form->addElement(new Input(ucfirst($name), $name . " (" . gettype($value) . ")", strval($value)));
        }
        #var_dump($this->properties->shapeProperties);
        #var_dump('Base', $base);
        #var_dump('Default Replaced', $defaultReplaced);
        $form->setCallable(function (Player $player, $data) use ($form, $defaultReplaced, $base) {
            //TODO validation, resending etc.
            $extraData = [];
            $names = array_keys($defaultReplaced);
            foreach ($data as $index => $value) {
                if (is_int($base[$names[$index]])) $value = intval($value);
                $extraData[$names[$index]] = $value;
            }
            $this->properties->shapeProperties = $extraData;

            $brush = $this;
            $session = API::getSession($player);
            if (!$session instanceof UserSession) {
                throw new \Exception("No session was created - probably no permission to use " . Loader::getInstance()->getName());
            }
            $this->properties->uuid = UUID::fromRandom()->toString();
            $session->addBrush($brush);
            $player->getInventory()->addItem($brush->toItem());
        });
        return $form;
    }
}