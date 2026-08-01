<?php

declare(strict_types=1);

/**
 * Normalize dates emitted by RSS/Atom sources without leaking parser warnings.
 *
 * M1-C intentionally preserves the existing public representation:
 * Y-m-d H:i:s in the timezone encoded by the source value. No implicit UTC
 * conversion is performed here.
 */
final class FeedDateNormalizer
{
    public static function normalize(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }

        return $date->format('Y-m-d H:i:s');
    }
}

/** Compatibility wrapper retained for Secure Baseline call sites/tests. */
function rss_normalize_date(mixed $value): ?string
{
    return FeedDateNormalizer::normalize($value);
}
