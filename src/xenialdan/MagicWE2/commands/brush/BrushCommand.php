<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\brush;

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
use xenialdan\customui\windows\SimpleForm;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;
use xenialdan\MagicWE2\session\UserSession;
use xenialdan\MagicWE2\tool\Brush;
use xenialdan\MagicWE2\tool\BrushProperties;

class BrushCommand extends BaseCommand
{
	/**
	 * This is where all the arguments, permissions, sub-commands, etc would be registered
	 * @throws InvalidArgumentException
	 */
	protected function prepare(): void
	{
		$this->registerSubCommand(new BrushNameCommand("name", "Get name or rename a brush"));
		$this->setPermission("we.command.brush");
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
			$form = new SimpleForm(Loader::PREFIX . TF::BOLD . TF::DARK_PURPLE . $lang->translateString('ui.brush.title'), $lang->translateString('ui.brush.content'));
			$form->addButton(new Button($lang->translateString('ui.brush.create')));
			$form->addButton(new Button($lang->translateString('ui.brush.getsession')));
			$form->addButton(new Button($lang->translateString('ui.brush.edithand')));
			$form->setCallable(function (Player $player, $data) use ($lang, $session) {
				try {
					switch ($data) {
						case $lang->translateString('ui.brush.create'):
						{
							$brush = new Brush(new BrushProperties());
							if ($brush instanceof Brush) {
								$player->sendForm($brush->getForm());
							}
							break;
						}
						case $lang->translateString('ui.brush.getsession'):
						{
							$menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
							foreach ($session->getBrushes() as $brush) {
								var_dump($brush);
								$menu->getInventory()->addItem($brush->toItem());
							}
							$menu->send($player, "Session brushes");
							break;
						}
						case $lang->translateString('ui.brush.edithand'):
						{
							$brush = $session->getBrushFromItem($player->getInventory()->getItemInHand());
							if ($brush instanceof Brush) {
								$player->sendForm($brush->getForm(false));
							}
							break;
						}
					}
					return null;
				} catch (Exception $error) {
					$session->sendMessage(TF::RED . $lang->translateString('error'));
					$session->sendMessage(TF::RED . $error->getMessage());
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
