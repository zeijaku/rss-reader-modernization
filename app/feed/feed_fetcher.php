<?php

declare(strict_types=1);

/**
 * Feed transport boundary.
 *
 * The fetcher intentionally delegates to the hardened SB-09 transport. M1-A
 * separates responsibilities only; it does not weaken, duplicate, or replace
 * URL validation, DNS/IP checks, redirect validation, TLS verification,
 * timeout limits, or response-size limits implemented by app_safe_http_fetch().
 */
final class FeedFetcher
{
    /**
     * @return array<string,mixed>
     */
    public function fetch(string $url): array
    {
        return app_safe_http_fetch($url);
    }
}
