<?php

declare(strict_types=1);

/**
 * Small file cache dedicated to UTF-8-normalized article HTML.
 *
 * This intentionally does not reuse FeedCache because FeedCache is typed around
 * FeedSource/FeedCacheEntry and Feed parser semantics. The storage protections
 * remain equivalent: hashed file names, size cap, checksum, symlink rejection,
 * atomic replacement and bounded stale serving.
 */
final class ReaderFullTextCache
{
    private const SCHEMA_VERSION = 2;
    private const FILE_PREFIX = 'reader-full-text-v1-';
    private const FUTURE_CLOCK_TOLERANCE_SECONDS = 300;

    /** @var Closure():int */
    private Closure $clock;

    /** @param Closure():int|null $clock */
    public function __construct(
        private readonly string $directory,
        private readonly int $ttlSeconds,
        private readonly int $staleMaxAgeSeconds,
        private readonly int $maxBodyBytes,
        ?Closure $clock = null
    ) {
        if ($directory === '' || str_contains($directory, "\0")) {
            throw new InvalidArgumentException('Reader Full Text cache directory must be non-empty.');
        }
        if ($ttlSeconds <= 0 || $staleMaxAgeSeconds < $ttlSeconds || $maxBodyBytes <= 0) {
            throw new InvalidArgumentException('Reader Full Text cache settings are invalid.');
        }
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function cachePath(string $sourceUrl): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . self::FILE_PREFIX . hash('sha256', $sourceUrl) . '.json';
    }

    /** @return array<string,mixed>|null */
    public function read(string $sourceUrl): ?array
    {
        $path = $this->cachePath($sourceUrl);
        if (!is_file($path) || is_link($path)) {
            return null;
        }

        $maxFileBytes = (int) ceil($this->maxBodyBytes * 4 / 3) + 65536;
        $size = @filesize($path);
        if (!is_int($size) || $size <= 0 || $size > $maxFileBytes) {
            $this->delete($sourceUrl);
            return null;
        }

        $json = @file_get_contents($path);
        if (!is_string($json) || $json === '' || strlen($json) > $maxFileBytes) {
            $this->delete($sourceUrl);
            return null;
        }

        try {
            $payload = json_decode($json, true, 24, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $this->delete($sourceUrl);
            return null;
        }
        if (!is_array($payload)
            || ($payload['schema'] ?? null) !== self::SCHEMA_VERSION
            || !is_string($payload['source_url'] ?? null)
            || !hash_equals($sourceUrl, (string) $payload['source_url'])
            || !is_string($payload['effective_url'] ?? null)
            || app_validate_feed_url((string) $payload['effective_url']) !== (string) $payload['effective_url']
            || !reader_full_text_content_type_allowed($payload['content_type'] ?? null)
            || !is_int($payload['status'] ?? null)
            || (int) $payload['status'] < 200
            || (int) $payload['status'] >= 300
            || !is_int($payload['fetched_at'] ?? null)
            || (int) $payload['fetched_at'] <= 0
            || !is_string($payload['body_base64'] ?? null)
            || !is_string($payload['body_sha256'] ?? null)
            || preg_match('/\A[a-f0-9]{64}\z/D', (string) $payload['body_sha256']) !== 1
        ) {
            $this->delete($sourceUrl);
            return null;
        }

        $body = base64_decode((string) $payload['body_base64'], true);
        if (!is_string($body) || $body === '' || strlen($body) > $this->maxBodyBytes
            || !hash_equals((string) $payload['body_sha256'], hash('sha256', $body))
            || !app_is_valid_utf8($body)
        ) {
            $this->delete($sourceUrl);
            return null;
        }

        $now = ($this->clock)();
        $fetchedAt = (int) $payload['fetched_at'];
        if ($fetchedAt > $now + self::FUTURE_CLOCK_TOLERANCE_SECONDS) {
            $this->delete($sourceUrl);
            return null;
        }

        return [
            'source_url' => $sourceUrl,
            'effective_url' => (string) $payload['effective_url'],
            'status' => (int) $payload['status'],
            'content_type' => reader_full_text_content_type($payload['content_type']) ?? '',
            'fetched_at' => $fetchedAt,
            'body' => $body,
        ];
    }

    /** @param array<string,mixed> $entry */
    public function ageSeconds(array $entry): ?int
    {
        $fetchedAt = $entry['fetched_at'] ?? null;
        if (!is_int($fetchedAt) || $fetchedAt <= 0) {
            return null;
        }
        $age = ($this->clock)() - $fetchedAt;
        return $age >= 0 ? $age : null;
    }

    /** @param array<string,mixed> $entry */
    public function isFresh(array $entry): bool
    {
        $age = $this->ageSeconds($entry);
        return $age !== null && $age <= $this->ttlSeconds;
    }

    /** @param array<string,mixed> $entry */
    public function canServeStale(array $entry): bool
    {
        $age = $this->ageSeconds($entry);
        return $age !== null && $age <= $this->staleMaxAgeSeconds;
    }

    /** @param array<string,mixed> $fetch */
    public function writeSuccessfulFetch(string $sourceUrl, array $fetch): bool
    {
        if (($fetch['ok'] ?? false) !== true
            || ($fetch['not_modified'] ?? false) === true
            || reader_full_text_fetch_url($sourceUrl) !== $sourceUrl
        ) {
            return false;
        }

        $body = $fetch['body'] ?? null;
        $effectiveUrl = $fetch['url'] ?? null;
        $status = $fetch['status'] ?? null;
        $contentType = reader_full_text_content_type($fetch['content_type'] ?? null);
        if (!is_string($body) || $body === '' || strlen($body) > $this->maxBodyBytes
            || !app_is_valid_utf8($body)
            || !is_string($effectiveUrl) || app_validate_feed_url($effectiveUrl) !== $effectiveUrl
            || !is_int($status) || $status < 200 || $status >= 300
            || $contentType === null || !reader_full_text_content_type_allowed($contentType)
        ) {
            return false;
        }

        $payload = [
            'schema' => self::SCHEMA_VERSION,
            'source_url' => $sourceUrl,
            'effective_url' => $effectiveUrl,
            'status' => $status,
            'content_type' => $contentType,
            'fetched_at' => ($this->clock)(),
            'body_base64' => base64_encode($body),
            'body_sha256' => hash('sha256', $body),
        ];

        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (Throwable) {
            return false;
        }
        if (!is_string($json) || !$this->ensureDirectory()) {
            return false;
        }

        $tmp = @tempnam($this->directory, '.reader-full-text-');
        if (!is_string($tmp) || $tmp === '') {
            return false;
        }

        $written = @file_put_contents($tmp, $json, LOCK_EX);
        if (!is_int($written) || $written !== strlen($json)) {
            @unlink($tmp);
            return false;
        }
        @chmod($tmp, 0600);

        $path = $this->cachePath($sourceUrl);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }
        @chmod($path, 0600);
        return true;
    }

    public function delete(string $sourceUrl): void
    {
        $path = $this->cachePath($sourceUrl);
        if (is_file($path) || is_link($path)) {
            @unlink($path);
        }
    }

    private function ensureDirectory(): bool
    {
        if (is_link($this->directory)) {
            return false;
        }
        if (is_dir($this->directory)) {
            return true;
        }
        if (!@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            return false;
        }
        @chmod($this->directory, 0700);
        return !is_link($this->directory);
    }
}
