<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\args;

use CortexPE\Commando\args\BaseArgument;
use InvalidArgumentException as InvalidArgumentExceptionAlias;
use pocketmine\command\CommandSender;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\exception\BlockQueryAlreadyParsedException;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\BlockPalette;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;

class BlocksArgument extends BaseArgument{
	public function getNetworkType() : int{
		return AvailableCommandsPacket::ARG_TYPE_STRING;
	}

	public function getTypeName() : string{
		return "string";
	}

	public function canParse(string $testString, CommandSender $sender) : bool{
		//TODO optimize; TODO check if returning false here triggers argument being passed to another $arg
		//TODO if regex match '/,(?![^\[]*])/' return true
		$pattern = '/[\w:]+(?:\[[\w=,]*])?%?\d*/';
		$r = preg_match_all($pattern, $testString);
		return $r !== false && $r >= 1;
	}

	/**
	 * @param string $argument
	 * @param CommandSender $sender
	 *
	 * @return BlockPalette
	 * @throws SessionException
	 */
	public function parse(string $argument, CommandSender $sender): BlockPalette
	{
		try {
			return BlockPalette::fromString($argument);
		} catch (BlockQueryAlreadyParsedException | InvalidArgumentExceptionAlias $error) {
			if ($sender instanceof Player)
				SessionHelper::getUserSession($sender)->sendMessage('error.command-error');
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
		}
		return BlockPalette::CREATE();
	}
}