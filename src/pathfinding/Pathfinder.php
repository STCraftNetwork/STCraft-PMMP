<?php

namespace pocketmine\pathfinding;

class Pathfinder {
    private $openList = [];
    private $closedList = [];
    private $grid = [];
    private $startNode;
    private $endNode;

    public function __construct($grid) {
        $this->grid = $grid;
    }

    public function findPath($startX, $startY, $startZ, $endX, $endY, $endZ): array {
        $this->startNode = new Node($startX, $startY, $startZ);
        $this->endNode = new Node($endX, $endY, $endZ);
        array_push($this->openList, $this->startNode);

        while (count($this->openList) > 0) {
            $currentNode = $this->getLowestFNode();
            if ($this->isEndNode($currentNode)) {
                return $this->retracePath($currentNode);
            }

            $this->removeFromOpenList($currentNode);
            array_push($this->closedList, $currentNode);

            $neighbors = $this->getNeighbors($currentNode);
            foreach ($neighbors as $neighbor) {
                if ($this->isInClosedList($neighbor) || !$this->isWalkable($neighbor)) {
                    continue;
                }

                $newG = $currentNode->g + $this->getDistance($currentNode, $neighbor);
                if ($newG < $neighbor->g || !$this->isInOpenList($neighbor)) {
                    $neighbor->g = $newG;
                    $neighbor->h = $this->getDistance($neighbor, $this->endNode);
                    $neighbor->f = $neighbor->g + $neighbor->h;
                    $neighbor->parent = $currentNode;

                    if (!$this->isInOpenList($neighbor)) {
                        array_push($this->openList, $neighbor);
                    }
                }
            }
        }

        return [];
    }

    private function getLowestFNode(): mixed {
		if (empty($this->openList)) {
			return null; 
		}
	
		$lowestFNode = $this->openList[0];
	
		foreach ($this->openList as $node) {
			if (is_object($node) && $node->f < $lowestFNode->f) {
				$lowestFNode = $node;
			}
		}
	
		return $lowestFNode;
	}

    private function isEndNode($node): bool {
        return $node->x === $this->endNode->x && $node->y === $this->endNode->y && $node->z === $this->endNode->z;
    }

    private function removeFromOpenList($node): void {
        $index = array_search($node, $this->openList);
        if ($index !== false) {
            array_splice($this->openList, $index, 1);
        }
    }

    private function isInClosedList($node): bool {
        return in_array($node, $this->closedList);
    }

    private function isInOpenList($node): bool {
        return in_array($node, $this->openList);
    }

    private function isWalkable($node): bool {
        return isset($this->grid[$node->x][$node->y][$node->z]) && $this->grid[$node->x][$node->y][$node->z] === 0;
    }

    private function getNeighbors($node): array {
        $neighbors = [];
        $directions = [
            [1, 0, 0], [-1, 0, 0],
            [0, 1, 0], [0, -1, 0],
            [0, 0, 1], [0, 0, -1],
        ];

        foreach ($directions as $dir) {
            $x = $node->x + $dir[0];
            $y = $node->y + $dir[1];
            $z = $node->z + $dir[2];
            if (isset($this->grid[$x][$y][$z])) {
                $neighbors[] = new Node($x, $y, $z);
            }
        }

        return $neighbors;
    }

    private function getDistance($nodeA, $nodeB): float {
        $dx = abs($nodeA->x - $nodeB->x);
        $dy = abs($nodeA->y - $nodeB->y);
        $dz = abs($nodeA->z - $nodeB->z);
        return $dx + $dy + $dz;
    }

    private function retracePath($endNode): array {
        $path = [];
        $currentNode = $endNode;
        while ($currentNode !== null) {
            array_unshift($path, [$currentNode->x, $currentNode->y, $currentNode->z]);
            $currentNode = $currentNode->parent;
        }
        return $path;
    }
}