<?php

declare(strict_types=1);

/** Transport boundary used by the cache-aware Feed loading service. */
interface FeedTransportInterface
{
    /** @return array<string,mixed> */
    public function fetch(FeedSource $source): array;
}
