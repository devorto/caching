<?php

namespace Devorto\Caching;

interface Cache
{
    /**
     * @param string $prefix
     *
     * @return Cache
     */
    public function setPrefix(string $prefix): Cache;

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
     * @return string|null Returns null if the key doesn't exists.
     */
    public function get(string $key): ?string;

    /**
     * @param string $key Deletes the key it doesn't matter if it exists or not.
     *
     * @return Cache
     */
    public function delete(string $key): Cache;

    /**
     * @return Cache
     */
    public function clear(): Cache;
}
