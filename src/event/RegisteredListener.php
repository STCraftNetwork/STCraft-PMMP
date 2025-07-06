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

use pocketmine\plugin\Plugin;
use pocketmine\timings\TimingsHandler;
use Closure;
use InvalidArgumentException;

class RegisteredListener
{
	public readonly Plugin $plugin;
	public readonly int $priority;
	public readonly bool $handleCancelled;
	public readonly TimingsHandler $timings;

	private ?Closure $handlerClosure = null;
	private ?string $handlerClass = null;
	private ?string $handlerMethod = null;

	/**
	 * @param Closure|string[] $handler Either a closure or a static callable [ClassName::class, 'method']
	 */
	public function __construct(
		Closure|array $handler,
		int $priority,
		Plugin $plugin,
		bool $handleCancelled,
		TimingsHandler $timings
	) {
		if ($priority > EventPriority::MONITOR || $priority < EventPriority::LOWEST) {
			throw new InvalidArgumentException("Invalid priority number $priority");
		}


		$this->plugin = $plugin;
		$this->priority = $priority;
		$this->handleCancelled = $handleCancelled;
		$this->timings = $timings;

		if ($handler instanceof Closure) {
			$this->handlerClosure = $handler;
		} elseif (is_array($handler) && count($handler) === 2) {
			[$this->handlerClass, $this->handlerMethod] = $handler;
		} else {
			throw new InvalidArgumentException("Handler must be a Closure or [ClassName::class, 'method']");
		}
	}

	public function getHandler(): Closure|array
	{
		return $this->handlerClosure ?? [$this->handlerClass, $this->handlerMethod];
	}

	public function getPlugin(): Plugin
	{
		return $this->plugin;
	}

	public function getPriority(): int
	{
		return $this->priority;
	}

	public function isHandlingCancelled(): bool
	{
		return $this->handleCancelled;
	}

	public function callEvent(Event $event): void
	{
		if ($event instanceof Cancellable && !$this->handleCancelled && $event->isCancelled()) {
			return;
		}

		$this->timings->startTiming();
		try {
			if ($this->handlerClosure !== null) {
				($this->handlerClosure)($event);
			} else {
				$this->handlerClass::{$this->handlerMethod}($event);
			}
		} finally {
			$this->timings->stopTiming();
		}
	}
}
