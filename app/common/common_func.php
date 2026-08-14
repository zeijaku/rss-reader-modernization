<?php
/*
 * *************************
 * iGuguru Common Unit
 * Version 1.0.0
 * *************************
 */

require_once dirname(__DIR__) . '/feed/feed_parser.php';

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
            'bootstrap' => 'bootstrap-5.3.8.min.css',
            'bootstrap-yeti' => 'bootstrap-yeti-5.3.8.min.css',
            'bootstrap-minty' => 'bootstrap-minty-5.3.8.min.css',
            'bootstrap-flatly' => 'bootstrap-flatly-5.3.8.min.css',
            'bootstrap-journal' => 'bootstrap-journal-5.3.8.min.css',
            'bootstrap-sketchy' => 'bootstrap-sketchy-5.3.8.min.css',
            'bootstrap-solar' => 'bootstrap-solar-5.3.8.min.css',
            'bootstrap-slate' => 'bootstrap-slate-5.3.8.min.css',
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

    /*
     * RSS / Atom parsing moved to app/feed/feed_parser.php in M1-A.
     * rss_parse and the existing helper function names remain available there
     * as compatibility boundaries while callers migrate incrementally.
     */

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