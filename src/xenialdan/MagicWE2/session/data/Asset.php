<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\session\data;

use BlockHorizons\libschematic\Schematic;
use Exception;
use InvalidArgumentException;
use jojoe77777\FormAPI\CustomForm;
use JsonSerializable;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\plugin\PluginException;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as TF;
use TypeError;
use xenialdan\libstructure\format\MCStructure;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\session\UserSession;
use function pathinfo;
use function var_dump;
use const PATHINFO_FILENAME;

class Asset implements JsonSerializable
{
	const TYPE_SCHEMATIC = 'schematic';
	const TYPE_MCSTRUCTURE = 'structure';
	const TYPE_CLIPBOARD = 'clipboard';//TODO consider if this is even worth the effort, or instead just convert it to mcstructure before storing

	public Schematic|SingleClipboard|MCStructure $structure;
	public string $filename;//used as identifier
	public string $displayname;
	public bool $locked = false;
	public ?string $ownerXuid = null;
	private ?Item $item = null;
	public bool $shared = false;

	/**
	 * Asset constructor.
	 * @param string $filename
	 * @param Schematic|MCStructure|SingleClipboard $value
	 * @param bool $locked
	 * @param string|null $ownerXuid
	 * @param bool $shared
	 */
	public function __construct(string $filename, Schematic|SingleClipboard|MCStructure $value, bool $locked = false, ?string $ownerXuid = null, bool $shared = false)
	{
		$this->filename = $filename;
		$this->displayname = pathinfo($filename, PATHINFO_FILENAME);
		$this->structure = $value;
		$this->locked = $locked;
		$this->ownerXuid = $ownerXuid;
		$this->shared = $shared;
	}

	public function getSize(): Vector3
	{
		if ($this->structure instanceof Schematic) return new Vector3($this->structure->getWidth(), $this->structure->getHeight(), $this->structure->getLength());
		if ($this->structure instanceof MCStructure) return $this->structure->getSize();
		if ($this->structure instanceof SingleClipboard) return new Vector3($this->structure->selection->getSizeX(), $this->structure->selection->getSizeY(), $this->structure->selection->getSizeZ());
		throw new Exception("Unknown structure type");
	}

	public function getTotalCount(): int
	{
		if ($this->structure instanceof Schematic || $this->structure instanceof MCStructure) return $this->getSize()->getFloorX() * $this->getSize()->getFloorY() * $this->getSize()->getFloorZ();
		if ($this->structure instanceof SingleClipboard) return $this->structure->getTotalCount();
		throw new Exception("Unknown structure type");
	}

	public function getOrigin(): Vector3
	{
		if ($this->structure instanceof Schematic) return new Vector3(0, 0, 0);
		if ($this->structure instanceof MCStructure) return $this->structure->getStructureWorldOrigin();
		if ($this->structure instanceof SingleClipboard) return $this->structure->position;
		throw new Exception("Unknown structure type");
	}

