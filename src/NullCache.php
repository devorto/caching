<?php

namespace Devorto\Caching;

/**
 * Class NullCache
 *
 * @package Devorto\Caching
 */
class NullCache implements Cache
{
    /**
     * @return string
     */
    public function getPrefix(): string
    {
        return 'no-prefix';
    }

    /**
     * @param string $key
     * @param string $value
     * @param int $ttl (time to live) in seconds, 0 = infinite.
     *
     * @return Cache
     */
    public function set(string $key, string $value, int $ttl = 0): Cache
    {
        return $this;
    }

    /**
     * @param string $key
     *
     * @return string|null Returns null if the key doesn't exist.
     */
    public function get(string $key): ?string
    {
        return null;
    }

    /**
     * @param string $key Deletes the key it doesn't matter if it exists or not.
     *
     * @return Cache
     */
    public function delete(string $key): Cache
    {
        return $this;
    }

    /**
     * @return Cache
     */
    public function clear(): Cache
    {
        return $this;
    }
}
