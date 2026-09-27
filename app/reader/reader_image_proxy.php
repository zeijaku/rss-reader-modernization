<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/validation.php';
require_once dirname(__DIR__) . '/http_fetch.php';

function reader_image_proxy_valid_token(mixed $value): ?string
{
    return is_string($value) && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1
        ? $value
        : null;
}

function reader_image_proxy_source_url(mixed $value): ?string
{
    if (!is_string($value) || $value === '' || strlen($value) > 1024) {
        return null;
    }
    return app_validate_feed_url($value);
}

function reader_image_proxy_token_for(int $userId, string $sourceUrl): ?string
{
    if ($userId <= 0 || strlen((string) INI_HASH_KEY) < 32) {
        return null;
    }

    $url = reader_image_proxy_source_url($sourceUrl);
    if ($url === null) {
        return null;
    }

    return hash_hmac('sha256', "reader-image-v1\0" . $userId . "\0" . $url, (string) INI_HASH_KEY);
}

function reader_image_proxy_public_src(string $token): ?string
{
    $safe = reader_image_proxy_valid_token($token);
    return $safe === null ? null : 'reader_image.php?id=' . rawurlencode($safe);
}

function reader_image_proxy_content_type(mixed $value): ?string
{
    if (!is_string($value) || $value === '' || strlen($value) > 128
        || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
    ) {
        return null;
    }

    $parts = explode(';', $value, 2);
    $type = strtolower(trim($parts[0]));
    return in_array($type, [
        'image/jpeg',
        'image/png',
        'image/apng',
        'image/gif',
        'image/webp',
        'image/avif',
    ], true) ? $type : null;
}

function reader_image_proxy_body_matches_type(string $body, string $contentType): bool
{
    if ($body === '') {
        return false;
    }

    return match ($contentType) {
        'image/jpeg' => strlen($body) >= 3 && substr($body, 0, 3) === "\xFF\xD8\xFF",
        'image/png', 'image/apng' => strlen($body) >= 8 && substr($body, 0, 8) === "\x89PNG\r\n\x1A\n",
        'image/gif' => str_starts_with($body, 'GIF87a') || str_starts_with($body, 'GIF89a'),
        'image/webp' => strlen($body) >= 12
            && substr($body, 0, 4) === 'RIFF'
            && substr($body, 8, 4) === 'WEBP',
        'image/avif' => strlen($body) >= 16
            && substr($body, 4, 4) === 'ftyp'
            && (
                in_array(substr($body, 8, 4), ['avif', 'avis'], true)
                || str_contains(substr($body, 8, min(64, strlen($body) - 8)), 'avif')
                || str_contains(substr($body, 8, min(64, strlen($body) - 8)), 'avis')
            ),
        default => false,
    };
}

/**
 * Filesystem-backed, user-bound registry and image cache.
 *
 * The browser receives only a 64-hex opaque HMAC token. The source URL stays
 * in this private directory outside public/ and is never accepted from a
 * reader_image.php request.
 */
final class ReaderImageProxyStore
{
    private const MAP_SCHEMA_VERSION = 1;
    private const CACHE_SCHEMA_VERSION = 1;
    private const CLOCK_TOLERANCE_SECONDS = 300;

    /** @var Closure():int */
    private Closure $clock;

