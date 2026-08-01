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

    public function statePath(FeedSource $source): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . self::FILE_PREFIX . $this->cacheKey($source) . '.state.json';
    }

    public function now(): int
    {
        return ($this->clock)();
    }

    public function ageSeconds(FeedCacheEntry $entry): ?int
    {
        $age = $this->now() - $entry->validatedAt;
        return $age >= 0 ? $age : null;
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


    /** @return array<string,mixed>|null */
    public function readState(FeedSource $source): ?array
    {
        $path = $this->statePath($source);
        if (!is_file($path) || is_link($path)) {
            return null;
        }

        $size = @filesize($path);
        if (!is_int($size) || $size <= 0 || $size > 16384) {
            $this->deleteState($source);
            return null;
        }

        $json = @file_get_contents($path);
        if (!is_string($json) || $json === '' || strlen($json) > 16384) {
            $this->deleteState($source);
            return null;
        }

        try {
            $state = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $this->deleteState($source);
            return null;
        }
        if (!is_array($state) || !$this->validState($source, $state)) {
            $this->deleteState($source);
            return null;
        }
        return $state;
    }

    /** @param array<string,mixed> $state */
    public function writeState(FeedSource $source, array $state): bool
    {
        if (!$this->validState($source, $state)) {
            return false;
        }
        return $this->writeJsonFile($this->statePath($source), $state, '.feed-state-');
    }

    public function deleteState(FeedSource $source): void
    {
        $path = $this->statePath($source);
        if (is_file($path) && !is_link($path)) {
            @unlink($path);
        }
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
        return $this->writeJsonFile($this->cachePath($source), $entry->toPayload(), '.feed-cache-');
    }

    /** @param array<string,mixed> $payload */
    private function writeJsonFile(string $target, array $payload, string $tempPrefix): bool
    {
        if (!$this->ensureDirectory() || is_link($target)) {
            return false;
        }

        try {
            $json = json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
            );
        } catch (Throwable) {
            return false;
        }

        $temp = @tempnam($this->directory, $tempPrefix);
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

    /** @param array<string,mixed> $state */
    private function validState(FeedSource $source, array $state): bool
    {
        foreach ([
            'schema', 'source_key', 'last_attempt_at', 'last_success_at', 'last_result',
            'last_http_status', 'last_error_code', 'consecutive_failures', 'next_retry_at',
        ] as $key) {
            if (!array_key_exists($key, $state)) {
                return false;
            }
        }

        if ($state['schema'] !== 1
            || !is_string($state['source_key'])
            || !hash_equals($this->cacheKey($source), $state['source_key'])
            || !is_int($state['last_attempt_at'])
            || $state['last_attempt_at'] <= 0
            || !is_int($state['last_success_at'])
            || $state['last_success_at'] < 0
            || !is_string($state['last_result'])
            || !in_array($state['last_result'], ['success', 'not_modified', 'transient_error', 'permanent_error', 'security_error'], true)
            || !is_int($state['last_http_status'])
            || $state['last_http_status'] < 0
            || $state['last_http_status'] > 599
            || !is_string($state['last_error_code'])
            || strlen($state['last_error_code']) > 64
            || ($state['last_error_code'] !== '' && preg_match('/\A[a-z0-9_]+\z/D', $state['last_error_code']) !== 1)
            || !is_int($state['consecutive_failures'])
            || $state['consecutive_failures'] < 0
            || $state['consecutive_failures'] > 1000
            || !is_int($state['next_retry_at'])
            || $state['next_retry_at'] < 0
        ) {
            return false;
        }

        $now = $this->now();
        if ($state['last_attempt_at'] > $now + self::FUTURE_CLOCK_TOLERANCE_SECONDS
            || $state['last_success_at'] > $now + self::FUTURE_CLOCK_TOLERANCE_SECONDS
            || $state['next_retry_at'] > $now + 604800
        ) {
            return false;
        }
        return true;
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
