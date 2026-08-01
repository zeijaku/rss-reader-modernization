<?php

declare(strict_types=1);

/** Validated immutable representation of one cached upstream Feed response. */
final class FeedCacheEntry
{
    public const SCHEMA_VERSION = 1;

    private function __construct(
        public readonly string $sourceUrl,
        public readonly string $effectiveUrl,
        public readonly int $status,
        public readonly int $fetchedAt,
        public readonly string $body
    ) {
    }

    /** @param array<string,mixed> $fetch */
    public static function fromSuccessfulFetch(FeedSource $source, array $fetch, int $fetchedAt, int $maxBodyBytes): ?self
    {
        if (($fetch['ok'] ?? false) !== true || $fetchedAt <= 0) {
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

        return new self($source->url, $effectiveUrl, $status, $fetchedAt, $body);
    }

    /** @param array<string,mixed> $payload */
    public static function fromPayload(array $payload, string $expectedSourceUrl, int $maxBodyBytes): ?self
    {
        $required = ['schema', 'source_url', 'effective_url', 'status', 'fetched_at', 'body_base64', 'body_sha256'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $payload)) {
                return null;
            }
        }

        if ($payload['schema'] !== self::SCHEMA_VERSION
            || !is_string($payload['source_url'])
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

        return new self(
            $payload['source_url'],
            $payload['effective_url'],
            $payload['status'],
            $payload['fetched_at'],
            $body
        );
    }

    /** @return array{schema:int,source_url:string,effective_url:string,status:int,fetched_at:int,body_base64:string,body_sha256:string} */
    public function toPayload(): array
    {
        return [
            'schema' => self::SCHEMA_VERSION,
            'source_url' => $this->sourceUrl,
            'effective_url' => $this->effectiveUrl,
            'status' => $this->status,
            'fetched_at' => $this->fetchedAt,
            'body_base64' => base64_encode($this->body),
            'body_sha256' => hash('sha256', $this->body),
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
