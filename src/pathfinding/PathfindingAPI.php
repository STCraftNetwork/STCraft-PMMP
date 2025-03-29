<?php

namespace pocketmine\pathfinding;

use pocketmine\Server;
use pocketmine\world\Position;



class PathfindingAPI
{

	public static function findPath(Position $start, Position $end): void
	{
		$min = $start->asVector3()->minComponents($end->asVector3());
		$max = $start->asVector3()->maxComponents($end->asVector3());

		$grid = [];

		$server = Server::getInstance();
		for ($x = $min->x; $x <= $max->x; $x++) {
			for ($y = $min->y; $y <= $max->y; $y++) {
				for ($z = $min->z; $z <= $max->z; $z++) {
					$block = $server->getWorldManager()->getDefaultWorld()->getBlockAt($x, $y, $z);
					$isObstacle = !$block->isTransparent();
					$grid[$x][$y][$z] = $isObstacle ? 1 : 0;
				}
			}
		}

		$pathfinder = new Pathfinder($grid);
		$path = $pathfinder->findPath($start->x, $start->y, $start->z, $end->x, $end->y, $end->z);
		print_r($path);
	}
}                                                       