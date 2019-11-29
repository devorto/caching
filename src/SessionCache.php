<?php

namespace Devorto\Caching;

use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use RuntimeException;

/**
 * Class SessionCache
 *
 * @package Devorto\Caching
 * Note: Be aware that this class depends on session.* php.ini settings.
 */
class SessionCache implements Cache
{
    /**
     * @var string
     */
    protected $prefix;

    /**
     * SessionCache constructor.
     *
     * @param string $prefix
     */
    public function __construct(string $prefix)
    {
        if (empty(trim($prefix))) {
            throw new InvalidArgumentException('Prefix cannot be an empty string.');
        }

        if (!session_start()) {
            throw new RuntimeException('Could not start session.');
        }

        if (!session_regenerate_id()) {
            throw new RuntimeException('Could not regenerate session ID.');
        }

        $this->prefix = $prefix;
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
     * @param int $ttl (time to live) in seconds, 0 = infinite. (Note: This might be affected by php.ini settings)
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

        $_SESSION[$this->prefix . $key] = $value;
        if ($ttl > 0) {
            try {
                $now = new DateTime('now', new DateTimeZone('UTC'));
                $now->add(new DateInterval('P' . $ttl . 'S'));
            } catch (Exception $exception) {
                throw new RuntimeException('Could not create new DateTime object.', 0, $exception);
            }

            $_SESSION[$this->prefix . $key . '-ttl'] = $now;
        } else {
            $_SESSION[$this->prefix . $key . '-ttl'] = $ttl;
        }

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

        if (!isset($_SESSION[$this->prefix . $key])) {
            return null;
        }

        $ttlKey = $this->prefix . $key . '-ttl';

        if (!isset($_SESSION[$ttlKey])) {
            $this->delete($key);

            return null;
        }

        // Infinite cache.
        if (!($_SESSION[$ttlKey] instanceof DateTime)) {
            return $_SESSION[$this->prefix . $key];
        }

        try {
            $now = new DateTime('now', new DateTimeZone('UTC'));
        } catch (Exception $exception) {
            throw new RuntimeException('Could not create new DateTime object.', 0, $exception);
        }

        // Key expired.
        if ($now > $_SESSION[$ttlKey]) {
            $this->delete($key);

            return null;
        }

        return $_SESSION[$ttlKey];
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

        unset($_SESSION[$this->prefix . $key]);
        unset($_SESSION[$this->prefix . $key . '-ttl']);

        return $this;
    }

    /**
     * @return Cache
     */
    public function clear(): Cache
    {
        $_SESSION = [];

        return $this;
    }

    /**
     * Stop session.
     */
    public function __destruct()
    {
        session_write_close();
    }
}
