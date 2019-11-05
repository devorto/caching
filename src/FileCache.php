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
    protected $cacheDirectory;

    /**
     * @var string
     */
    protected $prefix = '';

    /**
     * FileCache constructor.
     *
     * @param string $prefix
     * @param string $cacheDirectory Full path to cache directory.
     */
    public function __construct(string $prefix, string $cacheDirectory)
    {
        if (empty(trim($prefix))) {
            throw new InvalidArgumentException('Prefix cannot be empty.');
        }

        $this->prefix = $this->normalize($prefix);

        $cacheDirectory = realpath($cacheDirectory);
        if (empty($cacheDirectory)) {
            throw new RuntimeException('Cache directory does not exists.');
        }

        if (!is_writeable($cacheDirectory)) {
            throw new RuntimeException('Cache directory is not writeable.');
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
     *
     * @return string
     */
    protected function normalize(string $key): string
    {
        return preg_replace('/[^0-9A-Z]+/i', '-', $key);
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
        $key = $this->cacheDirectory . DIRECTORY_SEPARATOR . $this->getPrefix() . $this->normalize($key);
        $ttlKey = $key . '-ttl';

        file_put_contents($key, $value);
        file_put_contents($ttlKey, $ttl);

        return $this;
    }

    /**
     * @param string $key
     *
     * @return string|null Returns null if the key doesn't exists.
     */
    public function get(string $key): ?string
    {
        $path = $this->cacheDirectory . DIRECTORY_SEPARATOR . $this->getPrefix() . $this->normalize($key);
        $ttlPath = $path . '-ttl';
        if (!file_exists($path)) {
            return null;
        }

        // If there is no -ttl file remove the cache file.
        if (!file_exists($ttlPath)) {
            unlink($path);

            return null;
        }

        // See if file TTL has expired (and is not infinite).
        $ttl = file_get_contents($ttlPath);
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
        $key = $this->cacheDirectory . DIRECTORY_SEPARATOR . $this->getPrefix() . $this->normalize($key);
        $ttlKey = $key . '-ttl';

        if (file_exists($key)) {
            unlink($key);
        }

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
            if (substr($file, 0, 1) === '.') {
                continue;
            }

            $prefix = substr($file, 0, strlen($this->getPrefix()));
            if ($prefix === $this->getPrefix()) {
                $this->delete(substr($file, strlen($prefix)));
            }
        }

        return $this;
    }
}
