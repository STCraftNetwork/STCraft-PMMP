<?php

namespace pocketmine\pathfinding;

use pocketmine\Server;
use pocketmine\world\Position;


class PathfindingAPI {

    public static function findPath(Position $start, Position $end): void {
        $world = $start->getWorld();
        $pathfinder = new Pathfinder($world->getFolderName(), $start, $end);
        Server::getInstance()->getAsyncPool()->submitTask($pathfinder);
    }
}