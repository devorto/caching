<?php

namespace Devorto\Caching;

use RuntimeException;

class FileCache implements Cache
{
    /**
     * Filename of TTL file.
     */
    const CACHE_INDEX_FILENAME = '__cache_index__.json';

    /**
     * @var string
     */
    protected $cacheDirectory;

    /**
     * @var string
     */
    protected $prefix = '';

    /**
     * Contains keys + ttl to clean up expired files (is persistent).
     *
     * @var array
     */
    protected $cacheContents = [];

    /**
     * FileCache constructor.
     *
     * @param string $cacheDirectory Full path to cache directory.
     */
    public function __construct(string $cacheDirectory)
    {
        $cacheDirectory = realpath($cacheDirectory);
        if (empty($cacheDirectory)) {
            throw new RuntimeException('Cache directory does not exists.');
        }

        if (!is_writeable($cacheDirectory)) {
            throw new RuntimeException('Cache directory is not writeable.');
        }

        $this->cacheDirectory = $cacheDirectory;

        $indexFile = $this->cacheDirectory . DIRECTORY_SEPARATOR . static::CACHE_INDEX_FILENAME;
        if (file_exists($indexFile)) {
            $indexFile = file_get_contents($indexFile);
            $this->cacheContents = json_decode($indexFile, true);
        }
    }

    protected function saveIndexFile(): void
    {
        file_put_contents(
            $this->cacheDirectory . DIRECTORY_SEPARATOR . static::CACHE_INDEX_FILENAME,
            json_encode($this->cacheContents)
        );
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
        $this->prefix = $this->normalize($prefix);

        return $this;
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function normalize(string $key): string
    {
        return preg_replace('/[^0-9a-zA-Z]+/', '-', $key);
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
        $key = $this->getPrefix() . $this->normalize($key);
        $this->cacheContents[$key] = $ttl;

        file_put_contents(
            $this->cacheDirectory . DIRECTORY_SEPARATOR . $key,
            $value
        );

        $this->saveIndexFile();

        return $this;
    }

    /**
     * @param string $key
     *
     * @return string|null Returns null if the key doesn't exists.
     */
    public function get(string $key): ?string
    {
        $key = $this->getPrefix() . $this->normalize($key);

        if (!isset($this->cacheContents[$key])) {
            return null;
        }

        $path = $this->cacheDirectory . DIRECTORY_SEPARATOR . $key;

        // This should never happen but in case it does, solve it gracefully.
        if (!file_exists($path)) {
            $this->delete($key);

            return null;
        }

        // See if file TTL has expired (and is not infinite).
        $ttl = $this->cacheContents[$key];
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
        $key = $this->getPrefix() . $this->normalize($key);
        $path = $this->cacheDirectory . DIRECTORY_SEPARATOR . $key;

        unset($this->cacheContents[$key]);

        if (file_exists($path)) {
            unlink($path);
        }

        $this->saveIndexFile();

        return $this;
    }

    /**
     * @return Cache
     */
    public function clear(): Cache
    {
        foreach ($this->cacheContents as $key => $value) {
            $prefix = substr($key, 0, strlen($this->prefix));
            if ($prefix === $this->prefix) {
                $this->delete(substr($key, strlen($prefix)));
            }
        }

        return $this;
    }
}
