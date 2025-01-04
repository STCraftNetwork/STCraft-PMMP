<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissionNames;


class ClearChatCommand extends VanillaCommand
{

    public function __construct()
    {
        parent::__construct(
            "clearchat",
            "Clears the chat for all players",
            "/clearchat"
        );
        $this->setPermission(DefaultPermissionNames::COMMAND_CLEAR_CHAT);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if (!$this->testPermission($sender)) {
            return false;
        }

        foreach ($sender->getServer()->getOnlinePlayers() as $player) {
            for ($i = 0; $i < 100; $i++) {
                $player->sendMessage("");
            }
        }

        Command::broadcastCommandMessage($sender, "§aThe chat has been successfully cleared for all players.");

        return true;
    }
}