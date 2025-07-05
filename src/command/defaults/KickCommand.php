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
use function trim;

class KickCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"kick",
			KnownTranslationFactory::pocketmine_command_kick_description(),
			KnownTranslationFactory::commands_kick_usage()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_KICK);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(count($args) === 0){
			throw new InvalidCommandSyntaxException();
		}

		$name = array_shift($args);
		$reason = trim(implode(" ", $args));

		$matches = $sender->getServer()->getPlayersByPrefix($name);

		if(count($matches) === 0){
			$sender->sendMessage(KnownTranslationFactory::commands_generic_player_notFound()->prefix(TextFormat::RED));
			return false;
		}

		if(count($matches) > 1){
			$sender->sendMessage("§eMultiple players match '$name': §f" . implode(", ", array_map(fn(Player $p) => $p->getName(), $matches)));
			return false;
		}

		$player = $matches[0];
		$player->kick($reason !== ""
			? KnownTranslationFactory::pocketmine_disconnect_kick($reason)
			: KnownTranslationFactory::pocketmine_disconnect_kick_noReason()
		);

		Command::broadcastCommandMessage(
			$sender,
			$reason !== ""
				? KnownTranslationFactory::commands_kick_success_reason($player->getName(), $reason)
				: KnownTranslationFactory::commands_kick_success($player->getName())
		);

		return true;
	}
}
