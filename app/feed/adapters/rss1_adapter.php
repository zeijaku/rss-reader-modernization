<?php

declare(strict_types=1);

require_once __DIR__ . '/feed_adapter_interface.php';
require_once dirname(__DIR__) . '/feed_xml_helper.php';
require_once dirname(__DIR__) . '/normalized_item.php';

/** RDF-based RSS 1.0 adapter. */
final class Rss1Adapter implements FeedAdapterInterface
{
    private const RSS1_NAMESPACE = 'http://purl.org/rss/1.0/';
    private const RDF_NAMESPACE = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    private const DESCRIPTION_FIELDS = ['summary', 'subtitle', 'tagline', 'description'];
    private const DATE_FIELDS = ['created', 'updated', 'modified', 'issued', 'pubDate', 'lastBuildDate'];

    public function supports(SimpleXMLElement $xml): bool
    {
        return strtolower($xml->getName()) === 'rdf';
    }

    public function parse(SimpleXMLElement $xml): array
    {
        $rss = FeedXmlHelper::defaultNamespaceChildren($xml);
        if (!isset($rss->channel)) {
            $rss = $xml->children(self::RSS1_NAMESPACE);
        }
        if (!isset($rss->channel)) {
            throw new RuntimeException('RSS 1.0 channel is missing.');
        }

        $items = [];
        foreach ($rss->item as $item) {
            $items[] = new NormalizedItem(
                FeedXmlHelper::title($item),
                FeedXmlHelper::link($item),
                FeedXmlHelper::description($item, self::DESCRIPTION_FIELDS),
                FeedXmlHelper::content($item),
                FeedXmlHelper::date($item, self::DATE_FIELDS),
                FeedXmlHelper::attribute($item, 'about', self::RDF_NAMESPACE)
            );
        }

        return [
            'type' => 'rss1',
            'channel' => [
                'title' => FeedXmlHelper::title($rss->channel),
                'link' => FeedXmlHelper::link($rss->channel),
                'description' => FeedXmlHelper::description($rss->channel, self::DESCRIPTION_FIELDS),
            ],
            'item' => $items,
        ];
    }
}
