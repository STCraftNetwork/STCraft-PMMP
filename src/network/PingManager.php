<?php

namespace pocketmine\network;

use pocketmine\player\Player;

class PingManager {

    private static array $pingMap = [];
    private static int $historySize = 20;

    public static function updatePing(Player $player): void {
        $name = $player->getName();
        $ping = $player->getNetworkSession()->getPing();

        if (!isset(self::$pingMap[$name])) {
            self::$pingMap[$name] = [
                "latest" => $ping,
                "history" => [$ping],
                "avg" => $ping,
                "max" => $ping,
                "min" => $ping
            ];
            return;
        }

        self::$pingMap[$name]["latest"] = $ping;
        self::$pingMap[$name]["history"][] = $ping;

        if (count(self::$pingMap[$name]["history"]) > self::$historySize) {
            array_shift(self::$pingMap[$name]["history"]);
        }

        $avg = (int)(array_sum(self::$pingMap[$name]["history"]) / count(self::$pingMap[$name]["history"]));
        self::$pingMap[$name]["avg"] = $avg;
        self::$pingMap[$name]["max"] = max(self::$pingMap[$name]["history"]);
        self::$pingMap[$name]["min"] = min(self::$pingMap[$name]["history"]);
    }

    public static function getLatest(Player $player): int {
        return self::$pingMap[$player->getName()]["latest"] ?? 0;
    }

    public static function getAverage(Player $player): int {
        return self::$pingMap[$player->getName()]["avg"] ?? 0;
    }

    public static function getMax(Player $player): int {
        return self::$pingMap[$player->getName()]["max"] ?? 0;
    }

    public static function getMin(Player $player): int {
        return self::$pingMap[$player->getName()]["min"] ?? 0;
    }

    public static function getTopPings(int $limit = 5): array {
        $sorted = self::$pingMap;
        uasort($sorted, fn($a, $b) => $b["avg"] <=> $a["avg"]);

        return array_slice($sorted, 0, $limit, true);
    }

    public static function isLagging(Player $player, int $threshold = 400): bool {
        return self::getAverage($player) >= $threshold;
    }

    public static function remove(Player $player): void {
        unset(self::$pingMap[$player->getName()]);
    }

    public static function getAll(): array {
        return self::$pingMap;
    }
}
