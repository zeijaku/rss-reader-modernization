<?php

declare(strict_types=1);

require_once __DIR__ . '/item_identity.php';
require_once __DIR__ . '/normalized_item.php';
require_once dirname(__DIR__) . '/url_normalizer.php';

/** Build deterministic item identities without I/O or persistent state. */
final class ItemIdentityResolver
{
    /**
     * Resolve and attach an identity using a validated configured Feed URL as
     * the scope. content_id and owner_id are intentionally excluded so the
     * same Feed registered more than once yields the same item identities.
     */
    public function resolve(NormalizedItem $item, string $sourceUrl): NormalizedItem
    {
        if ($sourceUrl === '' || trim($sourceUrl) !== $sourceUrl) {
            throw new InvalidArgumentException('Item identity source URL must be a non-empty validated URL.');
        }

        [$basis, $candidate] = $this->selectCandidate($item);
        $payload = json_encode(
            ['m1-item-identity-v1', $sourceUrl, $basis, $candidate],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );

        $identity = new ItemIdentity(
            'm1i:v1:' . hash('sha256', $payload),
            $basis
        );

        return $item->withIdentity($identity);
    }

    /** @return array{0:string,1:string} */
    private function selectCandidate(NormalizedItem $item): array
    {
        $sourceItemId = self::normalizeScalar($item->sourceItemId);
        if ($sourceItemId !== null) {
            return [
                ItemIdentity::BASIS_SOURCE_ID,
                app_remove_tracking_parameters($sourceItemId),
            ];
        }

        $link = self::normalizeScalar($item->link);
        if ($link !== null) {
            return [
                ItemIdentity::BASIS_LINK,
                app_remove_tracking_parameters($link),
            ];
        }

        $fingerprintPayload = json_encode(
            [
                'm1-item-fingerprint-v1',
                self::normalizeFingerprintPart($item->title),
                self::normalizeFingerprintPart($item->date),
                self::normalizeFingerprintPart($item->description),
                self::normalizeFingerprintPart($item->content),
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );

        return [ItemIdentity::BASIS_FINGERPRINT, hash('sha256', $fingerprintPayload)];
    }

    private static function normalizeScalar(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $value));
        return $normalized === '' ? null : $normalized;
    }

    private static function normalizeFingerprintPart(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim(str_replace(["\r\n", "\r"], "\n", $value));
    }
}
