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

        $path = $this->cacheDirectory . DIRECTORY_SEPARATOR . $this->getPrefix() . $this->normalize($key);

        // Bereken de absolute timestamp (0 = oneindig geldig)
        $expiresAt = ($ttl === 0) ? 0 : (time() + $ttl);

        // Voeg de timestamp en de data samen in één payload, gescheiden door een newline
        $payload = $expiresAt . PHP_EOL . $value;

        file_put_contents($path, $payload, LOCK_EX);

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

        // Windows-proof stat cache reset specifiek voor dit bestandspad
        clearstatcache(true, $path);
        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if (empty($content)) {
            return null;
        }

        // Splits alleen de eerste regel af (de verloop-timestamp) van de rest van de data
        $parts = explode(PHP_EOL, $content, 2);
        $expiresAt = (int)$parts[0];
        $value = $parts[1] ?? '';

        // Controleer of de harde timestamp is verlopen (en niet oneindig '0' is)
        if ($expiresAt !== 0 && $expiresAt < time()) {
            $this->delete($key);

            return null;
        }

        return $value;
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

        $path = $this->cacheDirectory . DIRECTORY_SEPARATOR . $this->getPrefix() . $this->normalize($key);

        clearstatcache(true, $path);
        if (file_exists($path)) {
            @unlink($path);
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
                $path = $this->cacheDirectory . DIRECTORY_SEPARATOR . $file;

                clearstatcache(true, $path);
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
        }

        return $this;
    }
}
