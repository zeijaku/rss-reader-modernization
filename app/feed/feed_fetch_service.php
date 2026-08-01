<?php

declare(strict_types=1);

require_once __DIR__ . '/feed_transport_interface.php';
require_once __DIR__ . '/feed_fetcher.php';
require_once __DIR__ . '/feed_cache.php';
require_once __DIR__ . '/feed_retry.php';
require_once __DIR__ . '/feed_parser.php';

/** Feed取得、Cache確認、Parser呼び出しをまとめる。 */
final class FeedFetchService
{
    public const CACHE_HIT = 'hit';
    public const CACHE_MISS = 'miss';
    public const CACHE_REVALIDATED = 'revalidated';
    public const CACHE_STALE = 'stale';
    public const CACHE_DISABLED = 'disabled';
    public const CACHE_BYPASS = 'bypass';

    public function __construct(
        private readonly FeedTransportInterface $transport,
        private readonly FeedParser $parser,
        private readonly ?FeedCache $cache,
        private readonly bool $cacheEnabled,
        private readonly int $lockTimeoutMs,
        private readonly bool $conditionalRequestEnabled = true,
        private readonly bool $retryEnabled = false,
        private readonly int $retryMaxDelaySeconds = 3600,
        private readonly bool $staleIfErrorEnabled = false,
        private readonly int $staleMaxAgeSeconds = 86400
    ) {
        if ($lockTimeoutMs < 0 || $retryMaxDelaySeconds <= 0 || $staleMaxAgeSeconds <= 0) {
            throw new InvalidArgumentException('Feed cache and retry settings are invalid.');
        }
    }

    public static function fromRuntimeConfiguration(): self
    {
        return new self(
            new FeedFetcher(),
            new FeedParser(),
            new FeedCache(
                (string) APP_FEED_CACHE_DIR,
                (int) APP_FEED_CACHE_TTL_SECONDS,
                (int) APP_HTTP_MAX_BYTES
            ),
            (bool) APP_FEED_CACHE_ENABLED,
            (int) APP_FEED_CACHE_LOCK_TIMEOUT_MS,
            (bool) APP_FEED_CONDITIONAL_REQUEST_ENABLED,
            (bool) APP_FEED_RETRY_ENABLED,
            (int) APP_FEED_RETRY_MAX_DELAY_SECONDS,
            (bool) APP_FEED_STALE_IF_ERROR_ENABLED,
            (int) APP_FEED_STALE_MAX_AGE_SECONDS
        );
    }

    /** @return array<string,mixed> */
    public function load(FeedSource $source): array
    {
        if (!$this->cacheEnabled || $this->cache === null) {
            return $this->fetchAndParse($source, self::CACHE_DISABLED, false, null, false);
        }

        $cached = $this->loadFreshCache($source);
        if ($cached !== null) {
            return $cached;
        }

        $stale = $this->loadStaleCache($source);
        $backoff = $this->loadBackoffResult($source, $stale);
        if ($backoff !== null) {
            return $backoff;
        }

        $lock = $this->cache->acquireLock($source, $this->lockTimeoutMs);
        if ($lock === null) {
            $cached = $this->loadFreshCache($source);
            if ($cached !== null) {
                return $cached;
            }
            return $this->fetchAndParse($source, self::CACHE_BYPASS, false, $stale, false);
        }

        try {
            // Lock待ちの間に別processが更新している可能性があるため再確認する。
            $cached = $this->loadFreshCache($source);
            if ($cached !== null) {
                return $cached;
            }

            $stale = $this->loadStaleCache($source);
            $backoff = $this->loadBackoffResult($source, $stale);
            if ($backoff !== null) {
                return $backoff;
            }

            if ($this->conditionalRequestEnabled && $stale !== null && $stale['entry']->validators() !== []) {
                return $this->revalidate($source, $stale['entry'], $stale['feed']);
            }

            return $this->fetchAndParse($source, self::CACHE_MISS, true, $stale, true);
        } finally {
            $lock->release();
        }
    }

    /** @return array<string,mixed>|null */
    private function loadFreshCache(FeedSource $source): ?array
    {
        if ($this->cache === null) {
            return null;
        }

        $entry = $this->cache->readFresh($source);
        if ($entry === null) {
            return null;
        }

        $feed = $this->parser->parse_start($entry->body, $source->url);
        if ($feed === []) {
            $this->cache->delete($source);
            return null;
        }

        return [
            'ok' => true,
            'cache_status' => self::CACHE_HIT,
            'result_feed' => $feed,
            'effective_url' => $entry->effectiveUrl,
        ];
    }

