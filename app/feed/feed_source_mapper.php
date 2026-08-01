<?php

declare(strict_types=1);

require_once __DIR__ . '/feed_source.php';

/**
 * Map an owner-scoped active content row into the Feed engine model.
 *
 * The database lookup remains responsible for selecting an active row owned by
 * the authenticated user. This mapper verifies that invariant again at the
 * boundary and deliberately ignores UI-only columns.
 */
final class FeedSourceMapper
{
    /**
     * @param array<string,mixed> $content
     */
    public function fromOwnedContent(array $content, int $authenticatedOwnerId, string $validatedUrl): ?FeedSource
    {
        if ($authenticatedOwnerId <= 0) {
            return null;
        }

        $sourceId = $this->positiveInt($content['content_id'] ?? null);
        $ownerId = $this->positiveInt($content['content_owner'] ?? null);

        if ($sourceId === null || $ownerId === null || $ownerId !== $authenticatedOwnerId) {
            return null;
        }

        try {
            return FeedSource::fromValidatedValues($sourceId, $ownerId, $validatedUrl);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function positiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (!is_string($value) || preg_match('/^[0-9]+$/D', $value) !== 1) {
            return null;
        }

        $normalized = ltrim($value, '0');
        if ($normalized === '') {
            return null;
        }

        $max = (string) PHP_INT_MAX;
        if (strlen($normalized) > strlen($max)
            || (strlen($normalized) === strlen($max) && strcmp($normalized, $max) > 0)) {
            return null;
        }

        $result = (int) $normalized;
        return $result > 0 ? $result : null;
    }
}
