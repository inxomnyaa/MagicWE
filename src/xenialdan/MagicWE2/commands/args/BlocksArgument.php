<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\commands\args;

use CortexPE\Commando\args\RawStringArgument;
use InvalidArgumentException as InvalidArgumentExceptionAlias;
use pocketmine\block\utils\InvalidBlockStateException;
use pocketmine\command\CommandSender;
use pocketmine\item\LegacyStringToItemParserException;
use pocketmine\nbt\UnexpectedTagTypeException;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use xenialdan\MagicWE2\exception\BlockQueryAlreadyParsedException;
use xenialdan\MagicWE2\exception\SessionException;
use xenialdan\MagicWE2\helper\BlockPalette;
use xenialdan\MagicWE2\helper\SessionHelper;
use xenialdan\MagicWE2\Loader;

class BlocksArgument extends RawStringArgument
{

	public function canParse(string $testString, CommandSender $sender): bool
	{
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
	 * @throws LegacyStringToItemParserException
	 * @throws UnexpectedTagTypeException
	 */
	public function parse(string $argument, CommandSender $sender): BlockPalette
	{
		try {
			return BlockPalette::fromString($argument);
		} catch (BlockQueryAlreadyParsedException|InvalidArgumentExceptionAlias|InvalidBlockStateException $error) {
			if ($sender instanceof Player)
				SessionHelper::getUserSession($sender)->sendMessage('error.command-error');
			$sender->sendMessage(Loader::PREFIX . TF::RED . $error->getMessage());
		}
		return BlockPalette::CREATE();
	}
}