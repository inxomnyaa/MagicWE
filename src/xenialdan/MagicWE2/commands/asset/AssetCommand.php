<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\asset;

use BlockHorizons\libschematic\Schematic;
use CortexPE\Commando\BaseCommand;
use Exception;
use InvalidArgumentException;
use muqsit\invmenu\InvMenu;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\customui\elements\Button;
use xenialdan\customui\elements\Label;
use xenialdan\customui\elements\Toggle;
use xenialdan\customui\elements\UIElement;
use xenialdan\customui\windows\CustomForm;
use xenialdan\customui\windows\SimpleForm;
use xenialdan\libstructure\format\MCStructure;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\session\data\Asset;
use xenialdan\MagicWE2\session\data\AssetCollection;
use xenialdan\MagicWE2\session\UserSession;

class AssetCommand extends BaseCommand
{
	/**
	 * This is where all the arguments, permissions, sub-commands, etc would be registered
	 * @throws InvalidArgumentException
	 */
	protected function prepare(): void
	{
		#$this->registerSubCommand(new BrushNameCommand("name", "Get name or rename a brush"));
		$this->setPermission("we.command.brush");//TODO perm
	}

	/**
	 * @param CommandSender $sender
	 * @param string $aliasUsed
	 * @param mixed[] $args
	 */
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
			$form = new SimpleForm(Loader::PREFIX_FORM . TF::BOLD . TF::DARK_PURPLE . $lang->translateString('ui.asset.title'), $lang->translateString('ui.asset.content'));//TODO
			$form->addButton(new Button($lang->translateString('ui.asset.private')));
			$form->addButton(new Button($lang->translateString('ui.asset.global')));
			$form->addButton(new Button($lang->translateString('ui.asset.create.fromclipboard')));
			$form->addButton(new Button($lang->translateString('ui.asset.settings')));
			$form->addButton(new Button($lang->translateString('ui.asset.save')));
			$form->setCallable(function (Player $player, $data) use ($lang, $session) {
				try {
					$store = AssetCollection::getInstance();
					switch ($data) {
						case $lang->translateString('ui.asset.create.fromclipboard'):
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
						case $lang->translateString('ui.asset.save'):
						{
							//save asset
							//dropdown asset
							//dropdown type
							$form = new CustomForm(Loader::PREFIX_FORM . TF::BOLD . TF::DARK_PURPLE . $lang->translateString('ui.asset.select'));
							$options = [];
							foreach ($store->getUnlockedAssets() as $asset) {
								$options[$asset->filename] = $asset->filename;
							}
							$form->addDropdown("Asset", array_values($options));
							$form->addDropdown("File type", [Asset::TYPE_SCHEMATIC, Asset::TYPE_MCSTRUCTURE]);
							$form->setCallable(function (Player $player, $data) use ($lang, $session, $store) {
								[$filename, $type] = $data;
								/** @var Asset $asset */
								$asset = $store->assets->get($filename);
								$player->sendMessage('Saving ' . (string)$asset);
								//TODO async
								if ($asset->structure instanceof Schematic && $type === Asset::TYPE_SCHEMATIC) {
									$file = pathinfo($asset->filename, PATHINFO_BASENAME) . '_' . time() . '.schematic';
									$asset->structure->save($file);
									$player->sendMessage("Saved as $file");
								}
								if ($asset->structure instanceof MCStructure && $type === Asset::TYPE_MCSTRUCTURE) {
									#$asset->structure->save($asset->filename.'.mcstructure');
									$player->sendMessage('TODO');
								}
								//$asset->saveAs() //TODO
							});
							$player->sendForm($form);
							break;
						}
						case $lang->translateString('ui.asset.settings'):
						{
							//save asset
							//dropdown asset
							//dropdown type
							$form = new CustomForm(Loader::PREFIX_FORM . TF::BOLD . TF::DARK_PURPLE . $lang->translateString('ui.asset.select'));
							$options = [];
							foreach (AssetCollection::getInstance()->getAssets() as $asset) {
								$options[$asset->filename] = $asset->filename;
							}
							$form->addDropdown("Asset", array_values($options));
							$form->setCallable(function (Player $player, $data) use ($lang, $session, $store) {
								[$filename] = $data;
								/** @var Asset $asset */
								$asset = $store->assets->get($filename);
								$player->sendForm($asset->getSettingForm());
							});
							$player->sendForm($form);
							break;
						}
						case $lang->translateString('ui.asset.global'):
						{
							$menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
							#$store = Loader::$assetCollection;
							foreach ($store->getSharedAssets() as $asset) {
								$menu->getInventory()->addItem($asset->toItem());
							}
							$menu->send($player, "Shared assets (" . count($store->getSharedAssets()) . ")");
							break;
						}
						case $lang->translateString('ui.asset.private'):
						{
							$menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
							#$store = Loader::$assetCollection;
							$store = AssetCollection::getInstance();
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
			});
			$sender->sendForm($form);
		} catch (Exception $error) {
			$sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
			$sender->sendMessage($this->getUsage());
		}
	}

	/**
	 * @param UIElement[] $elements
	 * @param array $data
	 * @return array
	 */
	public static function generateLore(array $elements, array $data): array
	{
		$return = [];
		foreach ($elements as $i => $element) {
			if ($element instanceof Label) continue;
			if ($element instanceof Toggle) {
				$return[] = ($element->getText() . ": " . ($data[$i] ? "Yes" : "No"));
				continue;
			}
			$return[] = ($element->getText() . ": " . $data[$i]);
		}
		return $return;
	}
}
