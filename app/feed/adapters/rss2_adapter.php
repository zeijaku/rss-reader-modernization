<?php

declare(strict_types=1);

require_once __DIR__ . '/feed_adapter_interface.php';
require_once dirname(__DIR__) . '/feed_xml_helper.php';
require_once dirname(__DIR__) . '/normalized_item.php';

/** RSS 2.0 and compatible <rss><channel> document adapter. */
final class Rss2Adapter implements FeedAdapterInterface
{
    private const DESCRIPTION_FIELDS = ['summary', 'subtitle', 'tagline', 'description'];
    private const DATE_FIELDS = ['created', 'updated', 'modified', 'issued', 'pubDate', 'lastBuildDate'];

    public function supports(SimpleXMLElement $xml): bool
    {
        if (strtolower($xml->getName()) !== 'rss') {
            return false;
        }

        $root = FeedXmlHelper::defaultNamespaceChildren($xml);
        return isset($root->channel);
    }

    public function parse(SimpleXMLElement $xml): array
    {
        $root = FeedXmlHelper::defaultNamespaceChildren($xml);
        $channel = $root->channel;
        $items = [];

        foreach ($channel->item as $item) {
            $items[] = new NormalizedItem(
                FeedXmlHelper::title($item),
                FeedXmlHelper::link($item),
                FeedXmlHelper::description($item, self::DESCRIPTION_FIELDS),
                FeedXmlHelper::content($item),
                FeedXmlHelper::date($item, self::DATE_FIELDS),
                FeedXmlHelper::firstText($item, ['guid'])
            );
        }

        return [
            'type' => 'rss2',
            'channel' => [
                'title' => FeedXmlHelper::title($channel),
                'link' => FeedXmlHelper::link($channel),
                'description' => FeedXmlHelper::description($channel, self::DESCRIPTION_FIELDS),
            ],
            'item' => $items,
        ];
    }
}
