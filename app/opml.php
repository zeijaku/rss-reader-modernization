<?php

declare(strict_types=1);

const OPML_MAX_IMPORT_BYTES = 524288;
const OPML_MAX_FEEDS = 500;
const OPML_MAX_DEPTH = 16;
const OPML_MAX_FAILURE_DETAILS = 20;
const OPML_MAX_WARNING_DETAILS = 20;

function opml_limited_text(mixed $value, int $maxLength): string
{
    if (!is_string($value) || !app_is_valid_utf8($value)) {
        return '';
    }
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    if ($value === '' || app_has_control_characters($value)) {
        return '';
    }
    if (app_text_length($value) <= $maxLength) {
        return $value;
    }
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }
    if (function_exists('iconv_substr')) {
        $short = iconv_substr($value, 0, $maxLength, 'UTF-8');
        return is_string($short) ? $short : '';
    }
    return substr($value, 0, $maxLength);
}

/** @param list<string> $segments */
function opml_category_path(array $segments): string
{
    $clean = [];
    foreach ($segments as $segment) {
        $value = opml_limited_text($segment, FEED_METADATA_TITLE_MAX_LENGTH);
        if ($value !== '') {
            $clean[] = $value;
        }
    }
    if ($clean === []) {
        return '';
    }

    $path = implode(' / ', $clean);
    return opml_limited_text($path, FEED_METADATA_CATEGORY_MAX_LENGTH);
}

/** @return list<string> */
function opml_category_attribute_segments(string $value): array
{
    $segments = [];
    foreach (preg_split('/\s*,\s*/u', $value) ?: [] as $category) {
        $category = trim((string) $category, " \t\n\r\0\x0B/");
        if ($category === '') {
            continue;
        }
        foreach (preg_split('#\s*/\s*#u', $category) ?: [] as $part) {
            $part = opml_limited_text((string) $part, FEED_METADATA_TITLE_MAX_LENGTH);
            if ($part !== '') {
                $segments[] = $part;
            }
        }
    }
    return $segments;
}

/**
 * @return array{feeds:list<array{title:string,feed_url:string,site_url:string,category_path:string}>,failure_count:int,failures:list<array{title:string,url:string,reason:string}>,warning_count:int,warnings:list<array{title:string,url:string,reason:string}>}
 */
function opml_parse(string $xml): array
{
    if ($xml === '' || strlen($xml) > OPML_MAX_IMPORT_BYTES) {
        throw new InvalidArgumentException('OPML file size is invalid.');
    }
    if (!app_is_valid_utf8($xml) || app_has_control_characters($xml)) {
        throw new InvalidArgumentException('OPML must be valid UTF-8 XML.');
    }
    if (preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', $xml) === 1) {
        throw new InvalidArgumentException('DOCTYPE and ENTITY are not allowed in OPML.');
    }
    if (!function_exists('simplexml_load_string')) {
        throw new RuntimeException('SimpleXML is required for OPML import.');
    }

    $previous = libxml_use_internal_errors(true);
    libxml_clear_errors();
    try {
        $root = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT);
        if (!$root instanceof SimpleXMLElement || strtolower($root->getName()) !== 'opml') {
            throw new InvalidArgumentException('Invalid OPML document.');
        }
        $bodyNodes = $root->xpath('./*[local-name()="body"]');
        if (!is_array($bodyNodes) || !isset($bodyNodes[0]) || !$bodyNodes[0] instanceof SimpleXMLElement) {
            throw new InvalidArgumentException('OPML body is missing.');
        }

        $feeds = [];
        $failures = [];
        $warnings = [];
        $failureCount = 0;
        $warningCount = 0;
        $seenFeedOutlines = 0;

        $walk = static function (SimpleXMLElement $parent, array $parents, int $depth) use (&$walk, &$feeds, &$failures, &$warnings, &$failureCount, &$warningCount, &$seenFeedOutlines): void {
            if ($depth > OPML_MAX_DEPTH) {
                throw new InvalidArgumentException('OPML category depth is too large.');
            }

            $outlineNodes = $parent->xpath('./*[local-name()="outline"]');
            if (!is_array($outlineNodes)) {
                return;
            }
            foreach ($outlineNodes as $outline) {
                if (!$outline instanceof SimpleXMLElement) {
                    continue;
                }
                $attributes = $outline->attributes();
                $text = $attributes instanceof SimpleXMLElement ? (string) ($attributes['text'] ?? '') : '';
                $titleAttribute = $attributes instanceof SimpleXMLElement ? (string) ($attributes['title'] ?? '') : '';
                $label = opml_limited_text($text !== '' ? $text : $titleAttribute, FEED_METADATA_TITLE_MAX_LENGTH);
                $xmlUrlRaw = $attributes instanceof SimpleXMLElement ? trim((string) ($attributes['xmlUrl'] ?? '')) : '';
                $htmlUrlRaw = $attributes instanceof SimpleXMLElement ? trim((string) ($attributes['htmlUrl'] ?? '')) : '';
                $categoryRaw = $attributes instanceof SimpleXMLElement ? trim((string) ($attributes['category'] ?? '')) : '';

                if ($xmlUrlRaw !== '') {
                    $seenFeedOutlines++;
                    if ($seenFeedOutlines > OPML_MAX_FEEDS) {
                        throw new InvalidArgumentException('OPML feed count exceeds the limit.');
                    }
                    $feedUrl = app_validate_feed_url($xmlUrlRaw);
                    if ($feedUrl === null) {
                        $failureCount++;
                        if (count($failures) < OPML_MAX_FAILURE_DETAILS) {
                            $failures[] = ['title' => $label, 'url' => $xmlUrlRaw, 'reason' => 'invalid_feed_url'];
                        }
                        continue;
                    }

                    $siteUrl = '';
                    if ($htmlUrlRaw !== '') {
                        $siteUrl = app_validate_external_link($htmlUrlRaw, FEED_METADATA_SITE_URL_MAX_LENGTH) ?? '';
                        if ($siteUrl === '') {
                            $warningCount++;
                            if (count($warnings) < OPML_MAX_WARNING_DETAILS) {
                                $warnings[] = ['title' => $label, 'url' => $feedUrl, 'reason' => 'site_url_ignored'];
                            }
                        }
                    }
                    $categorySegments = array_merge($parents, opml_category_attribute_segments($categoryRaw));
                    $feeds[] = [
                        'title' => $label,
                        'feed_url' => $feedUrl,
                        'site_url' => $siteUrl,
                        'category_path' => opml_category_path($categorySegments),
                    ];
                    continue;
                }

                $nextParents = $parents;
                if ($label !== '') {
                    $nextParents[] = $label;
                }
                if ($categoryRaw !== '') {
                    $nextParents = array_merge($nextParents, opml_category_attribute_segments($categoryRaw));
                }
                $walk($outline, $nextParents, $depth + 1);
            }
        };

        $walk($bodyNodes[0], [], 1);
        return ['feeds' => $feeds, 'failure_count' => $failureCount, 'failures' => $failures, 'warning_count' => $warningCount, 'warnings' => $warnings];
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

function opml_xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
}

