<?php

declare(strict_types=1);

require_once __DIR__ . '/feed_source.php';
require_once __DIR__ . '/feed_transport_interface.php';

/**
 * Feed transport boundary.
 *
 * The fetcher intentionally delegates to the hardened SB-09 transport. M1-B
 * now accepts a FeedSource rather than an arbitrary URL string, while still
 * preserving URL validation, DNS/IP checks, redirect validation, TLS
 * verification, timeout limits, and response-size limits implemented by
 * app_safe_http_fetch().
 */
final class FeedFetcher implements FeedTransportInterface
{
    /**
     * @return array<string,mixed>
     */
    public function fetch(FeedSource $source): array
    {
        return app_safe_http_fetch($source->url);
    }
}
