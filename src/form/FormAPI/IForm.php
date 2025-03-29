<?php

declare(strict_types = 1);

namespace pocketmine\form\FormAPI;

use InvalidArgumentException;
use pocketmine\player\Player;

abstract class IForm implements Form {

    /** @var array<string, mixed> */
    protected array $data = [];
    /** @var callable(Player, mixed): void|null */
    private mixed $callable;

    /**
     * @param callable(Player, mixed): void|null $callable
     */
    public function __construct(?callable $callable) {
        $this->callable = $callable;
    }

    /**
     * @param Player $player
     * @throws InvalidArgumentException
     * @deprecated
     * @see Player::sendForm()
     */
    public function sendToPlayer(Player $player) : void {
        $player->sendForm($this);
    }

    /**
     * @return callable(Player, mixed): void|null
     */
    public function getCallable() : ?callable {
        return $this->callable;
    }

    /**
     * @param callable(Player, mixed): void|null $callable
     */
    public function setCallable(?callable $callable): void {
        $this->callable = $callable;
    }

    /**
     * @param Player $player
     * @param mixed $data
     */
    public function handleResponse(Player $player, $data) : void {
        $this->processData($data);
        $callable = $this->getCallable();
        if ($callable !== null) {
            $callable($player, $data);
        }
    }

    /**
     * @param mixed $data
     */
    public function processData(&$data) : void {
        // Process the data as needed.
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize() : array {
        return $this->data;
    }
}