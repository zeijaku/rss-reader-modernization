<?php
/*
 * *************************
 * iGuguru Common Unit
 * Version 1.0.0
 * *************************
 */

    /** Return UI defaults without storing mutable settings in the session. */
    function default_ui_config(): array
    {
        return [
            'conf_style' => 'bootstrap',
            'conf_style_nav' => 'dark',
            'conf_style_tabname1' => 'Base',
            'conf_style_tabname2' => 'Maint',
            'conf_style_tabname3' => 'IT',
            'conf_style_tabname4' => 'Observe',
            'conf_style_navlink_icon1' => 'map-marker-alt',
            'conf_style_navlink1' => 'https://map.google.com/',
            'conf_style_navlink_view1' => 'Map',
            'conf_style_navlink_icon2' => 'mail-bulk',
            'conf_style_navlink2' => 'https://mail.google.com/',
            'conf_style_navlink_view2' => 'Mail',
            'conf_style_navlink_icon3' => 'search',
            'conf_style_navlink3' => 'https://www.google.com/',
            'conf_style_navlink_view3' => 'Search',
            'conf_style_navlink_icon4' => 'images',
            'conf_style_navlink4' => 'https://www.google.com/imghp',
            'conf_style_navlink_view4' => 'Image',
        ];
    }

    /** Load the current settings from DB on each authenticated request. */
    function user_ui_config(int $userId): array
    {
        $defaults = default_ui_config();
        $rows = search_conf($userId);
        if (!isset($rows[0]) || !is_array($rows[0])) {
            return $defaults;
        }

        foreach ($defaults as $key => $defaultValue) {
            $value = $rows[0][$key] ?? null;
            if ($value !== null && $value !== '') {
                $defaults[$key] = (string) $value;
            }
        }

        return app_safe_ui_config($defaults);
    }



    /**
     * Resolve a user-selected theme to a known local stylesheet.
     * Never interpolate an arbitrary session value into a filesystem/web path.
     */
    function resolve_theme_stylesheet(?string $style): string
    {
        $themes = [
            'bootstrap' => 'bootstrap.min.css',
            'bootstrap-yeti' => 'bootstrap-yeti.min.css',
            'bootstrap-minty' => 'bootstrap-minty.min.css',
            'bootstrap-flatly' => 'bootstrap-flatly.min.css',
            'bootstrap-journal' => 'bootstrap-journal.min.css',
            'bootstrap-sketchy' => 'bootstrap-sketchy.min.css',
            'bootstrap-solar' => 'bootstrap-solar.min.css',
            'bootstrap-slate' => 'bootstrap-slate.min.css',
        ];

        return $themes[$style ?? ''] ?? $themes['bootstrap'];
    }


    /*
    * ********************
    * コンテンツ取得
    * ********************
    */

    function steal_contents($url) {
        if (!is_string($url)) {
            return false;
        }
        $result = app_safe_http_fetch($url);
        return ($result['ok'] ?? false) === true ? (string) $result['body'] : false;
    }

    /*
     * ********************
     * RSS/Atom response hint
     * ********************
     *
     * Kept only as a lightweight compatibility helper.  SB-11 no longer uses
     * this detector to decide whether an upstream response is a successful
     * "text" feed; api_feed_fetch() always runs the XML parser and rejects
     * unsupported/malformed responses as structured errors.
     */
    function rss_check_string(mixed $element): string
    {
        if (!is_string($element) || trim($element) === '') {
            return 'invalid';
        }

        return preg_match('/<\s*(?:rss\b|feed\b|(?:[A-Za-z0-9_.-]+:)?RDF\b)/i', $element) === 1
            ? 'rss'
            : 'text';
    }

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
     * The parser deliberately keeps this policy independent from SimpleXML so
     * it can be regression-tested even on minimal PHP runtimes. Atom feeds may
     * expose multiple links (self/alternate/enclosure), while some Legacy feeds
     * put the URL in the element text instead of an href attribute.
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
            // text/html alternate is the strongest signal, followed by a
            // generic alternate and then a relation-less RSS/Legacy link.
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

    /*
    * ********************
    * RSS / Atom parser
    * ********************
    * ['channel']['title']
    * ['channel']['link']
    * ['channel']['description']
    * ['item'][]['description']
    * ['item'][]['title']
    * ['item'][]['link']
    * ['item'][]['date']
    */
    class rss_parse
    {
        public ?string $last_error = null;

        /** @return array<string,mixed> */
        public function parse_start(mixed $contents): array
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
                    // Atom normally uses a default namespace.  SimpleXML needs
                    // an explicit namespace view before entry/title/link access.
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
                    // RSS 1.0 typically has rdf:RDF as its root while channel
                    // and item live in the default RSS namespace.
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

                // Zero-item feeds are valid. The browser renderer already
                // bounds iteration to the number of returned items.
                foreach ($items as $item) {
                    $feed['item'][] = [
                        'title' => $this->feed_title($item),
                        'link' => $this->feed_link($item),
                        'description' => $this->feed_description($item),
                        'content' => $this->feed_content($item),
                        'date' => $this->feed_date($item),
                    ];
                }

                return $feed;
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previousUseErrors);
            }
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

            // The byte stream is UTF-8 now. Keep the XML declaration aligned;
            // otherwise SimpleXML can reinterpret converted bytes using the old
            // declared encoding (common in older Japanese feeds).
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

            /*
             * SB-12 R2: use namespace-agnostic XPath for direct <link> children.
             * The R1 path depended on a SimpleXML namespace view and array-style
             * attribute access at the same time. Real Atom feeds such as Qiita
             * and Publickey expose entry URLs as href attributes, so losing that
             * attribute left the frontend with an empty item.link. local-name()
             * keeps the extraction stable for default/prefixed Atom namespaces.
             */
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

                    // RSS 2.0 and a few Atom/Legacy feeds use <link>URL</link>.
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

            // Compatibility fallback used by Qiita-style Atom documents that
            // may also expose the browser URL in a dedicated <url> element.
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
    /* 
    * アクセスログ [テキスト出力]
    */
    function access_log(){
        if (!defined('APP_LOG_ENABLED') || APP_LOG_ENABLED !== true) {
            return;
        }

        $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '-';
        $method = $_SERVER['REQUEST_METHOD'] ?? '-';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '-';
        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? '-';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '-';
        $referer = $_SERVER['HTTP_REFERER'] ?? '-';
        $date = new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo'));

        $record = sprintf(
            "%s - [%s] \"%s %s %s\" \"%s\" \"%s\"%s",
            str_replace(["\r", "\n"], '', $remoteAddress),
            $date->format('d/M/Y:H:i:s O'),
            str_replace(["\r", "\n"], '', $method),
            str_replace(["\r", "\n"], '', $requestUri),
            str_replace(["\r", "\n"], '', $protocol),
            str_replace(["\r", "\n"], '', $referer),
            str_replace(["\r", "\n"], '', $userAgent),
            PHP_EOL
        );

        $logPath = defined('APP_LOG_PATH') ? APP_LOG_PATH : dirname(__DIR__, 2) . '/var/log/app.log';
        $logDirectory = dirname($logPath);
        if (!is_dir($logDirectory) || !is_writable($logDirectory)) {
            error_log('Application access log path is not writable.');
            return;
        }

        file_put_contents($logPath, $record, FILE_APPEND | LOCK_EX);
    }
/* End of common_func.php */