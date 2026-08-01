<?php

declare(strict_types=1);

require_once __DIR__ . '/feed_transport_interface.php';
require_once __DIR__ . '/feed_fetcher.php';
require_once __DIR__ . '/feed_cache.php';
require_once __DIR__ . '/feed_parser.php';

/** Cache-aware orchestration of safe transport and Feed parsing. */
final class FeedFetchService
{
    public const CACHE_HIT = 'hit';
    public const CACHE_MISS = 'miss';
    public const CACHE_DISABLED = 'disabled';
    public const CACHE_BYPASS = 'bypass';

    public function __construct(
        private readonly FeedTransportInterface $transport,
        private readonly FeedParser $parser,
        private readonly ?FeedCache $cache,
        private readonly bool $cacheEnabled,
        private readonly int $lockTimeoutMs
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
            (int) APP_FEED_CACHE_LOCK_TIMEOUT_MS
        );
    }

    /**
     * @return array{ok:bool,cache_status:string,result_feed?:array<string,mixed>,effective_url?:string,fetch?:array<string,mixed>,error_type?:string,parse_error?:string}
     */
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
            // Cache/lock failure must not take down Feed display. One final
            // read catches a response stored just before the timeout; if still
            // absent, use the hardened transport without writing concurrently.
            $cached = $this->loadFreshCache($source);
            return $cached ?? $this->fetchAndParse($source, self::CACHE_BYPASS, false);
        }

        try {
            // Double-checked locking: another process may have populated the
            // cache while this request waited for the URL-specific lock.
            $cached = $this->loadFreshCache($source);
            if ($cached !== null) {
                return $cached;
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
            // A structurally valid cache may become unparsable after a parser
            // upgrade. Invalidate it and allow one safe network refresh.
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

    /** @return array<string,mixed> */
    private function fetchAndParse(FeedSource $source, string $cacheStatus, bool $storeOnSuccess): array
    {
        $fetch = $this->transport->fetch($source);
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

        // Only a response that passed both hardened transport and supported
        // Feed parsing is eligible for persistence.
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
