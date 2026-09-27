<?php

declare(strict_types=1);

final class ReaderFullTextService
{
    public function __construct(
        private readonly ?ReaderFullTextCache $cache,
        private readonly bool $cacheEnabled
    ) {
    }

    public static function fromRuntimeConfiguration(): self
    {
        return new self(
            new ReaderFullTextCache(
                (string) APP_READER_FULL_TEXT_CACHE_DIR,
                (int) APP_READER_FULL_TEXT_CACHE_TTL_SECONDS,
                (int) APP_READER_FULL_TEXT_STALE_MAX_AGE_SECONDS,
                (int) APP_HTTP_MAX_BYTES
            ),
            (bool) APP_READER_FULL_TEXT_CACHE_ENABLED
        );
    }

    /** @return array<string,mixed> */
    public function load(string $articleUrl): array
    {
        $sourceUrl = reader_full_text_fetch_url($articleUrl);
        if ($sourceUrl === null) {
            return $this->failure('invalid_url', 0);
        }

        $stale = null;
        if ($this->cacheEnabled && $this->cache !== null) {
            $cached = $this->cache->read($sourceUrl);
            if ($cached !== null) {
                if ($this->cache->isFresh($cached)) {
                    return $this->cachedSuccess($cached, 'hit', false);
                }
                if ($this->cache->canServeStale($cached)) {
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
                'accept' => 'text/html, application/xhtml+xml;q=0.9, */*;q=0.1',
                'retry_public_ips' => true,
                'user_agent' => (string) APP_READER_USER_AGENT,
            ]
        );
        if (($fetch['ok'] ?? false) !== true) {
            if ($stale !== null) {
                return $this->cachedSuccess($stale, 'stale', true, (string) ($fetch['error_code'] ?? 'transport_error'));
            }
            return $this->failure(
                is_string($fetch['error_code'] ?? null) ? (string) $fetch['error_code'] : 'transport_error',
                is_int($fetch['status'] ?? null) ? (int) $fetch['status'] : 0
            );
        }

        $rawContentType = $fetch['content_type'] ?? null;
        $contentType = reader_full_text_content_type($rawContentType);
        if ($contentType === null || !reader_full_text_content_type_allowed($contentType)) {
            if ($stale !== null) {
                return $this->cachedSuccess($stale, 'stale', true, 'unsupported_content_type');
            }
            return $this->failure('unsupported_content_type', (int) ($fetch['status'] ?? 0));
        }

        $body = is_string($fetch['body'] ?? null) ? (string) $fetch['body'] : '';
        if ($body === '') {
            if ($stale !== null) {
                return $this->cachedSuccess($stale, 'stale', true, 'empty_response');
            }
            return $this->failure('empty_response', (int) ($fetch['status'] ?? 0));
        }

        $normalized = reader_full_text_normalize_html_utf8($body, $rawContentType);
        if ($normalized === null) {
            if ($stale !== null) {
                return $this->cachedSuccess($stale, 'stale', true, 'unsupported_charset');
            }
            return $this->failure('unsupported_charset', (int) ($fetch['status'] ?? 0));
        }
        $body = (string) $normalized['html'];
        $fetch['body'] = $body;

        if ($this->cacheEnabled && $this->cache !== null) {
            $fetch['content_type'] = $contentType;
            $this->cache->writeSuccessfulFetch($sourceUrl, $fetch);
        }

        return [
            'ok' => true,
            'source_url' => $sourceUrl,
            'effective_url' => is_string($fetch['url'] ?? null) ? (string) $fetch['url'] : $sourceUrl,
            'status' => (int) ($fetch['status'] ?? 200),
            'content_type' => $contentType,
            'body' => $body,
            'cache_status' => $this->cacheEnabled ? 'miss' : 'disabled',
            'stale' => false,
            'stale_reason' => '',
        ];
    }

    /** @param array<string,mixed> $entry @return array<string,mixed> */
    private function cachedSuccess(array $entry, string $cacheStatus, bool $stale, string $staleReason = ''): array
    {
        return [
            'ok' => true,
            'source_url' => (string) ($entry['source_url'] ?? ''),
            'effective_url' => (string) ($entry['effective_url'] ?? ''),
            'status' => (int) ($entry['status'] ?? 200),
            'content_type' => (string) ($entry['content_type'] ?? ''),
            'body' => (string) ($entry['body'] ?? ''),
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
            'error_code' => preg_match('/\A[a-z0-9_]{1,64}\z/D', $errorCode) === 1 ? $errorCode : 'transport_error',
            'status' => max(0, min(599, $status)),
        ];
    }
}
