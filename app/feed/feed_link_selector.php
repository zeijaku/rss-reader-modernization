<?php

declare(strict_types=1);

/** Select the most useful browser-facing link from RSS/Atom candidates. */
final class FeedLinkSelector
{
    /**
     * @param list<array{href:mixed,rel?:mixed,type?:mixed}> $candidates
     */
    public static function select(array $candidates): ?string
    {
        $bestHref = null;
        $bestRank = PHP_INT_MAX;

        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $href = $candidate['href'] ?? null;
            if (!is_string($href)) {
                continue;
            }
            $href = trim($href);
            if ($href === '') {
                continue;
            }

            $rel = isset($candidate['rel']) && is_string($candidate['rel'])
                ? strtolower(trim($candidate['rel']))
                : '';
            $type = isset($candidate['type']) && is_string($candidate['type'])
                ? strtolower(trim($candidate['type']))
                : '';

            // Browser-facing Atom links should win over feed/self metadata.
            $rank = match (true) {
                $rel === 'alternate' && ($type === '' || $type === 'text/html') => 0,
                $rel === 'alternate' => 1,
                $rel === '' => 2,
                $rel === 'related' => 3,
                $rel === 'self' => 4,
                default => 5,
            };

            if ($rank < $bestRank) {
                $bestRank = $rank;
                $bestHref = $href;
                if ($rank === 0) {
                    break;
                }
            }
        }

        return $bestHref;
    }
}

/** Compatibility wrapper retained for Secure Baseline call sites/tests. */
function rss_select_link_candidate(array $candidates): ?string
{
    return FeedLinkSelector::select($candidates);
}
