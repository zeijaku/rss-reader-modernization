<?php

declare(strict_types=1);

require_once __DIR__ . '/feed_http_headers.php';

/** Cacheファイル1件分の内容。 */
final class FeedCacheEntry
{
    public const SCHEMA_VERSION = 2;

    private function __construct(
        public readonly string $sourceUrl,
        public readonly string $effectiveUrl,
        public readonly int $status,
        public readonly int $bodyFetchedAt,
        public readonly int $validatedAt,
        public readonly string $body,
        public readonly ?string $etag,
        public readonly ?string $lastModified
    ) {
    }

    /** @param array<string,mixed> $fetch */
    public static function fromSuccessfulFetch(FeedSource $source, array $fetch, int $fetchedAt, int $maxBodyBytes): ?self
    {
        if (($fetch['ok'] ?? false) !== true || ($fetch['not_modified'] ?? false) === true || $fetchedAt <= 0) {
            return null;
        }

        $body = $fetch['body'] ?? null;
        $effectiveUrl = $fetch['url'] ?? $source->url;
        $status = $fetch['status'] ?? null;
        if (!is_string($body) || $body === '' || strlen($body) > $maxBodyBytes) {
            return null;
        }
        if (!is_string($effectiveUrl) || !self::isHttpUrl($effectiveUrl)) {
            return null;
        }
        if (!is_int($status) || $status < 200 || $status >= 300) {
            return null;
        }

        return new self(
            $source->url,
            $effectiveUrl,
            $status,
            $fetchedAt,
            $fetchedAt,
            $body,
            feed_clean_etag($fetch['etag'] ?? null),
            feed_clean_last_modified($fetch['last_modified'] ?? null)
        );
    }

    /** @param array<string,mixed> $fetch */
    public static function fromNotModified(self $cached, array $fetch, int $validatedAt): ?self
    {
        if (($fetch['ok'] ?? false) !== true
            || ($fetch['not_modified'] ?? false) !== true
            || ($fetch['status'] ?? null) !== 304
            || $validatedAt <= 0
            || !is_string($fetch['url'] ?? null)
            || !hash_equals($cached->effectiveUrl, (string) $fetch['url'])
        ) {
            return null;
        }

        $etag = feed_clean_etag($fetch['etag'] ?? null) ?? $cached->etag;
        $lastModified = feed_clean_last_modified($fetch['last_modified'] ?? null) ?? $cached->lastModified;

        return new self(
            $cached->sourceUrl,
            $cached->effectiveUrl,
            $cached->status,
            $cached->bodyFetchedAt,
            $validatedAt,
            $cached->body,
            $etag,
            $lastModified
        );
    }

    /** @param array<string,mixed> $payload */
    public static function fromPayload(array $payload, string $expectedSourceUrl, int $maxBodyBytes): ?self
    {
        $schema = $payload['schema'] ?? null;
        if ($schema !== 1 && $schema !== self::SCHEMA_VERSION) {
            return null;
        }

        $required = ['source_url', 'effective_url', 'status', 'fetched_at', 'body_base64', 'body_sha256'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $payload)) {
                return null;
            }
        }

        if (!is_string($payload['source_url'])
            || !hash_equals($expectedSourceUrl, $payload['source_url'])
            || !is_string($payload['effective_url'])
            || !self::isHttpUrl($payload['effective_url'])
            || !is_int($payload['status'])
            || $payload['status'] < 200
            || $payload['status'] >= 300
            || !is_int($payload['fetched_at'])
            || $payload['fetched_at'] <= 0
            || !is_string($payload['body_base64'])
            || !is_string($payload['body_sha256'])
            || preg_match('/\A[a-f0-9]{64}\z/D', $payload['body_sha256']) !== 1
        ) {
            return null;
        }

        $body = base64_decode($payload['body_base64'], true);
        if (!is_string($body) || $body === '' || strlen($body) > $maxBodyBytes) {
            return null;
        }
        if (!hash_equals($payload['body_sha256'], hash('sha256', $body))) {
            return null;
        }

        if ($schema === 1) {
            return new self(
                $payload['source_url'],
                $payload['effective_url'],
                $payload['status'],
                $payload['fetched_at'],
                $payload['fetched_at'],
                $body,
                null,
                null
            );
        }

        foreach (['body_fetched_at', 'validated_at', 'etag', 'last_modified'] as $key) {
            if (!array_key_exists($key, $payload)) {
                return null;
            }
        }
        if (!is_int($payload['body_fetched_at']) || $payload['body_fetched_at'] <= 0
            || !is_int($payload['validated_at']) || $payload['validated_at'] <= 0
            || $payload['fetched_at'] !== $payload['validated_at']
            || $payload['body_fetched_at'] > $payload['validated_at']
            || ($payload['etag'] !== null && feed_clean_etag($payload['etag']) === null)
            || ($payload['last_modified'] !== null && feed_clean_last_modified($payload['last_modified']) === null)
        ) {
            return null;
        }

        return new self(
            $payload['source_url'],
            $payload['effective_url'],
            $payload['status'],
            $payload['body_fetched_at'],
            $payload['validated_at'],
            $body,
            $payload['etag'] === null ? null : feed_clean_etag($payload['etag']),
            $payload['last_modified'] === null ? null : feed_clean_last_modified($payload['last_modified'])
        );
    }

    /** @return array<string,mixed> */
    public function toPayload(): array
    {
        return [
            'schema' => self::SCHEMA_VERSION,
            'source_url' => $this->sourceUrl,
            'effective_url' => $this->effectiveUrl,
            'status' => $this->status,
            // fetched_atはM1-E形式との確認互換用。値はvalidated_atと同じ。
            'fetched_at' => $this->validatedAt,
            'body_fetched_at' => $this->bodyFetchedAt,
            'validated_at' => $this->validatedAt,
            'etag' => $this->etag,
            'last_modified' => $this->lastModified,
            'body_base64' => base64_encode($this->body),
            'body_sha256' => hash('sha256', $this->body),
        ];
    }

    /** @return array<string,mixed> */
    public function validators(): array
    {
        if ($this->etag === null && $this->lastModified === null) {
            return [];
        }
        return [
            'resource_url' => $this->effectiveUrl,
            'etag' => $this->etag,
            'last_modified' => $this->lastModified,
        ];
    }

    private static function isHttpUrl(string $url): bool
    {
        if ($url === '' || trim($url) !== $url || strlen($url) > 2048) {
            return false;
        }
        $parts = parse_url($url);
        return is_array($parts)
            && isset($parts['scheme'], $parts['host'])
            && in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
