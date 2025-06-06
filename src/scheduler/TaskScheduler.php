<?php

declare(strict_types=1);

namespace pocketmine\scheduler;

use pocketmine\utils\ObjectSet;
use pocketmine\utils\ReversePriorityQueue;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\utils\UUID;

class TaskScheduler {
    private bool $enabled = true;

    /** @phpstan-var ReversePriorityQueue<int, TaskHandler> */
    protected ReversePriorityQueue $queue;

    /**
     * @var ObjectSet|TaskHandler[]
     * @phpstan-var ObjectSet<TaskHandler>
     */
    protected ObjectSet $tasks;

    protected int $currentTick = 0;

    /** @var array<string, TaskHandler> */
    private array $playerTasks = [];

    public function __construct(private ?string $owner = null) {
        $this->queue = new ReversePriorityQueue();
        $this->tasks = new ObjectSet();
    }

    /**
     * Schedule a new task.
     */
    public function scheduleTask(Task $task): TaskHandler {
        return $this->addTask($task, -1, -1);
    }

    /**
     * Schedule a task with a delay.
     */
    public function scheduleDelayedTask(Task $task, int $delay): TaskHandler {
        return $this->addTask($task, $delay, -1);
    }

    /**
     * Schedule a repeating task.
     */
    public function scheduleRepeatingTask(Task $task, int $period): TaskHandler {
        return $this->addTask($task, -1, $period);
    }

    /**
     * Schedule a task with a delay and a repeating period.
     */
    public function scheduleDelayedRepeatingTask(Task $task, int $delay, int $period): TaskHandler {
        return $this->addTask($task, $delay, $period);
    }

    /**
     * Cancel all scheduled tasks.
     */
    public function cancelAllTasks(): void {
        foreach ($this->tasks as $task) {
            $task->cancel();
        }
        $this->tasks->clear();
    }

    /**
     * Check if a task is in the queue.
     */
    public function isQueued(TaskHandler $task): bool {
        return $this->tasks->contains($task);
    }

    /**
     * Check if a task is scheduled for a specific player.
     */
    public function isScheduledFor(Player $player): bool {
        return isset($this->playerTasks[$player->getUniqueId()->toString()]);
    }

    /**
     * Schedule a task for a player and store the reference.
     */
    public function scheduleForPlayer(Player $player, Task $task, int $ticks = 20): TaskHandler {
        $uniqueId = $player->getUniqueId()->toString();

        if ($this->isScheduledFor($player)) {
            return $this->playerTasks[$uniqueId];
        }

        $taskHandler = $this->scheduleRepeatingTask($task, $ticks);
        $this->playerTasks[$uniqueId] = $taskHandler;

        return $taskHandler;
    }

    /**
     * Cancel all tasks associated with a specific player.
     */
    public function cancelForPlayer(Player $player): void {
        $uniqueId = $player->getUniqueId()->toString();
        
        if (isset($this->playerTasks[$uniqueId])) {
            $this->playerTasks[$uniqueId]->cancel();
            unset($this->playerTasks[$uniqueId]);
        }
    }

    /**
     * Add a task to the scheduler.
     */
    private function addTask(Task $task, int $delay, int $period): TaskHandler {
        if (!$this->enabled) {
            throw new \LogicException("Tried to schedule task to disabled scheduler");
        }

        $delay = max($delay, -1);
        $period = max($period, -1);

        return $this->handle(new TaskHandler($task, $delay, $period, $this->owner));
    }

    /**
     * Handle the scheduling of a task.
     */
    private function handle(TaskHandler $handler): TaskHandler {
        $nextRun = $this->currentTick + max($handler->getDelay(), 0);
        $handler->setNextRun($nextRun);
        $this->tasks->add($handler);
        $this->queue->insert($handler, $nextRun);

        return $handler;
    }

    /**
     * Shutdown the scheduler and cancel all tasks.
     */
    public function shutdown(): void {
        $this->enabled = false;
        $this->cancelAllTasks();
    }

    /**
     * Enable or disable the scheduler.
     */
    public function setEnabled(bool $enabled): void {
        $this->enabled = $enabled;
    }

    /**
     * Process tasks in the main thread heartbeat.
     */
    public function mainThreadHeartbeat(int $currentTick): void {
        if (!$this->enabled) {
            throw new \LogicException("Cannot run heartbeat on a disabled scheduler");
        }
        
        $this->currentTick = $currentTick;

        while ($this->isReady($this->currentTick)) {
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

    /**
     * Check if there are tasks ready to be executed.
     */
    private function isReady(int $currentTick): bool {
        return !$this->queue->isEmpty() && $this->queue->current()->getNextRun() <= $currentTick;
    }
}