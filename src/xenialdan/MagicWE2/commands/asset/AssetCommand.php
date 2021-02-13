<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\asset;

use CortexPE\Commando\BaseCommand;
use Exception;
use InvalidArgumentException;
use muqsit\invmenu\InvMenu;
use pocketmine\block\VanillaBlocks;
use pocketmine\command\CommandSender;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\customui\elements\Button;
use xenialdan\customui\elements\Label;
use xenialdan\customui\elements\Toggle;
use xenialdan\customui\elements\UIElement;
use xenialdan\customui\windows\SimpleForm;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
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
			$form->addButton(new Button($lang->translateString('ui.asset.create.fromclipboard')));
			$form->addButton(new Button($lang->translateString('ui.asset.global')));
			$form->addButton(new Button($lang->translateString('ui.asset.private')));
			$form->setCallable(function (Player $player, $data) use ($lang, $session) {
				try {
					switch ($data) {
						case $lang->translateString('ui.asset.create.fromclipboard'):
						{
							//export clipboard
							//input Name
							//toggle lock
							//toggle shared asset
							//type dropdown?
							/*
                            $brush = new Brush(new BrushProperties());
                            if ($brush instanceof Brush) {
                                $player->sendForm($brush->getForm());
                            }*///TODO
							break;
						}
						case $lang->translateString('ui.asset.global'):
						{
							$menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
							$store = Loader::$assetCollection;
							foreach ($store->getAssetsGlobal() as $asset) {
								$menu->getInventory()->addItem($asset->toItem());
							}
							$menu->send($player, "Global assets");
							break;
						}
						case $lang->translateString('ui.asset.private'):
						{
							$menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
							$store = Loader::$assetCollection;
							$item = VanillaBlocks::CONCRETE()->asItem();
							$item->addEnchantment(new EnchantmentInstance(Loader::$ench));
							foreach ($store->getPlayerAssets($player->getXuid()) as $asset) {
								$menu->getInventory()->addItem($asset->toItem());
							}
							$menu->send($player, "Private assets");
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
