<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use function array_map;
use function array_shift;
use function count;
use function implode;
use function inet_pton;

class BanIpCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"ban-ip",
			KnownTranslationFactory::pocketmine_command_ban_ip_description(),
			KnownTranslationFactory::commands_banip_usage()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_BAN_IP);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(count($args) === 0){
			throw new InvalidCommandSyntaxException();
		}

		$value = array_shift($args);
		$reason = implode(" ", $args);

		$server = $sender->getServer();

		if(inet_pton($value) !== false){
			$this->processIPBan($value, $sender, $reason);
			Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_banip_success($value));
		}else{
			$matches = $server->getPlayersByPrefix($value);

			if(count($matches) === 0){
				$sender->sendMessage(KnownTranslationFactory::commands_banip_invalid());
				return false;
			}

			if(count($matches) > 1){
				$names = implode(", ", array_map(fn(Player $p) => $p->getName(), $matches));
				$sender->sendMessage("§eMultiple players match '$value': §f$names");
				return false;
			}

			$player = $matches[0];
			$ip = $player->getNetworkSession()->getIp();
			$this->processIPBan($ip, $sender, $reason);

			Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_banip_success_players($ip, $player->getName()));
		}

		return true;
	}

	private function processIPBan(string $ip, CommandSender $sender, string $reason) : void{
		$sender->getServer()->getIPBans()->addBan($ip, $reason, null, $sender->getName());

		foreach($sender->getServer()->getOnlinePlayers() as $player){
			if($player->getNetworkSession()->getIp() === $ip){
				$player->kick(KnownTranslationFactory::pocketmine_disconnect_ban($reason !== "" ? $reason : KnownTranslationFactory::pocketmine_disconnect_ban_ip()));
			}
		}

		$sender->getServer()->getNetwork()->blockAddress($ip, -1);
	}
}
