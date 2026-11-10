<?php

namespace Devorto\Caching;

interface Cache
{
    /**
     * @return string
     */
    public function getPrefix(): string;

    /**
     * @param string $key
     * @param string $value
     * @param int $ttl (time to live) in seconds, 0 = infinite.
     *
     * @return Cache
     */
    public function set(string $key, string $value, int $ttl = 0): Cache;

    /**
     * @param string $key
     *
     * @return string|null Returns null if the key doesn't exist.
     */
    public function get(string $key): ?string;

    /**
     * @param string $key Deletes the key it doesn't matter if it exists or not.
     *
     * @return Cache
     */
    public function delete(string $key): Cache;

    /**
     * Append a value to a temporary FIFO queue.
     *
     * @param string $key
     * @param string $value
     *
     * @return void
     */
    public function enqueue(string $key, string $value): void;

    /**
     * Atomically remove the oldest value. Cache deletion may discard queues.
     *
     * @param string $key
     *
     * @return string|null Returns null when the queue is empty or missing.
     */
    public function dequeue(string $key): ?string;

    /**
     * @return Cache
     */
    public function clear(): Cache;
}
