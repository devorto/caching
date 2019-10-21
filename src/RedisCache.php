<?php

namespace Devorto\Caching;

use Redis;
use RuntimeException;

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
        $this->redis = new Redis();
        if ($this->redis->connect($host, $port) === false) {
            throw new RuntimeException('Cannot connect to redis.');
        }

        $this->setPrefix($prefix);
    }

    /**
     * @return string
     */
    public function getPrefix(): string
    {
        if (empty($this->prefix)) {
            throw new RuntimeException('Prefix is not set, please provide one.');
        }

        return $this->prefix;
    }

    /**
     * @param string $prefix
     *
     * @return Cache
     */
    public function setPrefix(string $prefix): Cache
    {
        $this->prefix = $prefix;

        return $this;
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
        $this->redis->del($this->getPrefix() . $key);

        return $this;
    }

    /**
     * @return Cache
     */
    public function clear(): Cache
    {
        $keys = $this->redis->keys('*');
        foreach ($keys as $key) {
            $prefix = substr($key, 0, strlen($this->getPrefix()));
            if ($prefix === $this->getPrefix()) {
                $this->delete(substr($key, strlen($prefix)));
            }
        }

        return $this;
    }
}