    /** @param Closure():int|null $clock */
    public function __construct(
        private readonly string $directory,
        private readonly int $mappingTtlSeconds,
        private readonly int $cacheTtlSeconds,
        private readonly int $staleMaxAgeSeconds,
        private readonly int $maxBodyBytes,
        ?Closure $clock = null
    ) {
        if ($directory === '' || str_contains($directory, "\0")) {
            throw new InvalidArgumentException('Reader Image cache directory must be non-empty.');
        }
        if ($mappingTtlSeconds <= 0
            || $cacheTtlSeconds <= 0
            || $staleMaxAgeSeconds < $cacheTtlSeconds
            || $maxBodyBytes <= 0
        ) {
            throw new InvalidArgumentException('Reader Image cache settings are invalid.');
        }
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function register(int $userId, string $sourceUrl): ?string
    {
        $url = reader_image_proxy_source_url($sourceUrl);
        $token = $url === null ? null : reader_image_proxy_token_for($userId, $url);
        if ($token === null || !$this->ensureDirectory()) {
            return null;
        }

        $payload = [
            'schema' => self::MAP_SCHEMA_VERSION,
            'user_id' => $userId,
            'source_url' => $url,
            'registered_at' => ($this->clock)(),
        ];
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (!is_string($json) || strlen($json) > 8192) {
            return null;
        }

        return $this->atomicWrite($this->mappingPath($token), $json) ? $token : null;
    }

    /** @return array{user_id:int,source_url:string,registered_at:int}|null */
    public function resolve(int $userId, string $token): ?array
    {
        $safeToken = reader_image_proxy_valid_token($token);
        if ($userId <= 0 || $safeToken === null) {
            return null;
        }

        $path = $this->mappingPath($safeToken);
        if (!is_file($path) || is_link($path)) {
            return null;
        }
        $size = @filesize($path);
        if (!is_int($size) || $size <= 0 || $size > 8192) {
            return null;
        }

        $json = @file_get_contents($path);
        if (!is_string($json) || $json === '') {
            return null;
        }

        try {
            $payload = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }
        if (!is_array($payload)) {
            return null;
        }

        $storedUserId = is_int($payload['user_id'] ?? null) ? (int) $payload['user_id'] : 0;
        $url = reader_image_proxy_source_url($payload['source_url'] ?? null);
        $registeredAt = is_int($payload['registered_at'] ?? null) ? (int) $payload['registered_at'] : 0;
        $now = ($this->clock)();

        if (($payload['schema'] ?? null) !== self::MAP_SCHEMA_VERSION
            || $storedUserId !== $userId
            || $url === null
            || $registeredAt <= 0
            || $registeredAt > $now + self::CLOCK_TOLERANCE_SECONDS
            || ($now - $registeredAt) > $this->mappingTtlSeconds
        ) {
            return null;
        }

        $expected = reader_image_proxy_token_for($userId, $url);
        if ($expected === null || !hash_equals($expected, $safeToken)) {
            return null;
        }

        return [
            'user_id' => $userId,
            'source_url' => $url,
            'registered_at' => $registeredAt,
        ];
    }

    /** @return array{body:string,content_type:string,fetched_at:int,source_url:string}|null */
    public function readCache(int $userId, string $token, string $sourceUrl): ?array
    {
        $safeToken = reader_image_proxy_valid_token($token);
        $url = reader_image_proxy_source_url($sourceUrl);
        if ($userId <= 0 || $safeToken === null || $url === null) {
            return null;
        }

        $metaPath = $this->cacheMetaPath($safeToken);
        $bodyPath = $this->cacheBodyPath($safeToken);
        if (!is_file($metaPath) || is_link($metaPath) || !is_file($bodyPath) || is_link($bodyPath)) {
            return null;
        }

        $metaSize = @filesize($metaPath);
        $bodySize = @filesize($bodyPath);
        if (!is_int($metaSize) || $metaSize <= 0 || $metaSize > 8192
            || !is_int($bodySize) || $bodySize <= 0 || $bodySize > $this->maxBodyBytes
        ) {
            return null;
        }

        $json = @file_get_contents($metaPath);
        if (!is_string($json) || $json === '') {
            return null;
        }
        try {
            $meta = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }
        if (!is_array($meta)) {
            return null;
        }

        $contentType = reader_image_proxy_content_type($meta['content_type'] ?? null);
        $fetchedAt = is_int($meta['fetched_at'] ?? null) ? (int) $meta['fetched_at'] : 0;
        $storedUrl = reader_image_proxy_source_url($meta['source_url'] ?? null);
        $sha256 = is_string($meta['body_sha256'] ?? null) ? (string) $meta['body_sha256'] : '';
        $storedBytes = is_int($meta['body_bytes'] ?? null) ? (int) $meta['body_bytes'] : 0;

        if (($meta['schema'] ?? null) !== self::CACHE_SCHEMA_VERSION
            || ($meta['user_id'] ?? null) !== $userId
            || $storedUrl === null
            || !hash_equals($url, $storedUrl)
            || $contentType === null
            || $fetchedAt <= 0
            || preg_match('/\A[a-f0-9]{64}\z/D', $sha256) !== 1
            || $storedBytes !== $bodySize
        ) {
            return null;
        }

        $expected = reader_image_proxy_token_for($userId, $storedUrl);
        if ($expected === null || !hash_equals($expected, $safeToken)) {
            return null;
        }

        $body = @file_get_contents($bodyPath);
        if (!is_string($body) || strlen($body) !== $bodySize
            || !hash_equals($sha256, hash('sha256', $body))
            || !reader_image_proxy_body_matches_type($body, $contentType)
        ) {
            return null;
        }

        return [
            'body' => $body,
            'content_type' => $contentType,
            'fetched_at' => $fetchedAt,
            'source_url' => $storedUrl,
        ];
    }

    /** @param array{body:string,content_type:string} $image */
    public function writeCache(int $userId, string $token, string $sourceUrl, array $image): bool
    {
        $safeToken = reader_image_proxy_valid_token($token);
        $url = reader_image_proxy_source_url($sourceUrl);
        $body = is_string($image['body'] ?? null) ? (string) $image['body'] : '';
        $contentType = reader_image_proxy_content_type($image['content_type'] ?? null);
        if ($userId <= 0 || $safeToken === null || $url === null
            || $contentType === null || $body === '' || strlen($body) > $this->maxBodyBytes
            || !reader_image_proxy_body_matches_type($body, $contentType)
        ) {
            return false;
        }

        $expected = reader_image_proxy_token_for($userId, $url);
        if ($expected === null || !hash_equals($expected, $safeToken) || !$this->ensureDirectory()) {
            return false;
        }

        $meta = [
            'schema' => self::CACHE_SCHEMA_VERSION,
            'user_id' => $userId,
            'source_url' => $url,
            'content_type' => $contentType,
            'fetched_at' => ($this->clock)(),
            'body_bytes' => strlen($body),
            'body_sha256' => hash('sha256', $body),
        ];
        $json = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (!is_string($json) || strlen($json) > 8192) {
            return false;
        }

        if (!$this->atomicWrite($this->cacheBodyPath($safeToken), $body)) {
            return false;
        }
        if (!$this->atomicWrite($this->cacheMetaPath($safeToken), $json)) {
            @unlink($this->cacheBodyPath($safeToken));
            return false;
        }
        return true;
    }

    /** @param array{fetched_at:int} $entry */
    public function isFresh(array $entry): bool
    {
        $age = $this->ageSeconds($entry);
        return $age !== null && $age <= $this->cacheTtlSeconds;
    }

    /** @param array{fetched_at:int} $entry */
    public function canServeStale(array $entry): bool
    {
        $age = $this->ageSeconds($entry);
        return $age !== null && $age <= $this->staleMaxAgeSeconds;
    }

    /** @param array{fetched_at:int} $entry */
    private function ageSeconds(array $entry): ?int
    {
        $fetchedAt = is_int($entry['fetched_at'] ?? null) ? (int) $entry['fetched_at'] : 0;
        $now = ($this->clock)();
        if ($fetchedAt <= 0 || $fetchedAt > $now + self::CLOCK_TOLERANCE_SECONDS) {
            return null;
        }
        return max(0, $now - $fetchedAt);
    }

    private function mappingPath(string $token): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . 'reader-image-map-v1-' . $token . '.json';
    }

