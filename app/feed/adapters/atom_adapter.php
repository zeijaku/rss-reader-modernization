<?php

declare(strict_types=1);

require_once __DIR__ . '/feed_adapter_interface.php';
require_once dirname(__DIR__) . '/feed_xml_helper.php';
require_once dirname(__DIR__) . '/normalized_item.php';

/** Atom 1.0 adapter. */
final class AtomAdapter implements FeedAdapterInterface
{
    private const DESCRIPTION_FIELDS = ['summary', 'subtitle', 'tagline', 'description'];

    // Preserve the Legacy order and add Atom's standard published fallback
    // immediately after updated. No timezone conversion is introduced.
    private const DATE_FIELDS = ['created', 'updated', 'published', 'modified', 'issued', 'pubDate', 'lastBuildDate'];

    public function supports(SimpleXMLElement $xml): bool
    {
        return strtolower($xml->getName()) === 'feed';
    }

    public function parse(SimpleXMLElement $xml): array
    {
        $feed = FeedXmlHelper::defaultNamespaceChildren($xml);
        $items = [];

        foreach ($feed->entry as $entry) {
            $items[] = new NormalizedItem(
                FeedXmlHelper::title($entry),
                FeedXmlHelper::link($entry),
                FeedXmlHelper::description($entry, self::DESCRIPTION_FIELDS),
                FeedXmlHelper::content($entry),
                FeedXmlHelper::date($entry, self::DATE_FIELDS)
            );
        }

        return [
            'type' => 'atom',
            'channel' => [
                'title' => FeedXmlHelper::title($xml),
                'link' => FeedXmlHelper::link($xml),
                'description' => FeedXmlHelper::description($xml, self::DESCRIPTION_FIELDS),
            ],
            'item' => $items,
        ];
    }
}
