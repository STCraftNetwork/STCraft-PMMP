<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\network\PingManager;
use pocketmine\utils\TextFormat;

class PingStatsCommand extends VanillaCommand{

    public function __construct(){
        parent::__construct(
            "pingstats",
            "Allows you to view ping statistics of players",
            "Usage: /pingstats [player]",
        );
        $this->setPermission(DefaultPermissionNames::COMMAND_VERSION);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        $target = null;

        if(isset($args[0])){
            $target = $sender->getServer()->getPlayerExact($args[0]);
            if($target === null){
                $sender->sendMessage(TextFormat::RED . KnownTranslationFactory::commands_generic_player_notFound()->getText());
                return true;
            }
        } elseif($sender instanceof Player){
            $target = $sender;
        } else {
            throw new InvalidCommandSyntaxException("Console must provide target player name");
        }

        $ping = PingManager::getLatest($target);
        $avg = PingManager::getAverage($target);
        $max = PingManager::getMax($target);
        $min = PingManager::getMin($target);

        $sender->sendMessage(TextFormat::AQUA . "§lPing Stats for " . $target->getName());
        $sender->sendMessage(TextFormat::GRAY . "» §fLatest Ping: " . TextFormat::GREEN . "{$ping}ms");
        $sender->sendMessage(TextFormat::GRAY . "» §fAverage Ping: " . TextFormat::YELLOW . "{$avg}ms");
        $sender->sendMessage(TextFormat::GRAY . "» §fMaximum Ping: " . TextFormat::RED . "{$max}ms");
        $sender->sendMessage(TextFormat::GRAY . "» §fMinimum Ping: " . TextFormat::DARK_GREEN . "{$min}ms");

        return true;
    }
}