    private function cacheMetaPath(string $token): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . 'reader-image-cache-v1-' . $token . '.json';
    }

    private function cacheBodyPath(string $token): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . 'reader-image-cache-v1-' . $token . '.bin';
    }

    private function ensureDirectory(): bool
    {
        if (is_link($this->directory)) {
            return false;
        }
        if (is_dir($this->directory)) {
            return is_writable($this->directory);
        }
        if (!@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            return false;
        }
        @chmod($this->directory, 0700);
        return !is_link($this->directory) && is_writable($this->directory);
    }

    private function atomicWrite(string $path, string $data): bool
    {
        if (!$this->ensureDirectory() || is_link($path)) {
            return false;
        }

        $tmp = @tempnam($this->directory, '.reader-image-');
        if (!is_string($tmp) || $tmp === '') {
            return false;
        }

        $written = @file_put_contents($tmp, $data, LOCK_EX);
        if (!is_int($written) || $written !== strlen($data)) {
            @unlink($tmp);
            return false;
        }
        @chmod($tmp, 0600);

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }
        @chmod($path, 0600);
        return true;
    }
}

final class ReaderImageProxyService
{
    public function __construct(
        private readonly ReaderImageProxyStore $store,
        private readonly bool $enabled,
        private readonly bool $cacheEnabled,
        private readonly int $maxBodyBytes
    ) {
    }

    public static function fromRuntimeConfiguration(): self
    {
        return new self(
            new ReaderImageProxyStore(
                (string) APP_READER_IMAGE_CACHE_DIR,
                (int) APP_READER_IMAGE_MAPPING_TTL_SECONDS,
                (int) APP_READER_IMAGE_CACHE_TTL_SECONDS,
                (int) APP_READER_IMAGE_STALE_MAX_AGE_SECONDS,
                (int) APP_READER_IMAGE_MAX_BYTES
            ),
            (bool) APP_READER_IMAGE_PROXY_ENABLED,
            (bool) APP_READER_IMAGE_CACHE_ENABLED,
            (int) APP_READER_IMAGE_MAX_BYTES
        );
    }

