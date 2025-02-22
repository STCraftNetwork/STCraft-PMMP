<?php

namespace pocketmine\pathfinding;

use pocketmine\math\Vector3;

class Node extends Vector3 {
    public $gCost = INF;
    public $hCost = INF;
    public $parent = null;

    public function fCost() {
        return $this->gCost + $this->hCost;
    }

    public function equals(Vector3 $node): bool {
        return $this->x === $node->x && $this->y === $node->y && $this->z === $node->z;
    }

    public function hashCode() {
        return $this->x . ',' . $this->y . ',' . $this->z;
    }
}