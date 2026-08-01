<?php

declare(strict_types=1);

require_once __DIR__ . '/feed_date_normalizer.php';
require_once __DIR__ . '/feed_link_selector.php';

/**
 * Shared, format-neutral XML extraction helpers used by feed adapters.
 *
 * This class performs no HTTP access and does not decide which feed format is
 * being parsed. Format selection and field priority remain adapter concerns.
 */
final class FeedXmlHelper
{
    /** Return child elements in the document's default namespace, if any. */
    public static function defaultNamespaceChildren(SimpleXMLElement $xml): SimpleXMLElement
    {
        $namespaces = $xml->getDocNamespaces(true);
        $defaultNamespace = is_array($namespaces) ? ($namespaces[''] ?? '') : '';

        return is_string($defaultNamespace) && $defaultNamespace !== ''
            ? $xml->children($defaultNamespace)
            : $xml;
    }

    public static function title(SimpleXMLElement $xml): string
    {
        $view = self::defaultNamespaceChildren($xml);
        return isset($view->title) ? (string) $view->title : '';
    }

    public static function link(SimpleXMLElement $xml): ?string
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

        $selected = FeedLinkSelector::select($candidates);
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

    /**
     * @param list<string> $fieldNames ordered field preference
     */
    public static function description(SimpleXMLElement $xml, array $fieldNames): ?string
    {
        $view = self::defaultNamespaceChildren($xml);
        foreach ($fieldNames as $name) {
            if (isset($view->{$name})) {
                return (string) $view->{$name};
            }
        }

        return null;
    }

    public static function content(SimpleXMLElement $xml): ?string
    {
        $view = self::defaultNamespaceChildren($xml);
        if (isset($view->content)) {
            return (string) $view->content;
        }

        $contentNamespace = $xml->children('http://purl.org/rss/1.0/modules/content/');
        if (isset($contentNamespace->encoded)) {
            return (string) $contentNamespace->encoded;
        }

        return null;
    }

    /**
     * @param list<string> $fieldNames ordered date field preference
     */
    public static function date(SimpleXMLElement $xml, array $fieldNames, bool $allowDublinCore = true): ?string
    {
        $view = self::defaultNamespaceChildren($xml);
        foreach ($fieldNames as $name) {
            if (!isset($view->{$name})) {
                continue;
            }

            $normalized = FeedDateNormalizer::normalize((string) $view->{$name});
            if ($normalized !== null) {
                return $normalized;
            }
        }

        if ($allowDublinCore) {
            $dcNamespace = $xml->children('http://purl.org/dc/elements/1.1/');
            if (isset($dcNamespace->date)) {
                return FeedDateNormalizer::normalize((string) $dcNamespace->date);
            }
        }

        return null;
    }
}
