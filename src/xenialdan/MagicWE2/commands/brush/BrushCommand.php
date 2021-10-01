<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\brush;

use CortexPE\Commando\BaseCommand;
use Exception;
use InvalidArgumentException;
use jojoe77777\FormAPI\SimpleForm;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
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
					switch ($data) {
						case 'ui.brush.create':
						{
							$brush = new Brush(new BrushProperties());
							$player->sendForm($brush->getForm());
							break;
						}
						case 'ui.brush.getsession':
						{
							$menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
							foreach ($session->getBrushes()->getAll() as $brush) {
								$menu->getInventory()->addItem($brush->toItem());
							}
							$menu->send($player, "Session brushes");
							break;
						}
						case 'ui.brush.edithand':
						{
							$brush = $session->getBrushes()->getBrushFromItem($player->getInventory()->getItemInHand());
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
			}))
				->setTitle(Loader::PREFIX_FORM . TF::BOLD . TF::DARK_PURPLE . $lang->translateString('ui.brush.title'))
				->setContent($lang->translateString('ui.brush.content'))
				->addButton($lang->translateString('ui.brush.create'), -1, "", 'ui.brush.create')
				->addButton($lang->translateString('ui.brush.getsession'), -1, "", 'ui.brush.getsession')
				->addButton($lang->translateString('ui.brush.edithand'), -1, "", 'ui.brush.edithand');
			$sender->sendForm($form);
		} catch (Exception $error) {
			$sender->sendMessage(Loader::PREFIX . TF::RED . $lang->translateString('error.command-error'));
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
			$sender->sendMessage($this->getUsage());
		}
	}
}
