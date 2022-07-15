<?php

namespace Devorto\Caching;

use InvalidArgumentException;
use Redis;
use RuntimeException;

/**
 * Class RedisCache
 *
 * @package Devorto\Caching
 */
class RedisCache implements Cache
{
    /**
     * @var Redis
     */
    protected $redis;

    /**
     * @var string
     */
    protected $prefix = '';

    /**
     * RedisCache constructor.
     *
     * @param string $prefix
     * @param string $host
     * @param int $port
     */
    public function __construct(string $prefix, string $host = 'localhost', int $port = 6379)
    {
        if (empty(trim($prefix))) {
            throw new InvalidArgumentException('Prefix cannot be an empty string.');
        }

        $this->prefix = $prefix;
        $this->redis = new Redis();

        if ($this->redis->connect($host, $port) === false) {
            throw new RuntimeException('Cannot connect to redis.');
        }
    }

    /**
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
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
        if (empty(trim($key))) {
            throw new InvalidArgumentException('Key cannot be an empty string.');
        }

        if ($ttl < 0) {
            throw new InvalidArgumentException('TTL should be >= 0.');
        }

        $this->redis->set($this->getPrefix() . $key, $value, $ttl === 0 ? null : $ttl);

        return $this;
    }

    /**
     * @param string $key
     *
     * @return string|null Returns null if the key doesn't exists.
     */
    public function get(string $key): ?string
    {
        if (empty(trim($key))) {
            throw new InvalidArgumentException('Key cannot be an empty string.');
        }

        $data = $this->redis->get($this->getPrefix() . $key);

        if ($data === false) {
            return null;
        }

        return $data;
    }

    /**
     * @param string $key Deletes the key it doesn't matter if it exists or not.
     *
     * @return Cache
     */
    public function delete(string $key): Cache
    {
        if (empty(trim($key))) {
            throw new InvalidArgumentException('Key cannot be an empty string.');
        }

        $this->redis->del($this->getPrefix() . $key);

        return $this;
    }

    /**
     * @return Cache
     */
    public function clear(): Cache
    {
        $keys = $this->redis->keys($this->getPrefix() . '*');
        if (!empty($keys)) {
            $this->redis->del($keys);
        }

        return $this;
    }
}