/** @param list<array<string,mixed>> $feeds */
function opml_build_export(array $feeds): string
{
    $tree = ['feeds' => [], 'children' => []];
    foreach ($feeds as $feed) {
        $url = app_validate_feed_url($feed['feed_url'] ?? null);
        if ($url === null) {
            continue;
        }
        $title = opml_limited_text((string) ($feed['feed_title'] ?? ''), FEED_METADATA_TITLE_MAX_LENGTH);
        if ($title === '') {
            $title = $url;
        }
        $siteUrl = app_validate_external_link($feed['site_url'] ?? '', FEED_METADATA_SITE_URL_MAX_LENGTH) ?? '';
        $categoryPath = app_validate_text($feed['category_path'] ?? '', FEED_METADATA_CATEGORY_MAX_LENGTH, true) ?? '';
        $segments = $categoryPath === '' ? [] : preg_split('/\s+\/\s+/u', $categoryPath);
        $node =& $tree;
        foreach (is_array($segments) ? $segments : [] as $segment) {
            $segment = opml_limited_text((string) $segment, FEED_METADATA_TITLE_MAX_LENGTH);
            if ($segment === '') {
                continue;
            }
            if (!isset($node['children'][$segment])) {
                $node['children'][$segment] = ['feeds' => [], 'children' => []];
            }
            $node =& $node['children'][$segment];
        }
        $node['feeds'][] = ['title' => $title, 'feed_url' => $url, 'site_url' => $siteUrl];
        unset($node);
    }

    $render = static function (array $node, int $depth) use (&$render): string {
        $indent = str_repeat('  ', $depth);
        $xml = '';
        foreach ($node['children'] as $name => $child) {
            $xml .= $indent . '<outline text="' . opml_xml_escape((string) $name) . '" title="' . opml_xml_escape((string) $name) . '">\n';
            $xml .= $render($child, $depth + 1);
            $xml .= $indent . "</outline>\n";
        }
        foreach ($node['feeds'] as $feed) {
            $xml .= $indent . '<outline type="rss" text="' . opml_xml_escape($feed['title']) . '" title="' . opml_xml_escape($feed['title']) . '" xmlUrl="' . opml_xml_escape($feed['feed_url']) . '"';
            if ($feed['site_url'] !== '') {
                $xml .= ' htmlUrl="' . opml_xml_escape($feed['site_url']) . '"';
            }
            $xml .= " />\n";
        }
        return $xml;
    };

    return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
        . "<opml version=\"2.0\">\n"
        . "  <head><title>iGuguru RSS Export</title></head>\n"
        . "  <body>\n"
        . $render($tree, 2)
        . "  </body>\n"
        . "</opml>\n";
}
