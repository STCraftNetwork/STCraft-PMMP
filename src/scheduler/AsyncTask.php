<?php

declare(strict_types=1);

namespace pocketmine\scheduler;

use pmmp\thread\Runnable;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;
use pocketmine\thread\NonThreadSafeValue;
use pocketmine\timings\Timings;
use function array_key_exists;
use function igbinary_serialize;
use function igbinary_unserialize;
use function is_null;
use function is_scalar;
use function spl_object_id;

/**
 * Class used to run async tasks in other threads.
 *
 * @deprecated Progress update methods are deprecated.
 */
abstract class AsyncTask extends Runnable
{
    /**
     * Thread-local storage for data accessible only on the thread it was stored.
     * @phpstan-var array<int, array<string, mixed>>
     */
    private static array $threadLocalStorage = [];

    /**
     * @deprecated
     * @phpstan-var ThreadSafeArray<int, string>|null
     */
    private ?ThreadSafeArray $progressUpdates = null;

    private ThreadSafe|string|int|bool|null|float $result = null;

    private bool $submitted = false;
    private bool $finished = false;

    final public function run(): void
    {
        $this->result = null;

        $timings = Timings::getAsyncTaskRunTimings($this);
        $timings->startTiming();

        try {
            $this->onRun();
        } finally {
            $timings->stopTiming();
        }

        $this->finished = true;
        AsyncWorker::getNotifier()->wakeupSleeper();
        AsyncWorker::maybeCollectCycles();
    }

    /**
     * @deprecated Use isTerminated() instead.
     */
    public function isCrashed(): bool
    {
        return $this->isTerminated();
    }

    /**
     * Returns whether this task has finished executing (successfully or not).
     */
    public function isFinished(): bool
    {
        return $this->finished || $this->isTerminated();
    }

    public function hasResult(): bool
    {
        return $this->result !== null;
    }

    /**
     * @return mixed
     */
    public function getResult(): mixed
    {
        if ($this->result instanceof NonThreadSafeValue) {
            return $this->result->deserialize();
        }

        return $this->result;
    }

    public function setResult(mixed $result): void
    {
        $this->result = is_scalar($result) || is_null($result) || $result instanceof ThreadSafe
            ? $result
            : new NonThreadSafeValue($result);
    }

    /**
     * @deprecated No-op.
     */
    public function cancelRun(): void
    {
        // no operation
    }

    /**
     * @deprecated Always returns false.
     */
    public function hasCancelledRun(): bool
    {
        return false;
    }

    public function setSubmitted(): void
    {
        $this->submitted = true;
    }

    public function isSubmitted(): bool
    {
        return $this->submitted;
    }

    /**
     * Actions to execute when run (in async worker thread).
     */
    abstract public function onRun(): void;

    /**
     * Actions to execute when completed (on main thread).
     */
    public function onCompletion(): void
    {
    }

    /**
     * @deprecated
     *
     * Call this method from {@link onRun()} to schedule a call to
     * {@link onProgressUpdate()} from the main thread with the given progress.
     *
     * @param mixed $progress Serializable value.
     */
    public function publishProgress(mixed $progress): void
    {
        $progressUpdates = $this->progressUpdates ??= new ThreadSafeArray();

        $serialized = igbinary_serialize($progress);
        if ($serialized === false) {
            throw new \InvalidArgumentException("Progress must be serializable");
        }
        $progressUpdates[] = $serialized;
    }

    /**
     * @deprecated
     * Internal: only call from AsyncPool on main thread.
     */
    public function checkProgressUpdates(): void
    {
        if ($this->progressUpdates !== null) {
            while (($progress = $this->progressUpdates->shift()) !== null) {
                $this->onProgressUpdate(igbinary_unserialize($progress));
            }
        }
    }

    /**
     * @deprecated
     *
     * Called from the main thread after {@link publishProgress()} is called.
     *
     * @param mixed $progress Serialized and unserialized progress value.
     */
    public function onProgressUpdate(mixed $progress): void
    {
    }

    /**
     * @deprecated No longer used.
     */
    public function onError(): void
    {
    }

    /**
     * Saves mixed data in thread-local storage, accessible only on the same thread.
     */
    protected function storeLocal(string $key, mixed $complexData): void
    {
        self::$threadLocalStorage[spl_object_id($this)][$key] = $complexData;
    }

    /**
     * Retrieves data stored in thread-local storage.
     *
     * @throws \InvalidArgumentException if no matching data found.
     * @return mixed
     */
    protected function fetchLocal(string $key): mixed
    {
        $id = spl_object_id($this);
        if (!isset(self::$threadLocalStorage[$id]) || !array_key_exists($key, self::$threadLocalStorage[$id])) {
            throw new \InvalidArgumentException("No matching thread-local data found on this thread");
        }

        return self::$threadLocalStorage[$id][$key];
    }

    final public function __destruct()
    {
        $this->reallyDestruct();
        unset(self::$threadLocalStorage[spl_object_id($this)]);
    }

    /**
     * Override to do cleanup in child classes.
     */
    protected function reallyDestruct(): void
    {
    }
}