    /** @return array{entry:FeedCacheEntry,feed:array<string,mixed>}|null */
    private function loadStaleCache(FeedSource $source): ?array
    {
        if ($this->cache === null) {
            return null;
        }

        $entry = $this->cache->readStale($source);
        if ($entry === null) {
            return null;
        }

        $feed = $this->parser->parse_start($entry->body, $source->url);
        if ($feed === []) {
            $this->cache->delete($source);
            return null;
        }

        return ['entry' => $entry, 'feed' => $feed];
    }

    /** @param array{entry:FeedCacheEntry,feed:array<string,mixed>}|null $stale @return array<string,mixed>|null */
    private function loadBackoffResult(FeedSource $source, ?array $stale): ?array
    {
        if (!$this->retryEnabled || $this->cache === null) {
            return null;
        }

        $state = $this->cache->readState($source);
        if ($state === null || (int) $state['next_retry_at'] <= $this->cache->now()) {
            return null;
        }
        if ($stale !== null && $stale['entry']->validatedAt >= (int) $state['last_attempt_at']) {
            return null;
        }

        if ($state['last_result'] === 'transient_error' && $this->canUseStale($stale)) {
            return $this->staleResult($stale);
        }

        return [
            'ok' => false,
            'cache_status' => self::CACHE_MISS,
            'error_type' => 'fetch',
            'fetch' => [
                'ok' => false,
                'status' => (int) $state['last_http_status'],
                'error_code' => 'retry_backoff',
                'error_message' => 'Feed retry is waiting for the next attempt time.',
            ],
        ];
    }

    /** @param array<string,mixed> $cachedFeed @return array<string,mixed> */
    private function revalidate(FeedSource $source, FeedCacheEntry $cached, array $cachedFeed): array
    {
        $fetch = $this->transport->fetch($source, $cached->validators());
        if (($fetch['ok'] ?? false) !== true) {
            return $this->handleFailure($source, [
                'ok' => false,
                'cache_status' => self::CACHE_MISS,
                'error_type' => 'fetch',
                'fetch' => $fetch,
            ], ['entry' => $cached, 'feed' => $cachedFeed], true);
        }

        if (($fetch['not_modified'] ?? false) === true) {
            if (($fetch['status'] ?? null) !== 304) {
                return $this->handleFailure($source, [
                    'ok' => false,
                    'cache_status' => self::CACHE_MISS,
                    'error_type' => 'fetch',
                    'fetch' => [
                        'ok' => false,
                        'status' => (int) ($fetch['status'] ?? 0),
                        'error_code' => 'unexpected_not_modified',
                        'error_message' => 'Invalid not-modified response.',
                    ],
                ], ['entry' => $cached, 'feed' => $cachedFeed], true);
            }

            $this->cache?->writeNotModified($source, $cached, $fetch);
            $this->writeSuccessState($source, 'not_modified', 304);
            return [
                'ok' => true,
                'cache_status' => self::CACHE_REVALIDATED,
                'result_feed' => $cachedFeed,
                'effective_url' => $cached->effectiveUrl,
                'fetch' => $fetch,
            ];
        }

        return $this->parseFetchResult($source, $fetch, self::CACHE_MISS, true, ['entry' => $cached, 'feed' => $cachedFeed], true);
    }

    /** @param array{entry:FeedCacheEntry,feed:array<string,mixed>}|null $stale @return array<string,mixed> */
    private function fetchAndParse(
        FeedSource $source,
        string $cacheStatus,
        bool $storeOnSuccess,
        ?array $stale,
        bool $updateState
    ): array {
        $fetch = $this->transport->fetch($source);
        if (($fetch['not_modified'] ?? false) === true) {
            $fetch = [
                'ok' => false,
                'url' => $source->url,
                'status' => 304,
                'body' => '',
                'error_code' => 'unexpected_not_modified',
                'error_message' => 'Unexpected HTTP 304 response.',
            ];
        }
        return $this->parseFetchResult($source, $fetch, $cacheStatus, $storeOnSuccess, $stale, $updateState);
    }

    /** @param array<string,mixed> $fetch @param array{entry:FeedCacheEntry,feed:array<string,mixed>}|null $stale @return array<string,mixed> */
    private function parseFetchResult(
        FeedSource $source,
        array $fetch,
        string $cacheStatus,
        bool $storeOnSuccess,
        ?array $stale,
        bool $updateState
    ): array {
        if (($fetch['ok'] ?? false) !== true) {
            return $this->handleFailure($source, [
                'ok' => false,
                'cache_status' => $cacheStatus,
                'error_type' => 'fetch',
                'fetch' => $fetch,
            ], $stale, $updateState);
        }

        $body = is_string($fetch['body'] ?? null) ? $fetch['body'] : '';
        $effectiveUrl = is_string($fetch['url'] ?? null) ? $fetch['url'] : $source->url;
        $feed = $this->parser->parse_start($body, $source->url);
        if ($feed === []) {
            return $this->handleFailure($source, [
                'ok' => false,
                'cache_status' => $cacheStatus,
                'error_type' => 'parse',
                'parse_error' => (string) ($this->parser->last_error ?? 'unknown parse error'),
                'fetch' => $fetch,
            ], $stale, $updateState);
        }

        if ($storeOnSuccess && $this->cache !== null) {
            $this->cache->writeSuccessfulFetch($source, $fetch);
        }
        if ($updateState) {
            $this->writeSuccessState($source, 'success', (int) ($fetch['status'] ?? 0));
        }

        return [
            'ok' => true,
            'cache_status' => $cacheStatus,
            'result_feed' => $feed,
            'effective_url' => $effectiveUrl,
            'fetch' => $fetch,
        ];
    }

