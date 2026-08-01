<?php

declare(strict_types=1);

/**
 * Opaque, deterministic identity for one normalized feed item.
 *
 * The raw source ID, item URL, title, and content are never embedded in the
 * public value. The basis is retained internally so later cache/diff logic can
 * distinguish authoritative source IDs from weaker fallbacks.
 */
final class ItemIdentity
{
    public const BASIS_SOURCE_ID = 'source-id';
    public const BASIS_LINK = 'link';
    public const BASIS_FINGERPRINT = 'fingerprint';

    private const VALUE_PATTERN = '/\Am1i:v1:[a-f0-9]{64}\z/D';

    public function __construct(
        public readonly string $value,
        public readonly string $basis
    ) {
        if (!preg_match(self::VALUE_PATTERN, $value)) {
            throw new InvalidArgumentException('Item identity value is invalid.');
        }

        if (!in_array($basis, [
            self::BASIS_SOURCE_ID,
            self::BASIS_LINK,
            self::BASIS_FINGERPRINT,
        ], true)) {
            throw new InvalidArgumentException('Item identity basis is invalid.');
        }
    }
}
