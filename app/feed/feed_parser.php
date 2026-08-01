<?php

declare(strict_types=1);

require_once __DIR__ . '/normalized_item.php';
require_once __DIR__ . '/item_identity_resolver.php';
require_once __DIR__ . '/feed_date_normalizer.php';
require_once __DIR__ . '/feed_link_selector.php';
require_once __DIR__ . '/feed_xml_helper.php';
require_once __DIR__ . '/adapters/feed_adapter_interface.php';
require_once __DIR__ . '/adapters/rss2_adapter.php';
require_once __DIR__ . '/adapters/rss1_adapter.php';
require_once __DIR__ . '/adapters/atom_adapter.php';

/**
 * XML feed parser and adapter dispatcher.
 *
 * M1-C keeps byte/encoding cleanup, secure XML loading, and format dispatch at
 * this boundary. RSS 2.0, RSS 1.0, and Atom field extraction now belongs to
 * dedicated adapters which all return the M1 NormalizedItem contract.
 */
class FeedParser
{
    public ?string $last_error = null;

    /** @var list<FeedAdapterInterface> */
    private array $adapters;
    private ItemIdentityResolver $identityResolver;

    /**
     * @param list<FeedAdapterInterface>|null $adapters
     */
    public function __construct(?array $adapters = null, ?ItemIdentityResolver $identityResolver = null)
    {
        $adapters ??= [
            new AtomAdapter(),
            new Rss2Adapter(),
            new Rss1Adapter(),
        ];

        foreach ($adapters as $adapter) {
            if (!$adapter instanceof FeedAdapterInterface) {
                throw new InvalidArgumentException('Feed adapters must implement FeedAdapterInterface.');
            }
        }

        $this->adapters = array_values($adapters);
        $this->identityResolver = $identityResolver ?? new ItemIdentityResolver();
    }

    /**
     * Parse into the M1 normalized item model.
     *
     * @return array{type:string,channel:array{title:string,link:?string,description:?string},item:list<NormalizedItem>}|array{}
     */
    public function parse_normalized(mixed $contents, ?string $sourceUrl = null): array
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

            foreach ($this->adapters as $adapter) {
                if (!$adapter->supports($xml)) {
                    continue;
                }

                try {
                    $feed = $adapter->parse($xml);
                    return $sourceUrl === null ? $feed : $this->attachIdentities($feed, $sourceUrl);
                } catch (RuntimeException $exception) {
                    $this->last_error = $exception->getMessage();
                    return [];
                }
            }

            $this->last_error = 'Unsupported XML feed format.';
            return [];
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
    public function parse_start(mixed $contents, ?string $sourceUrl = null): array
    {
        $feed = $this->parse_normalized($contents, $sourceUrl);
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

    /**
     * @param array{type:string,channel:array{title:string,link:?string,description:?string},item:list<NormalizedItem>} $feed
     * @return array{type:string,channel:array{title:string,link:?string,description:?string},item:list<NormalizedItem>}
     */
    private function attachIdentities(array $feed, string $sourceUrl): array
    {
        $items = [];
        foreach ($feed['item'] as $item) {
            $items[] = $this->identityResolver->resolve($item, $sourceUrl);
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

    // Compatibility helpers retained for integrations that called the
    // extracted M1-A parser methods directly. Adapters use FeedXmlHelper.
    public function feed_title(SimpleXMLElement $xml): string
    {
        return FeedXmlHelper::title($xml);
    }

    public function feed_link(SimpleXMLElement $xml): ?string
    {
        return FeedXmlHelper::link($xml);
    }

    public function feed_description(SimpleXMLElement $xml): ?string
    {
        return FeedXmlHelper::description($xml, ['summary', 'subtitle', 'tagline', 'description']);
    }

    public function feed_content(SimpleXMLElement $xml): ?string
    {
        return FeedXmlHelper::content($xml);
    }

    public function feed_date(SimpleXMLElement $xml): ?string
    {
        return FeedXmlHelper::date($xml, ['created', 'updated', 'modified', 'issued', 'pubDate', 'lastBuildDate']);
    }
}

/** Compatibility name retained while call sites use the explicit boundary. */
class rss_parse extends FeedParser
{
}
