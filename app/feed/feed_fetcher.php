<?php

declare(strict_types=1);

require_once __DIR__ . '/feed_source.php';
require_once __DIR__ . '/feed_transport_interface.php';

/** SB-09の安全なHTTP取得をFeedSourceから呼び出す。 */
final class FeedFetcher implements FeedTransportInterface
{
    /** @return array<string,mixed> */
    public function fetch(FeedSource $source, array $validators = []): array
    {
        return app_safe_http_fetch($source->url, null, null, $validators);
    }
}
