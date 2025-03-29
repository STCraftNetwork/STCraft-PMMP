<?php

namespace pocketmine\pathfinding;

class Node {
    public $x;
    public $y;
    public $z;
    public $g;
    public $h;
    public $f;
    public $parent;

    public function __construct($x, $y, $z, $g = 0, $h = 0, $parent = null) {
        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
        $this->g = $g;
        $this->h = $h;
        $this->f = $g + $h;
        $this->parent = $parent;
    }
}