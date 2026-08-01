<?php

declare(strict_types=1);

require_once __DIR__ . '/feed_source.php';
require_once __DIR__ . '/feed_cache_entry.php';
require_once __DIR__ . '/feed_cache_lock.php';

/** Feed本文を保存するファイルCache。 */
final class FeedCache
{
    private const FILE_PREFIX = 'feed-v1-';
    private const FUTURE_CLOCK_TOLERANCE_SECONDS = 300;

    /** @var Closure():int */
    private Closure $clock;

    /** @param Closure():int|null $clock */
    public function __construct(
        private readonly string $directory,
        private readonly int $ttlSeconds,
        private readonly int $maxBodyBytes,
        ?Closure $clock = null
    ) {
        if ($directory === '' || str_contains($directory, "\0")) {
            throw new InvalidArgumentException('Feed cache directory must be non-empty.');
        }
        if ($ttlSeconds <= 0 || $maxBodyBytes <= 0) {
            throw new InvalidArgumentException('Feed cache TTL and body limit must be positive.');
        }
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function cacheKey(FeedSource $source): string
    {
        return hash('sha256', $source->url);
    }

    public function cachePath(FeedSource $source): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . self::FILE_PREFIX . $this->cacheKey($source) . '.json';
    }

    public function lockPath(FeedSource $source): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . self::FILE_PREFIX . $this->cacheKey($source) . '.lock';
    }

    public function readFresh(FeedSource $source): ?FeedCacheEntry
    {
        $entry = $this->read($source);
        return $entry !== null && $this->isFresh($entry) ? $entry : null;
    }

    public function readStale(FeedSource $source): ?FeedCacheEntry
    {
        $entry = $this->read($source);
        return $entry !== null && !$this->isFresh($entry) ? $entry : null;
    }

    public function read(FeedSource $source): ?FeedCacheEntry
    {
        $path = $this->cachePath($source);
        if (!is_file($path) || is_link($path)) {
            return null;
        }

        $maxFileBytes = (int) ceil($this->maxBodyBytes * 4 / 3) + 65536;
        $size = @filesize($path);
        if (!is_int($size) || $size <= 0 || $size > $maxFileBytes) {
            $this->delete($source);
            return null;
        }

        $json = @file_get_contents($path);
        if (!is_string($json) || $json === '' || strlen($json) > $maxFileBytes) {
            $this->delete($source);
            return null;
        }

        try {
            $payload = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $this->delete($source);
            return null;
        }
        if (!is_array($payload)) {
            $this->delete($source);
            return null;
        }

        $entry = FeedCacheEntry::fromPayload($payload, $source->url, $this->maxBodyBytes);
        if ($entry === null) {
            $this->delete($source);
            return null;
        }

        $now = ($this->clock)();
        if ($entry->validatedAt > $now + self::FUTURE_CLOCK_TOLERANCE_SECONDS) {
            $this->delete($source);
            return null;
        }
        return $entry;
    }

    /** @param array<string,mixed> $fetch */
    public function writeSuccessfulFetch(FeedSource $source, array $fetch): bool
    {
        $entry = FeedCacheEntry::fromSuccessfulFetch(
            $source,
            $fetch,
            ($this->clock)(),
            $this->maxBodyBytes
        );
        return $entry !== null && $this->writeEntry($source, $entry);
    }

    /** @param array<string,mixed> $fetch */
    public function writeNotModified(FeedSource $source, FeedCacheEntry $cached, array $fetch): bool
    {
        $entry = FeedCacheEntry::fromNotModified($cached, $fetch, ($this->clock)());
        return $entry !== null && $this->writeEntry($source, $entry);
    }

    public function delete(FeedSource $source): void
    {
        $path = $this->cachePath($source);
        if (is_file($path) && !is_link($path)) {
            @unlink($path);
        }
    }

    public function acquireLock(FeedSource $source, int $timeoutMs): ?FeedCacheLock
    {
        if ($timeoutMs < 0 || !$this->ensureDirectory()) {
            return null;
        }

        $path = $this->lockPath($source);
        if (is_link($path)) {
            return null;
        }

        $handle = @fopen($path, 'c+b');
        if (!is_resource($handle)) {
            return null;
        }
        @chmod($path, 0600);

        $deadline = hrtime(true) + ($timeoutMs * 1_000_000);
        do {
            if (@flock($handle, LOCK_EX | LOCK_NB)) {
                return new FeedCacheLock($handle);
            }
            if ($timeoutMs === 0 || hrtime(true) >= $deadline) {
                break;
            }
            usleep(20_000);
        } while (true);

        @fclose($handle);
        return null;
    }

    private function isFresh(FeedCacheEntry $entry): bool
    {
        $age = ($this->clock)() - $entry->validatedAt;
        return $age >= 0 && $age < $this->ttlSeconds;
    }

    private function writeEntry(FeedSource $source, FeedCacheEntry $entry): bool
    {
        if (!$this->ensureDirectory()) {
            return false;
        }

        $target = $this->cachePath($source);
        if (is_link($target)) {
            return false;
        }

        try {
            $json = json_encode(
                $entry->toPayload(),
                JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
            );
        } catch (Throwable) {
            return false;
        }

        $temp = @tempnam($this->directory, '.feed-cache-');
        if (!is_string($temp) || $temp === '') {
            return false;
        }

        $ok = false;
        try {
            @chmod($temp, 0600);
            $written = @file_put_contents($temp, $json, LOCK_EX);
            if (!is_int($written) || $written !== strlen($json)) {
                return false;
            }

            if (@rename($temp, $target)) {
                $ok = true;
                @chmod($target, 0600);
                return true;
            }

            // Windowsでは既存ファイルへのrenameが失敗するため、一度削除して置き換える。
            if (is_file($target) && !is_link($target) && @unlink($target) && @rename($temp, $target)) {
                $ok = true;
                @chmod($target, 0600);
                return true;
            }

            return false;
        } finally {
            if (!$ok && is_file($temp)) {
                @unlink($temp);
            }
        }
    }

    private function ensureDirectory(): bool
    {
        if (is_link($this->directory)) {
            return false;
        }
        if (!is_dir($this->directory)) {
            if (!@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
                return false;
            }
        }
        @chmod($this->directory, 0700);
        return is_dir($this->directory) && is_writable($this->directory);
    }
}
