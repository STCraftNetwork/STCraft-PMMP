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

namespace pocketmine\event;

use function mb_strtoupper;

/**
 * List of event priorities
 *
 * Events will be called in this order:
 * LOWEST -> LOW -> NORMAL -> HIGH -> HIGHEST -> MONITOR
 *
 * MONITOR events should not change the event outcome or contents
 *
 * WARNING: If these values are changed, handler sorting in HandlerList::getListenerList() may need to be updated.
 */
final class EventPriority {

    private function __construct() {}

    public const ALL = [
        self::MONITOR,
        self::HIGHEST,
        self::HIGH,
        self::NORMAL,
        self::LOW,
        self::LOWEST,
    ];

    /** Event listened to purely for monitoring, runs last, should NOT modify event */
    public const MONITOR = 5;

    /** Event with critical importance, runs just before MONITOR */
    public const HIGHEST = 4;

    /** Event of high importance */
    public const HIGH = 3;

    /** Default event priority */
    public const NORMAL = 2;

    /** Event of low importance */
    public const LOW = 1;

    /** Event of very low importance, runs first */
    public const LOWEST = 0;

    /**
     * @throws \InvalidArgumentException
     */
    public static function fromString(string $name) : int {
        $value = [
            "MONITOR" => self::MONITOR,
            "HIGHEST" => self::HIGHEST,
            "HIGH" => self::HIGH,
            "NORMAL" => self::NORMAL,
            "LOW" => self::LOW,
            "LOWEST" => self::LOWEST,
        ][mb_strtoupper($name)] ?? null;
        if ($value !== null) {
            return $value;
        }
        throw new \InvalidArgumentException("Unable to resolve priority \"$name\"");
    }
}
