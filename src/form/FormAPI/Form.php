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

namespace pocketmine\form\FormAPI;

use pocketmine\player\Player;
use InvalidArgumentException;

/**
 * Form implementations must implement this interface to be able to utilize the Player form-sending mechanism.
 * There is no restriction on custom implementations other than that they must implement this.
 */
interface Form extends \JsonSerializable
{
    /**
     * @param callable(Player, mixed): void|null $callable
     */
    public function __construct(?callable $callable);

    /**
     * @param Player $player
     * @throws InvalidArgumentException
     * @deprecated
     * @see Player::sendForm()
     */
    public function sendToPlayer(Player $player) : void;

    /**
     * @return callable(Player, mixed): void|null
     */
    public function getCallable() : ?callable;

    /**
     * @param callable(Player, mixed): void|null $callable
     */
    public function setCallable(?callable $callable) : void;

    /**
     * @param Player $player
     * @param mixed $data
     */
    public function handleResponse(Player $player, $data) : void;

    /**
     * @param mixed $data
     */
    public function processData(&$data) : void;

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize() : array;
}