<?php

declare(strict_types=1);

namespace pocketmine\scheduler;

use pocketmine\player\Player;
use pocketmine\utils\ObjectSet;
use pocketmine\utils\ReversePriorityQueue;

class TaskScheduler
{
    private bool $enabled = true;

    /** @phpstan-var ReversePriorityQueue<int, TaskHandler> */
    protected ReversePriorityQueue $queue;

    /**
     * @var ObjectSet|TaskHandler[]
     * @phpstan-var ObjectSet<TaskHandler>
     */
    protected ObjectSet $tasks;

    private int $currentTick = 0;

    /**
     * Map player UUID string to TaskHandler
     * @var array<string, TaskHandler>
     */
    private array $playerTasks = [];

    public function __construct(private readonly ?string $owner = null)
    {
        $this->queue = new ReversePriorityQueue();
        $this->tasks = new ObjectSet();
    }

    public function scheduleTask(Task $task): ?TaskHandler
    {
        return $this->addTask($task, -1, -1);
    }

    public function scheduleDelayedTask(Task $task, int $delay): ?TaskHandler
    {
        return $this->addTask($task, $delay, -1);
    }

    public function scheduleRepeatingTask(Task $task, int $period): ?TaskHandler
    {
        return $this->addTask($task, -1, $period);
    }

    public function scheduleDelayedRepeatingTask(Task $task, int $delay, int $period): ?TaskHandler
    {
        return $this->addTask($task, $delay, $period);
    }

    /**
     * @return TaskHandler[]
     */
    public function getTasks(): array
    {
        return $this->tasks->toArray();
    }

    public function cancelAllTasks(): void
    {
        foreach ($this->tasks as $task) {
            $task->cancel();
        }
        $this->tasks->clear();
        $this->queue = new ReversePriorityQueue();
        $this->playerTasks = [];
    }

    public function isQueued(TaskHandler $task): bool
    {
        return $this->tasks->contains($task);
    }

    public function isScheduledFor(Player $player): bool
    {
        return isset($this->playerTasks[$player->getUniqueId()->toString()]);
    }

    public function scheduleForPlayer(Player $player, Task $task, int $ticks = 20): ?TaskHandler
    {
        $uuid = $player->getUniqueId()->toString();

        if ($this->isScheduledFor($player)) {
            return $this->playerTasks[$uuid];
        }

        $taskHandler = $this->scheduleRepeatingTask($task, $ticks);
        if ($taskHandler !== null) {
            $this->playerTasks[$uuid] = $taskHandler;
        }

        return $taskHandler;
    }

    public function cancelForPlayer(Player $player): void
    {
        $uuid = $player->getUniqueId()->toString();

        if (isset($this->playerTasks[$uuid])) {
            $this->playerTasks[$uuid]->cancel();
            unset($this->playerTasks[$uuid]);
        }
    }

    private function addTask(Task $task, int $delay, int $period): ?TaskHandler
    {
        if (!$this->enabled) {
            return null; // fail gracefully if scheduler is disabled
        }

        $delay = max($delay, -1);
        $period = max($period, -1);

        return $this->registerTask(new TaskHandler($task, $delay, $period, $this->owner));
    }

    private function registerTask(TaskHandler $handler): TaskHandler
    {
        $nextRun = $this->currentTick + max($handler->getDelay(), 0);
        $handler->setNextRun($nextRun);
        $this->tasks->add($handler);
        $this->queue->insert($handler, $nextRun);

        return $handler;
    }

    public function shutdown(): void
    {
        $this->enabled = false;
        $this->cancelAllTasks();
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Call this method every tick.
     * Scheduler auto-increments internal current tick.
     */
    public function mainThreadHeartbeat(): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->currentTick++;

        while ($this->hasReadyTask()) {
            /** @var TaskHandler $task */
            $task = $this->queue->extract();

            if ($task->isCancelled()) {
                $this->tasks->remove($task);
                continue;
            }

            $task->run();

            if (!$task->isCancelled() && $task->isRepeating()) {
                $nextRun = $this->currentTick + $task->getPeriod();
                $task->setNextRun($nextRun);
                $this->queue->insert($task, $nextRun);
            } else {
                $task->remove();
                $this->tasks->remove($task);
            }
        }
    }

    private function hasReadyTask(): bool
    {
        return !$this->queue->isEmpty() && $this->queue->current()->getNextRun() <= $this->currentTick;
    }
}