    /** @param array<string,mixed> $failure @param array{entry:FeedCacheEntry,feed:array<string,mixed>}|null $stale @return array<string,mixed> */
    private function handleFailure(FeedSource $source, array $failure, ?array $stale, bool $updateState): array
    {
        $errorType = is_string($failure['error_type'] ?? null) ? $failure['error_type'] : 'fetch';
        $fetch = is_array($failure['fetch'] ?? null) ? $failure['fetch'] : [];
        $kind = feed_failure_kind($errorType, $fetch);

        if ($updateState) {
            $this->writeFailureState($source, $errorType, $kind, $fetch, $stale);
        }

        if ($kind === 'transient' && $this->canUseStale($stale)) {
            return $this->staleResult($stale);
        }
        return $failure;
    }

    /** @param array{entry:FeedCacheEntry,feed:array<string,mixed>}|null $stale */
    private function canUseStale(?array $stale): bool
    {
        if (!$this->staleIfErrorEnabled || $this->cache === null || $stale === null) {
            return false;
        }
        $age = $this->cache->ageSeconds($stale['entry']);
        return $age !== null && $age <= $this->staleMaxAgeSeconds;
    }

    /** @param array{entry:FeedCacheEntry,feed:array<string,mixed>} $stale @return array<string,mixed> */
    private function staleResult(array $stale): array
    {
        return [
            'ok' => true,
            'cache_status' => self::CACHE_STALE,
            'result_feed' => $stale['feed'],
            'effective_url' => $stale['entry']->effectiveUrl,
        ];
    }

    private function writeSuccessState(FeedSource $source, string $result, int $status): void
    {
        if (!$this->retryEnabled || $this->cache === null) {
            return;
        }
        $now = $this->cache->now();
        $this->cache->writeState($source, [
            'schema' => 1,
            'source_key' => $this->cache->cacheKey($source),
            'last_attempt_at' => $now,
            'last_success_at' => $now,
            'last_result' => $result,
            'last_http_status' => max(0, min(599, $status)),
            'last_error_code' => '',
            'consecutive_failures' => 0,
            'next_retry_at' => 0,
        ]);
    }

    /** @param array<string,mixed> $fetch @param array{entry:FeedCacheEntry,feed:array<string,mixed>}|null $stale */
    private function writeFailureState(FeedSource $source, string $errorType, string $kind, array $fetch, ?array $stale): void
    {
        if (!$this->retryEnabled || $this->cache === null) {
            return;
        }

        $old = $this->cache->readState($source);
        $failures = is_array($old) ? (int) $old['consecutive_failures'] + 1 : 1;
        $failures = max(1, min(1000, $failures));
        $now = $this->cache->now();
        $delay = feed_retry_delay_seconds($failures, $kind, $fetch, $now, $this->retryMaxDelaySeconds);
        $status = is_int($fetch['status'] ?? null) ? $fetch['status'] : 0;
        $errorCode = $errorType === 'parse' ? 'parse_error' : (is_string($fetch['error_code'] ?? null) ? $fetch['error_code'] : 'upstream_error');
        if (preg_match('/\A[a-z0-9_]{1,64}\z/D', $errorCode) !== 1) {
            $errorCode = 'upstream_error';
        }

        $lastSuccess = is_array($old) ? (int) $old['last_success_at'] : 0;
        if ($lastSuccess <= 0 && $stale !== null) {
            $lastSuccess = $stale['entry']->validatedAt;
        }

        $this->cache->writeState($source, [
            'schema' => 1,
            'source_key' => $this->cache->cacheKey($source),
            'last_attempt_at' => $now,
            'last_success_at' => max(0, $lastSuccess),
            'last_result' => $kind . '_error',
            'last_http_status' => max(0, min(599, $status)),
            'last_error_code' => $errorCode,
            'consecutive_failures' => $failures,
            'next_retry_at' => $delay > 0 ? $now + $delay : 0,
        ]);
    }
}