    public function register(int $userId, string $sourceUrl): ?string
    {
        if (!$this->enabled) {
            return null;
        }
        return $this->store->register($userId, $sourceUrl);
    }

    /** @return array<string,mixed> */
    public function load(int $userId, string $token): array
    {
        if (!$this->enabled) {
            return $this->failure('proxy_disabled', 404);
        }

        $safeToken = reader_image_proxy_valid_token($token);
        if ($safeToken === null) {
            return $this->failure('invalid_token', 404);
        }

        $mapping = $this->store->resolve($userId, $safeToken);
        if ($mapping === null) {
            return $this->failure('not_found', 404);
        }
        $sourceUrl = (string) $mapping['source_url'];

        $stale = null;
        if ($this->cacheEnabled) {
            $cached = $this->store->readCache($userId, $safeToken, $sourceUrl);
            if ($cached !== null) {
                if ($this->store->isFresh($cached)) {
                    return $this->success($cached, 'hit', false);
                }
                if ($this->store->canServeStale($cached)) {
                    $stale = $cached;
                }
            }
        }

        $fetch = app_safe_http_fetch(
            $sourceUrl,
            null,
            null,
            [],
            [
                'accept' => 'image/avif,image/webp,image/apng,image/png,image/jpeg,image/gif;q=0.9,*/*;q=0.1',
                'retry_public_ips' => true,
                'user_agent' => (string) APP_READER_USER_AGENT,
                'max_bytes' => $this->maxBodyBytes,
            ]
        );

        if (($fetch['ok'] ?? false) !== true) {
            if ($stale !== null) {
                return $this->success($stale, 'stale', true, (string) ($fetch['error_code'] ?? 'transport_error'));
            }
            return $this->failure(
                is_string($fetch['error_code'] ?? null) ? (string) $fetch['error_code'] : 'transport_error',
                (int) ($fetch['status'] ?? 0)
            );
        }

        $contentType = reader_image_proxy_content_type($fetch['content_type'] ?? null);
        $body = is_string($fetch['body'] ?? null) ? (string) $fetch['body'] : '';
        if ($contentType === null) {
            if ($stale !== null) {
                return $this->success($stale, 'stale', true, 'unsupported_content_type');
            }
            return $this->failure('unsupported_content_type', (int) ($fetch['status'] ?? 0));
        }
        if ($body === '' || strlen($body) > $this->maxBodyBytes) {
            if ($stale !== null) {
                return $this->success($stale, 'stale', true, 'response_too_large');
            }
            return $this->failure('response_too_large', (int) ($fetch['status'] ?? 0));
        }
        if (!reader_image_proxy_body_matches_type($body, $contentType)) {
            if ($stale !== null) {
                return $this->success($stale, 'stale', true, 'invalid_image_body');
            }
            return $this->failure('invalid_image_body', (int) ($fetch['status'] ?? 0));
        }

        $entry = [
            'body' => $body,
            'content_type' => $contentType,
            'fetched_at' => time(),
            'source_url' => $sourceUrl,
        ];
        if ($this->cacheEnabled) {
            $this->store->writeCache($userId, $safeToken, $sourceUrl, [
                'body' => $body,
                'content_type' => $contentType,
            ]);
        }

        return $this->success($entry, $this->cacheEnabled ? 'miss' : 'disabled', false);
    }

    /** @param array<string,mixed> $entry @return array<string,mixed> */
    private function success(array $entry, string $cacheStatus, bool $stale, string $staleReason = ''): array
    {
        return [
            'ok' => true,
            'body' => (string) ($entry['body'] ?? ''),
            'content_type' => (string) ($entry['content_type'] ?? ''),
            'cache_status' => $cacheStatus,
            'stale' => $stale,
            'stale_reason' => $staleReason,
        ];
    }

    /** @return array<string,mixed> */
    private function failure(string $errorCode, int $status): array
    {
        return [
            'ok' => false,
            'error_code' => preg_match('/\A[a-z0-9_]{1,64}\z/D', $errorCode) === 1
                ? $errorCode
                : 'transport_error',
            'status' => max(0, min(599, $status)),
        ];
    }
}