	/**
	 * @param bool $renew
	 * @return Item
	 * @throws InvalidArgumentException
	 */
	public function toItem(bool $renew = false): Item
	{
		if ($this->item !== null && !$renew) return $this->item;
		$item = ItemFactory::getInstance()->get(ItemIds::SCAFFOLDING);
		$item->addEnchantment(new EnchantmentInstance(Loader::$ench));
		try {
			['filename' => $filename, 'displayname' => $displayname, 'type' => $type, 'locked' => $locked, 'owner' => $owner, 'shared' => $shared] = $this->jsonSerialize();
			$item->getNamedTag()->setTag(API::TAG_MAGIC_WE_ASSET,
				CompoundTag::create()
					->setString("filename", $filename)
					->setString("displayname", $displayname)
					->setString("type", $type)
					->setByte("locked", $locked ? 1 : 0)
					->setString("owner", $owner)
					->setByte("shared", $shared ? 1 : 0)
			);
			$item->setCustomName(Loader::PREFIX_ASSETS . TF::BOLD . TF::LIGHT_PURPLE . $displayname);
			$item->setLore($this->generateLore());
			$this->item = $item;
		} catch (TypeError $e) {
			Loader::getInstance()->getLogger()->logException($e);
		}
		return $item;
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	private function generateLore(): array
	{
		$return = [];
		['filename' => $filename, 'displayname' => $displayname, 'type' => $type, 'locked' => $locked, 'owner' => $ownerXuid, 'shared' => $shared] = $this->jsonSerialize();
		if (pathinfo($filename, PATHINFO_FILENAME) !== $displayname)
			$return[] = TF::RESET . TF::BOLD . TF::GOLD . "Filename: " . TF::RESET . $filename;
		$return[] = TF::RESET . TF::BOLD . TF::GOLD . "Type: " . TF::RESET . ucfirst($type);
		$return[] = TF::RESET . TF::BOLD . TF::GOLD . "Locked: " . TF::RESET . ($locked ? TF::GREEN . "Yes" : TF::RED . "No");
		$return[] = TF::RESET . TF::BOLD . TF::GOLD . "Origin: " . TF::RESET . API::vecToString($this->getOrigin());
		$return[] = TF::RESET . TF::BOLD . TF::GOLD . "Size: " . TF::RESET . API::vecToString($this->getSize()) . " ({$this->getTotalCount()})";
		$return[] = TF::RESET . TF::BOLD . TF::GOLD . "Owner: " . TF::RESET . $ownerXuid ?? 'none';
		$return[] = TF::RESET . TF::BOLD . TF::GOLD . "Shared: " . TF::RESET . ($shared ? TF::GREEN . "Yes" : TF::RED . "No");
		return $return;
	}

	public function toSchematic(): Schematic
	{
		$structure = $this->structure;
		if ($structure instanceof Schematic) return $structure;
		if ($structure instanceof MCStructure) {
			$schematic = new Schematic();
			$blocks = iterator_to_array($structure->blocks());
			$schematic->setWidth((int)$this->getSize()->getX());
			$schematic->setHeight((int)$this->getSize()->getY());
			$schematic->setLength((int)$this->getSize()->getZ());
			$schematic->setBlockArray($blocks);
			return $schematic;
		}
		if ($structure instanceof SingleClipboard) {
			$schematic = new Schematic();
			$blocks = [];
			foreach ($structure->iterateEntries($x, $y, $z) as $block) {
				$blocks[] = API::setComponents($block->toBlock(), (int)$x, (int)$y, (int)$z);//turn BlockEntry to blocks
			}
			$schematic->setBlockArray($blocks);
			return $schematic;
		}
		throw new Exception("Unknown structure type");
	}

	public function toMCStructure(): MCStructure
	{
		$structure = $this->structure;
		if ($structure instanceof MCStructure) return $structure;
		throw new PluginException("Can't do this yet");
//		if($structure instanceof Schematic) {
//			/*$schematic = new ();
//			$blocks=[];
//			foreach ($structure->iterateEntries($x, $y, $z) as $blockEntry) {
//				$blocks[] = API::setComponents($blockEntry->toBlock(), (int)$x, (int)$y, (int)$z);//turn BlockEntry to blocks
//			}
//			$size = $structure->getSize();
//			#$aabb = new AxisAlignedBB(0,0,0,$size->getX(),$size->getY(),$size->getZ());
//			$schematic->setBlockArray(new $blocks);
//			$schematic->setWidth($size->getX());
//			$schematic->setHeight($size->getY());
//			$schematic->setLength($size->getZ());
//			return $schematic;*/
//		}
//		if($structure instanceof SingleClipboard) {
//			$schematic = new Schematic();
//			$blocks=[];
//			foreach ($structure->iterateEntries($x, $y, $z) as $blockEntry) {
//				$blocks[] = API::setComponents($blockEntry->toBlock(), (int)$x, (int)$y, (int)$z);//turn BlockEntry to blocks
//			}
//			$size = $structure->getSize();
//			#$aabb = new AxisAlignedBB(0,0,0,$size->getX(),$size->getY(),$size->getZ());
//			$schematic->setBlockArray(new $blocks);
//			$schematic->setWidth($size->getX());
//			$schematic->setHeight($size->getY());
//			$schematic->setLength($size->getZ());
//			return $schematic;
//		}
//		throw new PluginException("Wrong type");
	}

	public function __toString(): string
	{
		return 'Asset ' . implode(' ', $this->generateLore());
	}

	/**
	 * @param bool $new true if creating new brush
	 * @param array $errors
	 * @return CustomForm
	 * @throws Exception
	 * @throws AssumptionFailedError
	 */
	public function getSettingForm(bool $new = true, array $errors = []): CustomForm
	{
		//export clipboard
		//input Name
		//toggle lock
		//toggle shared asset
		//type dropdown?
		try {
			// Form
			//TODO display errors
			$form = (new CustomForm(function (Player $player, $data) /*use ($form, $new)*/ {
				var_dump(__LINE__, $data);
				[$filename, $this->locked, $shared] = $data;
				var_dump($filename, $this->locked ? "true" : "false", $shared ? "true" : "false");

				try {
					$session = SessionHelper::getUserSession($player);
					if (!$session instanceof UserSession) {
						throw new SessionException(Loader::getInstance()->getLanguage()->translateString('error.nosession', [Loader::getInstance()->getName()]));
					}
					if ($this->locked) {
						$session->sendMessage('error.asset.locked');
						return;
					}

					/*//Resend form upon error
					if (!empty($error)) {
						$player->sendForm($this->getForm($new, $error));
						return;
					}*/
					$this->filename = $filename;
					$this->displayname = pathinfo($filename, PATHINFO_FILENAME);
					$this->shared = $shared;
					var_dump($this->filename, $this->displayname);
					#var_dump(__LINE__, $this);
					#print_r(AssetCollection::getInstance()->assets);
					#print_r(AssetCollection::getInstance()->assets->toArray());
					#print_r(AssetCollection::getInstance()->assets->values()->toArray());
					#print_r(AssetCollection::getInstance()->assets->keys()->toArray());
					#print_r(AssetCollection::getInstance()->getAssets());
					if($shared){
						Loader::$assetCollection->assets[$this->filename] = $this;//overwrites
					}else{
						$session->getAssets()->assets[$this->filename] = $this;//overwrites
					}
					$player->sendMessage("Asset stored in " . ($shared ? 'global' : 'private') . ' collection');
					$player->sendMessage((string)$this);
					#$player->sendMessage((string)$this->toItem(true));
				} catch (Exception $ex) {
					$player->sendMessage($ex->getMessage());
					Loader::getInstance()->getLogger()->logException($ex);
				}
			}))
			->setTitle("Asset settings")
			->addInput("Filename", "Filename", $this->filename)
			->addToggle("Lock asset", $this->locked)
			->addToggle("Shared asset", $this->shared);
			foreach ($this->generateLore() as $value) {
				$form->addLabel($value);
			}
			return $form;
		} catch (Exception $e) {
			Loader::getInstance()->getLogger()->logException($e);
			throw new AssumptionFailedError("Could not create asset setting form: " . $e->getMessage());
		}
	}

	public function jsonSerialize(): array
	{
		return [
			'filename' => $this->filename,
			'displayname' => $this->displayname,
			//'type' => $this->structure instanceof Schematic ? self::TYPE_SCHEMATIC : ($this->structure instanceof MCStructure ? self::TYPE_MCSTRUCTURE : ($this->structure instanceof SingleClipboard ? self::TYPE_CLIPBOARD : '')),
			'type' => $this->structure instanceof Schematic ? self::TYPE_SCHEMATIC : ($this->structure instanceof MCStructure ? self::TYPE_MCSTRUCTURE : self::TYPE_CLIPBOARD),
			'locked' => $this->locked,
			'owner' => $this->ownerXuid ?? 'none',
			'shared' => $this->shared,
		];
	}
}