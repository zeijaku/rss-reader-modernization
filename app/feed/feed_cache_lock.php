<?php

declare(strict_types=1);

/** Process-safe exclusive lock guard for one Feed cache key. */
final class FeedCacheLock
{
    /** @var resource|null */
    private $handle;

    /** @param resource $handle */
    public function __construct($handle)
    {
        if (!is_resource($handle)) {
            throw new InvalidArgumentException('Feed cache lock requires a valid resource.');
        }
        $this->handle = $handle;
    }

    public function release(): void
    {
        if (!is_resource($this->handle)) {
            return;
        }
        @flock($this->handle, LOCK_UN);
        @fclose($this->handle);
        $this->handle = null;
    }

    public function __destruct()
    {
        $this->release();
    }
}
