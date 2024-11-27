<?php

namespace Devorto\Caching;

use InvalidArgumentException;
use Redis;
use RedisException;
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
    protected Redis $redis;

    /**
     * @var string
     */
    protected string $prefix = '';

    /**
     * RedisCache constructor.
     *
     * @param string $prefix
     * @param string $host
     * @param int $port
     * @param int $database
     */
    public function __construct(string $prefix, string $host = 'localhost', int $port = 6379, int $database = 0)
    {
        if (empty(trim($prefix))) {
            throw new InvalidArgumentException('Prefix cannot be an empty string.');
        }

        $this->prefix = $prefix;
        try {
            $this->redis = new Redis();

            if ($this->redis->connect($host, $port) === false) {
                throw new RuntimeException('Cannot connect to redis.');
            }

            if ($database > 0) {
                $this->redis->select($database);
            }
        } catch (RedisException $exception) {
            throw new RuntimeException('Cannot connect to redis.', 0, $exception);
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

        try {
            $this->redis->set($this->getPrefix() . $key, $value, $ttl === 0 ? null : $ttl);
        } catch (RedisException $exception) {
            throw new RuntimeException('Error while saving value in Redis.', 0, $exception);
        }

        return $this;
    }

    /**
     * @param string $key
     *
     * @return string|null Returns null if the key doesn't exist.
     */
    public function get(string $key): ?string
    {
        if (empty(trim($key))) {
            throw new InvalidArgumentException('Key cannot be an empty string.');
        }

        try {
            $data = $this->redis->get($this->getPrefix() . $key);
        } catch (RedisException $exception) {
            throw new RuntimeException('Error while retrieving value from Redis.', 0, $exception);
        }

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

        try {
            $this->redis->del($this->getPrefix() . $key);
        } catch (RedisException $exception) {
            throw new RuntimeException('Error while deleting key from Redis.', 0, $exception);
        }

        return $this;
    }

    /**
     * @return Cache
     */
    public function clear(): Cache
    {
        try {
            $keys = $this->redis->keys($this->getPrefix() . '*');
        } catch (RedisException $exception) {
            throw new RuntimeException('Error while retrieving all keys from Redis.', 0, $exception);
        }

        if (!empty($keys)) {
            try {
                $this->redis->del($keys);
            } catch (RedisException $exception) {
                throw new RuntimeException('Error while deleting key from Redis.', 0, $exception);
            }
        }

        return $this;
    }
}
