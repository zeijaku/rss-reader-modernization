<?php

declare(strict_types=1);

require_once __DIR__ . '/feed_transport_interface.php';
require_once __DIR__ . '/feed_fetcher.php';
require_once __DIR__ . '/feed_cache.php';
require_once __DIR__ . '/feed_parser.php';

/** Feed取得、Cache確認、Parser呼び出しをまとめる。 */
final class FeedFetchService
{
    public const CACHE_HIT = 'hit';
    public const CACHE_MISS = 'miss';
    public const CACHE_REVALIDATED = 'revalidated';
    public const CACHE_DISABLED = 'disabled';
    public const CACHE_BYPASS = 'bypass';

    public function __construct(
        private readonly FeedTransportInterface $transport,
        private readonly FeedParser $parser,
        private readonly ?FeedCache $cache,
        private readonly bool $cacheEnabled,
        private readonly int $lockTimeoutMs,
        private readonly bool $conditionalRequestEnabled = true
    ) {
        if ($lockTimeoutMs < 0) {
            throw new InvalidArgumentException('Feed cache lock timeout cannot be negative.');
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
            (bool) APP_FEED_CONDITIONAL_REQUEST_ENABLED
        );
    }

    /** @return array<string,mixed> */
    public function load(FeedSource $source): array
    {
        if (!$this->cacheEnabled || $this->cache === null) {
            return $this->fetchAndParse($source, self::CACHE_DISABLED, false);
        }

        $cached = $this->loadFreshCache($source);
        if ($cached !== null) {
            return $cached;
        }

        $lock = $this->cache->acquireLock($source, $this->lockTimeoutMs);
        if ($lock === null) {
            $cached = $this->loadFreshCache($source);
            return $cached ?? $this->fetchAndParse($source, self::CACHE_BYPASS, false);
        }

        try {
            // Lock待ちの間に別processが更新している可能性があるため再確認する。
            $cached = $this->loadFreshCache($source);
            if ($cached !== null) {
                return $cached;
            }

            if ($this->conditionalRequestEnabled) {
                $stale = $this->loadStaleCache($source);
                if ($stale !== null && $stale['entry']->validators() !== []) {
                    return $this->revalidate($source, $stale['entry'], $stale['feed']);
                }
            }

            return $this->fetchAndParse($source, self::CACHE_MISS, true);
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

    /** @param array<string,mixed> $cachedFeed @return array<string,mixed> */
    private function revalidate(FeedSource $source, FeedCacheEntry $cached, array $cachedFeed): array
    {
        $fetch = $this->transport->fetch($source, $cached->validators());
        if (($fetch['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'cache_status' => self::CACHE_MISS,
                'error_type' => 'fetch',
                'fetch' => $fetch,
            ];
        }

        if (($fetch['not_modified'] ?? false) === true) {
            if (($fetch['status'] ?? null) !== 304) {
                return [
                    'ok' => false,
                    'cache_status' => self::CACHE_MISS,
                    'error_type' => 'fetch',
                    'fetch' => [
                        'ok' => false,
                        'status' => (int) ($fetch['status'] ?? 0),
                        'error_code' => 'unexpected_not_modified',
                        'error_message' => 'Invalid not-modified response.',
                    ],
                ];
            }

            $this->cache?->writeNotModified($source, $cached, $fetch);
            return [
                'ok' => true,
                'cache_status' => self::CACHE_REVALIDATED,
                'result_feed' => $cachedFeed,
                'effective_url' => $cached->effectiveUrl,
                'fetch' => $fetch,
            ];
        }

        return $this->parseFetchResult($source, $fetch, self::CACHE_MISS, true);
    }

    /** @return array<string,mixed> */
    private function fetchAndParse(FeedSource $source, string $cacheStatus, bool $storeOnSuccess): array
    {
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
        return $this->parseFetchResult($source, $fetch, $cacheStatus, $storeOnSuccess);
    }

    /** @param array<string,mixed> $fetch @return array<string,mixed> */
    private function parseFetchResult(FeedSource $source, array $fetch, string $cacheStatus, bool $storeOnSuccess): array
    {
        if (($fetch['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'cache_status' => $cacheStatus,
                'error_type' => 'fetch',
                'fetch' => $fetch,
            ];
        }

        $body = is_string($fetch['body'] ?? null) ? $fetch['body'] : '';
        $effectiveUrl = is_string($fetch['url'] ?? null) ? $fetch['url'] : $source->url;
        $feed = $this->parser->parse_start($body, $source->url);
        if ($feed === []) {
            return [
                'ok' => false,
                'cache_status' => $cacheStatus,
                'error_type' => 'parse',
                'parse_error' => (string) ($this->parser->last_error ?? 'unknown parse error'),
                'fetch' => $fetch,
            ];
        }

        if ($storeOnSuccess && $this->cache !== null) {
            $this->cache->writeSuccessfulFetch($source, $fetch);
        }

        return [
            'ok' => true,
            'cache_status' => $cacheStatus,
            'result_feed' => $feed,
            'effective_url' => $effectiveUrl,
            'fetch' => $fetch,
        ];
    }
}
