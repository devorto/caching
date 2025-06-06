<?php

namespace Devorto\Caching;

use InvalidArgumentException;
use RuntimeException;

/**
 * Class FileCache
 *
 * @package Devorto\Caching
 */
class FileCache implements Cache
{
    /**
     * @var string
     */
    protected string $cacheDirectory;

    /**
     * @var string
     */
    protected string $prefix = '';

    /**
     * FileCache constructor.
     *
     * @param string $prefix
     * @param string $cacheDirectory Full path to cache directory.
     */
    public function __construct(string $prefix, string $cacheDirectory)
    {
        if (empty(trim($prefix))) {
            throw new InvalidArgumentException('Prefix cannot be an empty string.');
        }

        $cacheDirectory = realpath($cacheDirectory);
        if (empty($cacheDirectory)) {
            throw new RuntimeException('Cache directory does not exists.');
        }

        if (!is_writeable($cacheDirectory)) {
            throw new RuntimeException('Cache directory is not writeable.');
        }

        $this->prefix = sha1($prefix);
        $this->cacheDirectory = $cacheDirectory;
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
     *
     * @return string
     */
    protected function normalize(string $key): string
    {
        return sha1($key);
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

        $key = $this->cacheDirectory . DIRECTORY_SEPARATOR . $this->getPrefix() . $this->normalize($key);
        $ttlKey = $key . '-ttl';

        file_put_contents($key, $value, LOCK_EX);
        file_put_contents($ttlKey, $ttl, LOCK_EX);

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

        $path = $this->cacheDirectory . DIRECTORY_SEPARATOR . $this->getPrefix() . $this->normalize($key);
        $ttlPath = $path . '-ttl';

        clearstatcache();
        if (!file_exists($path)) {
            return null;
        }

        // If there is no -ttl file remove the cache file.
        clearstatcache();
        if (!file_exists($ttlPath)) {
            unlink($path);

            return null;
        }

        // See if file TTL has expired (and is not infinite).
        $ttl = (int)file_get_contents($ttlPath);
        if ($ttl !== 0) {
            $stamp = filemtime($path);

            if (($stamp + $ttl) < time()) {
                $this->delete($key);

                return null;
            }
        }

        return file_get_contents($path);
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

        $key = $this->cacheDirectory . DIRECTORY_SEPARATOR . $this->getPrefix() . $this->normalize($key);
        $ttlKey = $key . '-ttl';

        clearstatcache();
        if (file_exists($key)) {
            unlink($key);
        }

        clearstatcache();
        if (file_exists($ttlKey)) {
            unlink($ttlKey);
        }

        return $this;
    }

    /**
     * @return Cache
     */
    public function clear(): Cache
    {
        foreach (scandir($this->cacheDirectory) as $file) {
            if (str_starts_with($file, '.')) {
                continue;
            }

            $prefix = substr($file, 0, strlen($this->getPrefix()));
            if ($prefix === $this->getPrefix()) {
                clearstatcache();
                if (file_exists($this->cacheDirectory . DIRECTORY_SEPARATOR . $file)) {
                    unlink($this->cacheDirectory . DIRECTORY_SEPARATOR . $file);
                }
            }
        }

        return $this;
    }
}
