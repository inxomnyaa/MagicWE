<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\asset;

use BlockHorizons\libschematic\Schematic;
use CortexPE\Commando\BaseCommand;
use Exception;
use InvalidArgumentException;
use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\libstructure\format\MCStructure;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\session\data\Asset;
use xenialdan\MagicWE2\session\UserSession;
use function array_keys;
use function array_values;
use function count;
use function mkdir;
use function pathinfo;
use function time;
use function var_dump;
use const DIRECTORY_SEPARATOR;
use const PATHINFO_BASENAME;
use const PATHINFO_DIRNAME;

class AssetCommand extends BaseCommand
{
	/**
	 * This is where all the arguments, permissions, sub-commands, etc would be registered
	 * @throws InvalidArgumentException
	 */
	protected function prepare(): void
	{
		#$this->registerSubCommand(new BrushNameCommand("name", "Get name or rename a brush"));
		$this->setPermission("we.command.asset");//TODO perm
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
	{
		$lang = Loader::getInstance()->getLanguage();
		if ($sender instanceof Player && SessionHelper::hasSession($sender)) {
			try {
				$lang = SessionHelper::getUserSession($sender)->getLanguage();
			} catch (SessionException $e) {
			}
		}
		if (!$sender instanceof Player) {
			$sender->sendMessage(TF::RED . $lang->translateString('error.runingame'));
			return;
		}
		/** @var Player $sender */
		try {
			$session = SessionHelper::getUserSession($sender);
			if (!$session instanceof UserSession) {
				throw new SessionException($lang->translateString('error.nosession', [Loader::getInstance()->getName()]));
			}
			$form = (new SimpleForm(function (Player $player, $data) use ($lang, $session) {
				try {
					$store = Loader::$assetCollection;//TODO allow private assets again
					switch ($data) {
						case 'ui.asset.create.fromclipboard':
						{
							//create clipboard asset
							//input Name
							//toggle lock
							//toggle shared asset
							//type dropdown?
							$clipboard = $session->getCurrentClipboard();
							if (!$clipboard instanceof SingleClipboard) {
								$player->sendMessage($lang->translateString('error.noclipboard'));
								break;
							}
							$asset = new Asset($clipboard->getCustomName(), $clipboard, false, $player->getXuid(), false);
							$player->sendForm($asset->getSettingForm());
							break;
						}
						case 'ui.asset.save':
						{
							//save asset
							//dropdown asset
							//dropdown type
							$form = (new CustomForm(function (Player $player, $data) use (/*$lang, $session,*/ $store) {
								[$filename, $type] = $data;
								/** @var Asset $asset */
								$asset = $store->assets[$filename];
								$player->sendMessage('Saving ' . $asset);
								//TODO async, convert
								if ($type === Asset::TYPE_SCHEMATIC) {
									if ($asset->structure instanceof Schematic) {
										$file = pathinfo($asset->filename, PATHINFO_BASENAME) . '_' . time() . '.schematic';
										$e = ($asset->shared ? '' : ($asset->ownerXuid === null ? '' : $asset->ownerXuid . DIRECTORY_SEPARATOR));
										$file = Loader::getInstance()->getDataFolder() . 'assets' . DIRECTORY_SEPARATOR . $e . $file;
										mkdir(pathinfo($file, PATHINFO_DIRNAME), 7777, true);
										$asset->structure->save($file);
										$player->sendMessage("Saved as $file");
									}
									if ($asset->structure instanceof MCStructure || $asset->structure instanceof SingleClipboard) {
										$file = pathinfo($asset->filename, PATHINFO_BASENAME) . '_' . time() . '.schematic';
										$e = ($asset->shared ? '' : ($asset->ownerXuid === null ? '' : $asset->ownerXuid . DIRECTORY_SEPARATOR));
										$file = Loader::getInstance()->getDataFolder() . 'assets' . DIRECTORY_SEPARATOR . $e . $file;
										@mkdir(pathinfo($file, PATHINFO_DIRNAME), 7777, true);
										$asset->toSchematic()->save($file);
										$player->sendMessage("Saved as $file");
									}
								}
								if ($asset->structure instanceof MCStructure && $type === Asset::TYPE_MCSTRUCTURE) {
									#$asset->structure->save($asset->filename.'.mcstructure');
									$player->sendMessage('TODO');
								}
								//$asset->saveAs() //TODO
							}))
								->setTitle(Loader::PREFIX_FORM . TF::BOLD . TF::DARK_PURPLE . $lang->translateString('ui.asset.select'));
							$options = [];
							foreach ($store->getUnlockedAssets() as $asset) {
								$options[$asset->filename] = $asset->filename;
							}
							$form->addDropdown("Asset", array_values($options))
								->addDropdown("File type", [Asset::TYPE_SCHEMATIC, Asset::TYPE_MCSTRUCTURE]);
							$player->sendForm($form);
							break;
						}
						case 'ui.asset.settings':
						{
							//save asset
							//dropdown asset
							//dropdown type
							$form = (new CustomForm(function (Player $player, $data) use (/*$lang, $session,*/ $store) {
								[$filename] = $data;
								/** @var Asset $asset */
								$asset = $store->assets[$filename];
								$player->sendForm($asset->getSettingForm());
							}))
								->setTitle(Loader::PREFIX_FORM . TF::BOLD . TF::DARK_PURPLE . $lang->translateString('ui.asset.select'));
							$options = [];
							foreach (Loader::$assetCollection->getAll() as $asset) {//TODO allow private assets again
								$options[$asset->filename] = $asset->filename;
							}
							$form->addDropdown("Asset", array_values($options));
							$player->sendForm($form);
							break;
						}
						case 'ui.asset.global':
						{
							$menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
							$store = Loader::$assetCollection;
							foreach ($store->getSharedAssets() as $asset) {
								$menu->getInventory()->addItem($asset->toItem());
							}
							$menu->send($player, "Shared assets (" . count($store->getSharedAssets()) . ")");
							break;
						}
						case 'ui.asset.private':
						{
							$menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
							$store = $session->getAssets();
							$playerAssets = $store->getPlayerAssets($player->getXuid());
							var_dump(array_keys($playerAssets), array_keys($store->getPlayerAssets()));
							foreach ($playerAssets as $key => $asset) {
								var_dump($key, $asset);
								$menu->getInventory()->addItem($asset->toItem());
							}
							$menu->send($player, "Private assets (" . count($playerAssets) . ")");
							break;
						}
					}
					return null;
				} catch (Exception $error) {
					$session->sendMessage(TF::RED . $lang->translateString('error'));
					$session->sendMessage(TF::RED . $error->getMessage());
					Loader::getInstance()->getLogger()->logException($error);
				}
			}))
				->setTitle(Loader::PREFIX_FORM . TF::BOLD . TF::DARK_PURPLE . $lang->translateString('ui.asset.title'))
				->setContent($lang->translateString('ui.asset.content'))//TODO
				->addButton($lang->translateString('ui.asset.private'), -1, "", 'ui.asset.private')
				->addButton($lang->translateString('ui.asset.global'), -1, "", 'ui.asset.global')
				->addButton($lang->translateString('ui.asset.create.fromclipboard'), -1, "", 'ui.asset.create.fromclipboard')
				->addButton($lang->translateString('ui.asset.settings'), -1, "", 'ui.asset.settings')
				->addButton($lang->translateString('ui.asset.save'), -1, "", 'ui.asset.save');
			$sender->sendForm($form);
		} catch (Exception $error) {
			$sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
			$sender->sendMessage($this->getUsage());
		}
	}
}
