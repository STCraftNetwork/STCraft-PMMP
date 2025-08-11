<?php

namespace pocketmine\entity\ai;

use pocketmine\color\Color;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\world\particle\DustParticle;
use SplPriorityQueue;

class Pathfinder
{
    /**
     * Basic A* pathfinding algorithm for 3D grid with optional debug visualization.
     *
     * @param World $world
     * @param Vector3 $start
     * @param Vector3 $end
     * @param callable(Vector3): bool $isWalkable
     * @param int $maxIterations
     * @param bool $debug
     * @return Vector3[]|null
     */
    public static function findPath(
        World $world,
        Vector3 $start,
        Vector3 $end,
        callable $isWalkable,
        int $maxIterations = 1000,
        bool $debug = true
    ): ?array {
        $startKey = self::vecKeyInt($start);
        $endKey = self::vecKeyInt($end);

        if ($startKey === $endKey) {
            return [$start];
        }

        $openSet = new SplPriorityQueue();
        $openSet->setExtractFlags(SplPriorityQueue::EXTR_DATA);
        $openSet->insert($start, 0);

        $cameFrom = [];
        $gScore = [$startKey => 0];
        $fScore = [$startKey => self::heuristicSquared($start, $end)];

        if ($debug) {
            self::spawnDebugParticle($world, $start, [0, 255, 0]); // Green: start
            self::spawnDebugParticle($world, $end, [255, 0, 0]);   // Red: end
        }

        $iterations = 0;

        while (!$openSet->isEmpty() && $iterations++ < $maxIterations) {
            $current = $openSet->extract();
            $currentKey = self::vecKeyInt($current);

            if ($debug) {
                self::spawnDebugParticle($world, $current, [255, 255, 0]); // Yellow: open set node
            }

            if (self::distanceSquaredInt($current, $end) <= 4) {
                $path = self::reconstructPath($cameFrom, $current);

                if ($debug) {
                    foreach ($path as $node) {
                        self::spawnDebugParticle($world, $node, [150, 0, 255]); // Purple: final path
                    }
                }

                return $path;
            }

            foreach (self::getNeighborsInt($current) as $neighbor) {
                if (!$isWalkable($neighbor)) continue;

                if ($debug) {
                    self::spawnDebugParticle($world, $neighbor, [0, 0, 255]); // Blue: neighbors
                }

                $neighborKey = self::vecKeyInt($neighbor);
                $tentativeG = $gScore[$currentKey] + 1;

                if (!isset($gScore[$neighborKey]) || $tentativeG < $gScore[$neighborKey]) {
                    $cameFrom[$neighborKey] = $current;
                    $gScore[$neighborKey] = $tentativeG;
                    $fScore[$neighborKey] = $tentativeG + self::heuristicSquared($neighbor, $end);
                    $openSet->insert($neighbor, -$fScore[$neighborKey]);
                }
            }
        }

        return null;
    }

    private static function heuristicSquared(Vector3 $a, Vector3 $b): float
    {
        $dx = $a->x - $b->x;
        $dy = $a->y - $b->y;
        $dz = $a->z - $b->z;
        return $dx * $dx + $dy * $dy + $dz * $dz;
    }

    private static function getNeighborsInt(Vector3 $pos): array
    {
        $x = (int)round($pos->x);
        $y = (int)round($pos->y);
        $z = (int)round($pos->z);

        static $directions = [
            [1, 0, 0], [-1, 0, 0],
            [0, 1, 0], [0, -1, 0],
            [0, 0, 1], [0, 0, -1],
        ];

        $neighbors = [];
        foreach ($directions as [$dx, $dy, $dz]) {
            $neighbors[] = new Vector3($x + $dx, $y + $dy, $z + $dz);
        }
        return $neighbors;
    }

    private static function reconstructPath(array $cameFrom, Vector3 $current): array
    {
        $path = [$current];
        while (isset($cameFrom[self::vecKeyInt($current)])) {
            $current = $cameFrom[self::vecKeyInt($current)];
            array_unshift($path, $current);
        }
        return $path;
    }

    private static function vecKeyInt(Vector3 $vec): string
    {
        return ((int)round($vec->x)) . ',' . ((int)round($vec->y)) . ',' . ((int)round($vec->z));
    }

    private static function distanceSquaredInt(Vector3 $a, Vector3 $b): float
    {
        $dx = ((int)round($a->x)) - ((int)round($b->x));
        $dy = ((int)round($a->y)) - ((int)round($b->y));
        $dz = ((int)round($a->z)) - ((int)round($b->z));
        return $dx * $dx + $dy * $dy + $dz * $dz;
    }

    private static function spawnDebugParticle(World $world, Vector3 $pos, array $rgb): void
    {
        $particle = new DustParticle(new Color($rgb[0], $rgb[1], $rgb[2]));
        $world->addParticle($pos->add(0.5, 0.5, 0.5), $particle);
    }
}
