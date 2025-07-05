<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function array_map;
use function array_shift;
use function count;
use function implode;

class TellCommand extends VanillaCommand {

	public function __construct() {
		parent::__construct(
			"tell",
			KnownTranslationFactory::pocketmine_command_tell_description(),
			KnownTranslationFactory::commands_message_usage(),
			["w", "msg"]
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_TELL);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) {
		if (count($args) < 2) {
			throw new InvalidCommandSyntaxException();
		}

		$targetName = array_shift($args);
		$matches = $sender->getServer()->getPlayersByPrefix($targetName);

		if (count($matches) === 0) {
			$sender->sendMessage(KnownTranslationFactory::commands_generic_player_notFound());
			return true;
		}

		if (count($matches) > 1) {
			$sender->sendMessage(TextFormat::YELLOW . "Multiple players match '$targetName': " .
				implode(", ", array_map(fn(Player $p) => $p->getName(), $matches)));
			return true;
		}

		$player = $matches[0];

		if ($player === $sender) {
			$sender->sendMessage(KnownTranslationFactory::commands_message_sameTarget()->prefix(TextFormat::RED));
			return true;
		}

		$message = implode(" ", $args);

		$sender->sendMessage(
			KnownTranslationFactory::commands_message_display_outgoing($player->getDisplayName(), $message)
				->prefix(TextFormat::GRAY . TextFormat::ITALIC)
		);

		$senderName = $sender instanceof Player ? $sender->getDisplayName() : $sender->getName();

		$player->sendMessage(
			KnownTranslationFactory::commands_message_display_incoming($senderName, $message)
				->prefix(TextFormat::GRAY . TextFormat::ITALIC)
		);

		Command::broadcastCommandMessage(
			$sender,
			KnownTranslationFactory::commands_message_display_outgoing($player->getDisplayName(), $message),
			false
		);

		return true;
	}
}
