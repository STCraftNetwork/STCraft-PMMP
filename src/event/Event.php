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

use pocketmine\timings\Timings;
use RuntimeException;

/**
 * Base class for all events.
 */
abstract class Event {
	private const MAX_EVENT_CALL_DEPTH = 50;

	private static int $eventCallDepth = 1;

	/** @var string|null Cached event name */
	protected ?string $eventName = null;

	/**
	 * Returns the name of the event class (cached).
	 */
	final public function getEventName() : string {
		return $this->eventName ??= static::class;
	}

	/**
	 * Calls all registered handlers for this event.
	 *
	 * @throws RuntimeException if recursive depth is too high.
	 */
	public function call() : void {
		if (self::$eventCallDepth >= self::MAX_EVENT_CALL_DEPTH) {
			throw new RuntimeException(
				"Recursive event call detected: reached max depth of " . self::MAX_EVENT_CALL_DEPTH
			);
		}

		$handlers = HandlerListManager::global()->getHandlersFor(static::class);
		if ($handlers === []) {
			return;
		}

		$timings = Timings::getEventTimings($this);
		$timings->startTiming();

		self::$eventCallDepth++;
		try {
			foreach ($handlers as $listener) {
				$listener->callEvent($this);
			}
		} finally {
			self::$eventCallDepth--;
			$timings->stopTiming();
		}
	}

	/**
	 * Fast check to avoid allocating new events if there are no handlers.
	 */
	public static function hasHandlers() : bool {
		return HandlerListManager::global()->hasHandlersFor(static::class);
	}
}
