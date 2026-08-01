<?php

declare(strict_types=1);

require_once __DIR__ . '/normalized_item.php';

/** Parse a feed date without passing null/false into PHP date functions. */
function rss_normalize_date(mixed $value): ?string
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

/**
 * Select the most useful browser-facing link from RSS/Atom candidates.
 *
 * @param list<array{href:mixed,rel?:mixed,type?:mixed}> $candidates
 */
function rss_select_link_candidate(array $candidates): ?string
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

/**
 * RSS 2.0 / RSS 1.0 / Atom parser boundary.
 *
 * Parsing now produces NormalizedItem objects internally. parse_start()
 * remains as a compatibility adapter for the SB-15 array contract while M1 is
 * migrated incrementally.
 */
class FeedParser
{
    public ?string $last_error = null;

    /**
     * Parse into the M1 normalized item model.
     *
     * @return array{type:string,channel:array{title:string,link:?string,description:?string},item:list<NormalizedItem>}|array{}
     */
    public function parse_normalized(mixed $contents): array
    {
        $this->last_error = null;
        if (!is_string($contents) || trim($contents) === '') {
            $this->last_error = 'Feed body is empty.';
            return [];
        }

        $contents = $this->normalize_encoding($contents);
        if ($contents === null) {
            return [];
        }

        // XML 1.0 disallows these control characters. Remove them without
        // relying on Legacy global mbstring runtime settings.
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $contents);
        if (!is_string($cleaned)) {
            $this->last_error = 'Feed body could not be normalized.';
            return [];
        }
        $contents = $cleaned;

        if (!function_exists('simplexml_load_string')) {
            $this->last_error = 'SimpleXML extension is unavailable.';
            return [];
        }

