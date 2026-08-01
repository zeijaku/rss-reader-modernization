<?php

declare(strict_types=1);

/**
 * Source-agnostic identity and validated endpoint for one configured Feed.
 *
 * M1-B intentionally maps the existing content table into this small runtime
 * model. It does not introduce a new table, change content_id semantics, or
 * include presentation fields such as content_style/content_location.
 */
final class FeedSource
{
    private function __construct(
        public readonly int $sourceId,
        public readonly int $ownerId,
        public readonly string $url
    ) {
    }

    /**
     * Create a source only after the stored URL has passed
     * app_validate_feed_url(). The factory still rejects structurally invalid
     * identifiers or an empty/non-canonical URL so invalid DB data cannot flow
     * silently into the Feed engine.
     */
    public static function fromValidatedValues(int $sourceId, int $ownerId, string $url): self
    {
        if ($sourceId <= 0) {
            throw new InvalidArgumentException('Feed source ID must be a positive integer.');
        }
        if ($ownerId <= 0) {
            throw new InvalidArgumentException('Feed source owner ID must be a positive integer.');
        }
        if ($url === '' || trim($url) !== $url) {
            throw new InvalidArgumentException('Feed source URL must be a non-empty validated URL.');
        }

        return new self($sourceId, $ownerId, $url);
    }
}
