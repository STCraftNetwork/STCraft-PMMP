<?php

namespace pocketmine\pathfinding;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\world\Position;
use SplPriorityQueue;

class Pathfinder extends AsyncTask {

    private $worldname;
    private $start;
    private $end;
    private $openList;
    private $closedList;

    public function __construct(string $worldname, Position $start, Position $end) {
        $this->worldname = $worldname;
        $this->start = $start;
        $this->end = $end;
        $this->openList = new SplPriorityQueue();
        $this->closedList = [];
    }

    public function onRun(): void {
        $startNode = new Node($this->start->getX(), $this->start->getY(), $this->start->getZ());
        $endNode = new Node($this->end->getX(), $this->end->getY(), $this->end->getZ());

        $this->openList->insert($startNode, -$startNode->fCost());

        while (!$this->openList->isEmpty()) {
            $currentNode = $this->openList->extract();
            if ($currentNode->equals($endNode)) {
                $this->setResult($this->retracePath($startNode, $currentNode));
                return;
            }

            $this->closedList[$currentNode->hashCode()] = $currentNode;

            foreach ($this->getNeighbors($currentNode) as $neighbor) {
                if (isset($this->closedList[$neighbor->hashCode()]) || !$this->isWalkable($neighbor)) {
                    continue;
                }

                $newMovementCostToNeighbor = $currentNode->gCost + $this->getDistance($currentNode, $neighbor);
                if ($newMovementCostToNeighbor < $neighbor->gCost || !isset($this->openList[$neighbor->hashCode()])) {
                    $neighbor->gCost = $newMovementCostToNeighbor;
                    $neighbor->hCost = $this->getDistance($neighbor, $endNode);
                    $neighbor->parent = $currentNode;

                    if (!isset($this->openList[$neighbor->hashCode()])) {
                        $this->openList->insert($neighbor, -$neighbor->fCost());
                    }
                }
            }
        }

        $this->setResult(null); // No path found
    }

    private function getNeighbors(Node $node): array {
        $neighbors = [];
        $directions = [
            new Vector3(1, 0, 0), new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1), new Vector3(0, 0, -1),
            new Vector3(1, 0, 1), new Vector3(-1, 0, -1),
            new Vector3(1, 0, -1), new Vector3(-1, 0, 1),
            new Vector3(0, 1, 0), new Vector3(0, -1, 0),
        ];

        foreach ($directions as $direction) {
            $neighborPos = $node->add($direction->x, $direction->y, $direction->z);
            $neighbors[] = new Node($neighborPos->getX(), $neighborPos->getY(), $neighborPos->getZ());
        }

        return $neighbors;
    }

    private function isWalkable(Node $node): bool {
        // Implement the logic to check if a node is walkable.
        // This method should be overridden to check block types, etc.
        return true;
    }

    private function getDistance(Node $nodeA, Node $nodeB): float {
        $dstX = abs($nodeA->x - $nodeB->x);
        $dstY = abs($nodeA->y - $nodeB->y);
        $dstZ = abs($nodeA->z - $nodeB->z);

        if ($dstX > $dstZ) {
            return 14 * $dstZ + 10 * ($dstX - $dstZ) + 10 * $dstY;
        }
        return 14 * $dstX + 10 * ($dstZ - $dstX) + 10 * $dstY;
    }

    private function retracePath(Node $startNode, Node $endNode): array {
        $path = [];
        $currentNode = $endNode;

        while (!$currentNode->equals($startNode)) {
            $path[] = $currentNode;
            $currentNode = $currentNode->parent;
        }

        return array_reverse($path);
    }

    public function onCompletion(): void {
        $result = $this->getResult();
		$server = Server::getInstance();
        if ($result !== null) {
            $level = $server->getWorldManager()->getWorldByName($this->worldname);
            foreach ($result as $node) {
                $level->setBlock(new Vector3($node->x, $node->y, $node->z), VanillaBlocks::GLOWSTONE());
            }
        } else {
            $server->getLogger()->info("No path found!");
        }
    }
}