        $previousUseErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $xml = simplexml_load_string($contents, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
            if ($xml === false) {
                $errors = libxml_get_errors();
                $firstError = $errors[0] ?? null;
                $this->last_error = $firstError instanceof LibXMLError
                    ? trim($firstError->message)
                    : 'XML could not be parsed.';
                return [];
            }

            $rootName = strtolower($xml->getName());
            $type = null;
            $channel = null;
            $items = [];
            $rootChildren = $this->default_namespace_children($xml);

            if ($rootName === 'feed') {
                $type = 'atom';
                $channel = $xml;
                foreach ($rootChildren->entry as $item) {
                    $items[] = $item;
                }
            } elseif ($rootName === 'rss' && isset($rootChildren->channel)) {
                $type = 'rss2';
                $channel = $rootChildren->channel;
                foreach ($rootChildren->channel->item as $item) {
                    $items[] = $item;
                }
            } elseif ($rootName === 'rdf') {
                $rssChildren = $rootChildren;
                if (!isset($rssChildren->channel)) {
                    $rssChildren = $xml->children('http://purl.org/rss/1.0/');
                }
                if (!isset($rssChildren->channel)) {
                    $this->last_error = 'RSS 1.0 channel is missing.';
                    return [];
                }
                $type = 'rss1';
                $channel = $rssChildren->channel;
                foreach ($rssChildren->item as $item) {
                    $items[] = $item;
                }
            } else {
                $this->last_error = 'Unsupported XML feed format.';
                return [];
            }

            $feed = [
                'type' => $type,
                'channel' => [
                    'title' => $this->feed_title($channel),
                    'link' => $this->feed_link($channel),
                    'description' => $this->feed_description($channel),
                ],
                'item' => [],
            ];

            // Zero-item feeds are valid. The browser renderer already bounds
            // iteration to the number of returned items.
            foreach ($items as $item) {
                $feed['item'][] = new NormalizedItem(
                    $this->feed_title($item),
                    $this->feed_link($item),
                    $this->feed_description($item),
                    $this->feed_content($item),
                    $this->feed_date($item)
                );
            }

            return $feed;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseErrors);
        }
    }

    /**
     * Compatibility adapter for the Secure Baseline parser result shape.
     *
     * @return array<string,mixed>
     */
    public function parse_start(mixed $contents): array
    {
        $feed = $this->parse_normalized($contents);
        if ($feed === []) {
            return [];
        }

        $items = [];
        foreach ($feed['item'] as $item) {
            if ($item instanceof NormalizedItem) {
                $items[] = $item->toArray();
            }
        }
        $feed['item'] = $items;
        return $feed;
    }

    private function normalize_encoding(string $contents): ?string
    {
        if (!function_exists('mb_detect_encoding') || !function_exists('mb_convert_encoding')) {
            $this->last_error = 'mbstring extension is unavailable.';
            return null;
        }

        $utf8 = $contents;
        if (!function_exists('mb_check_encoding') || !mb_check_encoding($contents, 'UTF-8')) {
            $targetEncoding = mb_detect_encoding(
                $contents,
                ['UTF-8', 'SJIS-win', 'EUC-JP', 'JIS', 'UTF-16', 'UTF-16BE', 'UTF-16LE', 'Windows-1252', 'ISO-8859-1', 'ASCII'],
                true
            );
            if (!is_string($targetEncoding) || $targetEncoding === '') {
                $this->last_error = 'Feed character encoding could not be detected.';
                return null;
            }

            try {
                $utf8 = mb_convert_encoding($contents, 'UTF-8', $targetEncoding);
            } catch (Throwable) {
                $this->last_error = 'Feed character encoding conversion failed.';
                return null;
            }
        }

        $normalized = preg_replace(
            '/(<\?xml\s+[^>]*encoding\s*=\s*["\'])[^"\']+(["\'])/i',
            '$1UTF-8$2',
            $utf8,
            1
        );
        if (!is_string($normalized)) {
            $this->last_error = 'Feed XML declaration could not be normalized.';
            return null;
        }
        return $normalized;
    }

    /** Return child elements in the document's default namespace, if any. */
    private function default_namespace_children(SimpleXMLElement $xml): SimpleXMLElement
    {
        $namespaces = $xml->getDocNamespaces(true);
        $defaultNamespace = is_array($namespaces) ? ($namespaces[''] ?? '') : '';
        return is_string($defaultNamespace) && $defaultNamespace !== ''
            ? $xml->children($defaultNamespace)
            : $xml;
    }

    public function feed_title(SimpleXMLElement $xml): string
    {
        $view = $this->default_namespace_children($xml);
        return isset($view->title) ? (string) $view->title : '';
    }

    public function feed_link(SimpleXMLElement $xml): ?string
    {
        $candidates = [];

        $links = $xml->xpath('./*[local-name()="link"]');
        if (is_array($links)) {
            foreach ($links as $link) {
                if (!$link instanceof SimpleXMLElement) {
                    continue;
                }

                $attributes = $link->attributes();
                $href = '';
                $rel = '';
                $type = '';
                if ($attributes instanceof SimpleXMLElement) {
                    $href = isset($attributes['href']) ? trim((string) $attributes['href']) : '';
                    $rel = isset($attributes['rel']) ? trim((string) $attributes['rel']) : '';
                    $type = isset($attributes['type']) ? trim((string) $attributes['type']) : '';
                }

                if ($href === '') {
                    $href = trim((string) $link);
                }

                if ($href !== '') {
                    $candidates[] = [
                        'href' => $href,
                        'rel' => $rel,
                        'type' => $type,
                    ];
                }
            }
        }

        $selected = rss_select_link_candidate($candidates);
        if ($selected !== null) {
            return $selected;
        }

        $urls = $xml->xpath('./*[local-name()="url"]');
        if (is_array($urls)) {
            foreach ($urls as $url) {
                if (!$url instanceof SimpleXMLElement) {
                    continue;
                }
                $value = trim((string) $url);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    public function feed_description(SimpleXMLElement $xml): ?string
    {
        $view = $this->default_namespace_children($xml);
        foreach (['summary', 'subtitle', 'tagline', 'description'] as $name) {
            if (isset($view->{$name})) {
                return (string) $view->{$name};
            }
        }
        return null;
    }

    public function feed_content(SimpleXMLElement $xml): ?string
    {
        $view = $this->default_namespace_children($xml);
        if (isset($view->content)) {
            return (string) $view->content;
        }

        $contentNamespace = $xml->children('http://purl.org/rss/1.0/modules/content/');
        if (isset($contentNamespace->encoded)) {
            return (string) $contentNamespace->encoded;
        }
        return null;
    }

    public function feed_date(SimpleXMLElement $xml): ?string
    {
        $view = $this->default_namespace_children($xml);
        foreach (['created', 'updated', 'modified', 'issued', 'pubDate', 'lastBuildDate'] as $name) {
            if (isset($view->{$name})) {
                $normalized = rss_normalize_date((string) $view->{$name});
                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        $dcNamespace = $xml->children('http://purl.org/dc/elements/1.1/');
        if (isset($dcNamespace->date)) {
            return rss_normalize_date((string) $dcNamespace->date);
        }

        return null;
    }
}

/**
 * Compatibility name retained for existing tests/integrations while M1 moves
 * call sites to the explicit FeedParser boundary.
 */
class rss_parse extends FeedParser
{
}
