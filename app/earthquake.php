<?php

declare(strict_types=1);

/**
 * V1.15-A Earthquake Widget.
 *
 * Source: Japan Meteorological Agency Disaster Prevention Information XML.
 * The Widget intentionally displays only values explicitly present in JMA XML;
 * tsunami status is never inferred from magnitude, depth, or location.
 */

const EARTHQUAKE_JMA_HOST = 'www.data.jma.go.jp';
const EARTHQUAKE_JMA_FEED_URL = 'https://www.data.jma.go.jp/developer/xml/feed/eqvol.xml';
const EARTHQUAKE_JMA_LONG_FEED_URL = 'https://www.data.jma.go.jp/developer/xml/feed/eqvol_l.xml';
const EARTHQUAKE_JMA_INFORMATION_URL = 'https://www.data.jma.go.jp/multi/quake/index.html?lang=jp';

if (!defined('APP_EARTHQUAKE_CACHE_TTL_SECONDS')) {
    define('APP_EARTHQUAKE_CACHE_TTL_SECONDS', 60);
}
if (!defined('APP_EARTHQUAKE_STALE_MAX_AGE_SECONDS')) {
    define('APP_EARTHQUAKE_STALE_MAX_AGE_SECONDS', 604800);
}
if (!defined('APP_EARTHQUAKE_LONG_FEED_CACHE_TTL_SECONDS')) {
    define('APP_EARTHQUAKE_LONG_FEED_CACHE_TTL_SECONDS', 3600);
}
if (!defined('APP_EARTHQUAKE_TIMEOUT_MS')) {
    define('APP_EARTHQUAKE_TIMEOUT_MS', 6000);
}
if (!defined('APP_EARTHQUAKE_CACHE_DIR')) {
    define('APP_EARTHQUAKE_CACHE_DIR', dirname(__DIR__) . '/var/cache/earthquake');
}

/** @return array{ok:bool,url:string,status:int,body:string,error_code:string} */
function earthquake_safe_fetch(
    string $url,
    ?callable $resolver = null,
    ?callable $transport = null,
    int $maxBytes = 1048576
): array {
    $parts = parse_url($url);
    if (!is_array($parts)
        || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
        || strtolower((string) ($parts['host'] ?? '')) !== EARTHQUAKE_JMA_HOST
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || isset($parts['query'])) {
        return ['ok' => false, 'url' => $url, 'status' => 0, 'body' => '', 'error_code' => 'invalid_target'];
    }

    $path = (string) ($parts['path'] ?? '');
    if ($path !== '/developer/xml/feed/eqvol.xml'
        && $path !== '/developer/xml/feed/eqvol_l.xml'
        && preg_match('#\A/developer/xml/data/[A-Za-z0-9_.-]+\.xml\z#D', $path) !== 1) {
        return ['ok' => false, 'url' => $url, 'status' => 0, 'body' => '', 'error_code' => 'path_not_allowed'];
    }

    $target = app_validate_fetch_target($url, $resolver);
    if (($target['ok'] ?? false) !== true) {
        return ['ok' => false, 'url' => $url, 'status' => 0, 'body' => '', 'error_code' => 'invalid_target'];
    }

    $maxBytes = max(65536, min(2097152, $maxBytes));
    $transportFn = $transport ?? 'app_curl_single_hop';
    $response = $transportFn([
        'url' => (string) ($target['url'] ?? $url),
        'host' => (string) $target['host'],
        'port' => (int) $target['port'],
        'ip' => (string) $target['ips'][0],
        'max_bytes' => $maxBytes,
        'connect_timeout_ms' => min(APP_HTTP_CONNECT_TIMEOUT_MS, APP_EARTHQUAKE_TIMEOUT_MS),
        'total_timeout_ms' => APP_EARTHQUAKE_TIMEOUT_MS,
        'user_agent' => APP_HTTP_USER_AGENT,
        'accept' => 'application/xml, text/xml;q=0.9, */*;q=0.1',
        'request_headers' => [],
    ]);

    if (($response['ok'] ?? false) !== true) {
        return [
            'ok' => false,
            'url' => $url,
            'status' => (int) ($response['status'] ?? 0),
            'body' => '',
            'error_code' => (string) ($response['error_code'] ?? 'transport_error'),
        ];
    }

    $status = (int) ($response['status'] ?? 0);
    if ($status < 200 || $status >= 300) {
        return ['ok' => false, 'url' => $url, 'status' => $status, 'body' => '', 'error_code' => 'http_status'];
    }

    $body = is_string($response['body'] ?? null) ? $response['body'] : '';
    if ($body === '' || strlen($body) > $maxBytes) {
        return ['ok' => false, 'url' => $url, 'status' => $status, 'body' => '', 'error_code' => 'invalid_body'];
    }

    return ['ok' => true, 'url' => $url, 'status' => $status, 'body' => $body, 'error_code' => ''];
}

function earthquake_xml_is_safe(string $xml): bool
{
    return $xml !== ''
        && strlen($xml) <= 2097152
        && stripos($xml, '<!DOCTYPE') === false
        && stripos($xml, '<!ENTITY') === false;
}

function earthquake_xml_decode_text(string $value): ?string
{
    $value = preg_replace('/<[^>]*>/s', '', $value);
    if (!is_string($value)) {
        return null;
    }
    $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8'));
    return $value === '' ? null : $value;
}

function earthquake_xml_section(string $xml, string $localName): ?string
{
    if (!earthquake_xml_is_safe($xml) || preg_match('/\A[A-Za-z_][A-Za-z0-9_.-]*\z/D', $localName) !== 1) {
        return null;
    }
    $name = preg_quote($localName, '/');
    $pattern = '/<(?:[A-Za-z_][A-Za-z0-9_.-]*:)?' . $name . '\b[^>]*>(.*?)<\/(?:[A-Za-z_][A-Za-z0-9_.-]*:)?' . $name . '\s*>/s';
    return preg_match($pattern, $xml, $matches) === 1 ? $matches[1] : null;
}

function earthquake_xml_tag_text(string $xml, string $localName): ?string
{
    $section = earthquake_xml_section($xml, $localName);
    return $section === null ? null : earthquake_xml_decode_text($section);
}

function earthquake_xml_tag_attribute(string $xml, string $localName, string $attribute): ?string
{
    if (!earthquake_xml_is_safe($xml)
        || preg_match('/\A[A-Za-z_][A-Za-z0-9_.-]*\z/D', $localName) !== 1
        || preg_match('/\A[A-Za-z_][A-Za-z0-9_.-]*\z/D', $attribute) !== 1) {
        return null;
    }
    $name = preg_quote($localName, '/');
    $attr = preg_quote($attribute, '/');
    if (preg_match('/<(?:[A-Za-z_][A-Za-z0-9_.-]*:)?' . $name . '\b([^>]*)>/s', $xml, $tag) !== 1) {
        return null;
    }
    $attributes = $tag[1];
    if (preg_match('/(?:^|\s)' . $attr . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/s', $attributes, $match) !== 1) {
        return null;
    }
    $raw = $match[1] !== '' ? $match[1] : $match[2];
    $value = trim(html_entity_decode($raw, ENT_QUOTES | ENT_XML1, 'UTF-8'));
    return $value === '' ? null : $value;
}

function earthquake_validate_jma_detail_url(mixed $value): ?string
{
    if (!is_string($value) || strlen($value) > 1024) {
        return null;
    }
    $parts = parse_url($value);
    if (!is_array($parts)
        || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
        || strtolower((string) ($parts['host'] ?? '')) !== EARTHQUAKE_JMA_HOST
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || isset($parts['query'])
        || !isset($parts['path'])
        || preg_match('#\A/developer/xml/data/[A-Za-z0-9_.-]+\.xml\z#D', (string) $parts['path']) !== 1) {
        return null;
    }
    return $value;
}

/**
 * @return array{url:string,title:string,updated_at:string|null}|null
 */
function earthquake_parse_feed(string $xml): ?array
{
    if (!earthquake_xml_is_safe($xml)) {
        return null;
    }

    if (preg_match_all('/<(?:[A-Za-z_][A-Za-z0-9_.-]*:)?entry\b[^>]*>(.*?)<\/(?:[A-Za-z_][A-Za-z0-9_.-]*:)?entry\s*>/s', $xml, $matches) === false) {
        return null;
    }

    $selected = null;
    $selectedTimestamp = PHP_INT_MIN;
    $selectedPriority = -1;
    foreach (($matches[1] ?? []) as $entryXml) {
        if (!is_string($entryXml)) {
            continue;
        }
        $title = earthquake_xml_tag_text($entryXml, 'title') ?? '';
        if ($title !== '震源・震度に関する情報' && $title !== '震源に関する情報') {
            continue;
        }
        $url = earthquake_validate_jma_detail_url(earthquake_xml_tag_attribute($entryXml, 'link', 'href'));
        if ($url === null) {
            continue;
        }
        $updatedAt = earthquake_xml_tag_text($entryXml, 'updated');
        $timestamp = is_string($updatedAt) ? strtotime($updatedAt) : false;
        $sortTimestamp = is_int($timestamp) ? $timestamp : PHP_INT_MIN + 1;
        $priority = $title === '震源・震度に関する情報' ? 2 : 1;
        if ($selected === null || $sortTimestamp > $selectedTimestamp || ($sortTimestamp === $selectedTimestamp && $priority > $selectedPriority)) {
            $selected = [
                'url' => $url,
                'title' => $title,
                'updated_at' => $updatedAt,
            ];
            $selectedTimestamp = $sortTimestamp;
            $selectedPriority = $priority;
        }
    }
    return $selected;
}

function earthquake_normalize_intensity(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    return match (trim($value)) {
        '1' => '震度1',
        '2' => '震度2',
        '3' => '震度3',
        '4' => '震度4',
        '5-' => '震度5弱',
        '5+' => '震度5強',
        '6-' => '震度6弱',
        '6+' => '震度6強',
        '7' => '震度7',
        default => null,
    };
}

function earthquake_ascii_digits(string $value): string
{
    return strtr($value, [
        '０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4',
        '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
        '．' => '.', '－' => '-', '＋' => '+',
    ]);
}

/** @return array{depth_km:int|null,depth_text:string|null} */
function earthquake_parse_depth(?string $description, ?string $coordinate): array
{
    if (is_string($description) && $description !== '') {
        $normalized = earthquake_ascii_digits($description);
        if (str_contains($normalized, 'ごく浅い')) {
            return ['depth_km' => null, 'depth_text' => 'ごく浅い'];
        }
        if (preg_match('/深さ\s*([0-9]{1,4})\s*(?:km|KM|Km|ｋｍ|キロ)/u', $normalized, $matches) === 1) {
            $depth = (int) $matches[1];
            if ($depth >= 0 && $depth <= 1000) {
                return ['depth_km' => $depth, 'depth_text' => $depth . 'km'];
            }
        }
    }

    if (is_string($coordinate) && preg_match('/([+-][0-9]+(?:\.[0-9]+)?)\/\z/D', trim($coordinate), $matches) === 1) {
        $meters = (float) $matches[1];
        if ($meters <= 0.0 && $meters >= -1000000.0) {
            $depth = (int) round(abs($meters) / 1000);
            return ['depth_km' => $depth, 'depth_text' => $depth . 'km'];
        }
    }

    return ['depth_km' => null, 'depth_text' => null];
}

function earthquake_normalize_datetime(?string $value): ?string
{
    if ($value === null || strlen($value) > 64) {
        return null;
    }
    try {
        $date = new DateTimeImmutable($value);
    } catch (Exception) {
        return null;
    }
    return $date->format(DateTimeInterface::ATOM);
}

/** @return array<string,mixed>|null */
function earthquake_parse_report(string $xml, ?string $sourceUrl = null): ?array
{
    if (!earthquake_xml_is_safe($xml)) {
        return null;
    }

    $control = earthquake_xml_section($xml, 'Control') ?? '';
    $controlTitle = earthquake_xml_tag_text($control, 'Title');
    if ($controlTitle !== null
        && $controlTitle !== '震源・震度に関する情報'
        && $controlTitle !== '震源に関する情報') {
        return null;
    }

    $body = earthquake_xml_section($xml, 'Body');
    $earthquakeSection = $body !== null ? earthquake_xml_section($body, 'Earthquake') : null;
    if ($earthquakeSection === null) {
        return null;
    }

    $originTime = earthquake_normalize_datetime(earthquake_xml_tag_text($earthquakeSection, 'OriginTime'));
    $hypocenterSection = earthquake_xml_section($earthquakeSection, 'Hypocenter');
    $areaSection = $hypocenterSection !== null ? earthquake_xml_section($hypocenterSection, 'Area') : null;
    $hypocenter = $areaSection !== null ? earthquake_xml_tag_text($areaSection, 'Name') : null;
    if ($originTime === null || $hypocenter === null || strlen($hypocenter) > 512) {
        return null;
    }

    $magnitudeRaw = earthquake_xml_tag_text($earthquakeSection, 'Magnitude');
    $magnitude = is_string($magnitudeRaw) && is_numeric($magnitudeRaw) ? (float) $magnitudeRaw : null;
    if ($magnitude !== null && ($magnitude < 0.0 || $magnitude > 10.0)) {
        $magnitude = null;
    }

    $coordinate = $areaSection !== null ? earthquake_xml_tag_text($areaSection, 'Coordinate') : null;
    $coordinateDescription = $areaSection !== null ? earthquake_xml_tag_attribute($areaSection, 'Coordinate', 'description') : null;
    $depth = earthquake_parse_depth($coordinateDescription, $coordinate);

    $intensitySection = $body !== null ? earthquake_xml_section($body, 'Intensity') : null;
    $observationSection = $intensitySection !== null ? earthquake_xml_section($intensitySection, 'Observation') : null;
    $maxIntensity = earthquake_normalize_intensity(
        $observationSection !== null ? earthquake_xml_tag_text($observationSection, 'MaxInt') : null
    );

    $commentsSection = $body !== null ? earthquake_xml_section($body, 'Comments') : null;
    $forecastSection = $commentsSection !== null ? earthquake_xml_section($commentsSection, 'ForecastComment') : null;
    $tsunami = $forecastSection !== null ? earthquake_xml_tag_text($forecastSection, 'Text') : null;
    if ($tsunami !== null && strlen($tsunami) > 1024) {
        $tsunami = null;
    }

    $head = earthquake_xml_section($xml, 'Head');
    $reportTime = $head !== null ? earthquake_normalize_datetime(earthquake_xml_tag_text($head, 'ReportDateTime')) : null;
    $headlineSection = $head !== null ? earthquake_xml_section($head, 'Headline') : null;
    $headline = $headlineSection !== null ? earthquake_xml_tag_text($headlineSection, 'Text') : null;
    if ($headline !== null && strlen($headline) > 2048) {
        $headline = null;
    }

    return [
        'occurred_at' => $originTime,
        'report_at' => $reportTime,
        'hypocenter' => $hypocenter,
        'max_intensity' => $maxIntensity,
        'magnitude' => $magnitude,
        'depth_km' => $depth['depth_km'],
        'depth_text' => $depth['depth_text'],
        'tsunami' => $tsunami,
        'headline' => $headline,
        'source_name' => '気象庁',
        'source_url' => earthquake_validate_jma_detail_url($sourceUrl),
        'information_url' => EARTHQUAKE_JMA_INFORMATION_URL,
        'updated_at' => gmdate('c'),
        'stale' => false,
    ];
}

function earthquake_cache_path(): string
{
    return rtrim((string) APP_EARTHQUAKE_CACHE_DIR, '/\\') . '/latest.json';
}

function earthquake_long_feed_cache_path(): string
{
    return rtrim((string) APP_EARTHQUAKE_CACHE_DIR, '/\\') . '/long-feed-entry.json';
}

/** @return array{url:string,title:string,updated_at:string|null}|null */
function earthquake_long_feed_cache_read(): ?array
{
    $path = earthquake_long_feed_cache_path();
    if (!is_file($path) || filesize($path) === false || filesize($path) > 8192) {
        return null;
    }
    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    try {
        $cache = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }
    if (!is_array($cache) || !is_int($cache['cached_at'] ?? null) || !is_array($cache['entry'] ?? null)) {
        return null;
    }
    $age = time() - $cache['cached_at'];
    if ($age < 0 || $age > APP_EARTHQUAKE_LONG_FEED_CACHE_TTL_SECONDS) {
        return null;
    }
    $entry = $cache['entry'];
    $url = earthquake_validate_jma_detail_url($entry['url'] ?? null);
    $title = is_string($entry['title'] ?? null) ? $entry['title'] : '';
    $updatedAt = is_string($entry['updated_at'] ?? null) ? $entry['updated_at'] : null;
    if ($url === null || ($title !== '震源・震度に関する情報' && $title !== '震源に関する情報')) {
        return null;
    }
    return ['url' => $url, 'title' => $title, 'updated_at' => $updatedAt];
}

/** @param array{url:string,title:string,updated_at:string|null} $entry */
function earthquake_long_feed_cache_write(array $entry): void
{
    $dir = (string) APP_EARTHQUAKE_CACHE_DIR;
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        return;
    }
    $url = earthquake_validate_jma_detail_url($entry['url'] ?? null);
    if ($url === null) {
        return;
    }
    $payload = json_encode(
        [
            'schema' => 1,
            'cached_at' => time(),
            'entry' => [
                'url' => $url,
                'title' => (string) ($entry['title'] ?? ''),
                'updated_at' => is_string($entry['updated_at'] ?? null) ? $entry['updated_at'] : null,
            ],
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if (!is_string($payload) || strlen($payload) > 8192) {
        return;
    }
    $tmp = tempnam($dir, '.earthquake-long-feed-');
    if (!is_string($tmp)) {
        return;
    }
    if (file_put_contents($tmp, $payload . "\n", LOCK_EX) !== false) {
        @chmod($tmp, 0640);
        @rename($tmp, earthquake_long_feed_cache_path());
    }
    if (is_file($tmp)) {
        @unlink($tmp);
    }
}

/** @return array<string,mixed>|null */
function earthquake_cache_read(bool $allowStale = false): ?array
{
    $path = earthquake_cache_path();
    if (!is_file($path) || filesize($path) === false || filesize($path) > 65536) {
        return null;
    }
    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    try {
        $cache = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }
    if (!is_array($cache) || !is_int($cache['cached_at'] ?? null) || !is_array($cache['earthquake'] ?? null)) {
        return null;
    }
    $age = time() - $cache['cached_at'];
    $maxAge = $allowStale ? APP_EARTHQUAKE_STALE_MAX_AGE_SECONDS : APP_EARTHQUAKE_CACHE_TTL_SECONDS;
    if ($age < 0 || $age > $maxAge) {
        return null;
    }
    return $cache['earthquake'];
}

/** @param array<string,mixed> $earthquake */
function earthquake_cache_write(array $earthquake): void
{
    $dir = (string) APP_EARTHQUAKE_CACHE_DIR;
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        return;
    }
    $payload = json_encode(
        ['schema' => 1, 'cached_at' => time(), 'earthquake' => $earthquake],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if (!is_string($payload) || strlen($payload) > 65536) {
        return;
    }
    $tmp = tempnam($dir, '.earthquake-');
    if (!is_string($tmp)) {
        return;
    }
    if (file_put_contents($tmp, $payload . "\n", LOCK_EX) !== false) {
        @chmod($tmp, 0640);
        @rename($tmp, earthquake_cache_path());
    }
    if (is_file($tmp)) {
        @unlink($tmp);
    }
}

/** @return array<string,mixed> */
function earthquake_latest(bool $force = false, ?callable $fetcher = null): array
{
    if (!$force) {
        $cached = earthquake_cache_read(false);
        if ($cached !== null) {
            return $cached;
        }
    }

    $fetch = $fetcher ?? static function (string $url, int $maxBytes): array {
        return earthquake_safe_fetch($url, null, null, $maxBytes);
    };

    $entry = null;
    $feedResponse = $fetch(EARTHQUAKE_JMA_FEED_URL, 1048576);
    if (is_array($feedResponse) && ($feedResponse['ok'] ?? false) === true && is_string($feedResponse['body'] ?? null)) {
        $entry = earthquake_parse_feed($feedResponse['body']);
    }

    // The high-frequency feed contains only the most recent arrivals. During a
    // quiet period there may be no earthquake entry at all, so fall back to the
    // JMA long-term feed. Its selected entry is cached for one hour because the
    // long-term feed itself is updated hourly.
    if ($entry === null) {
        $entry = $force ? null : earthquake_long_feed_cache_read();
        if ($entry === null) {
            $longFeedResponse = $fetch(EARTHQUAKE_JMA_LONG_FEED_URL, 2097152);
            if (is_array($longFeedResponse)
                && ($longFeedResponse['ok'] ?? false) === true
                && is_string($longFeedResponse['body'] ?? null)) {
                $entry = earthquake_parse_feed($longFeedResponse['body']);
                if ($entry !== null) {
                    earthquake_long_feed_cache_write($entry);
                }
            }
        }
    }

    if ($entry !== null) {
        // JMA asks clients not to download the same published XML repeatedly.
        // Atom feeds are polled, but an already cached immutable detail URL is
        // reused until a feed points at a new report file.
        $known = earthquake_cache_read(true);
        if ($known !== null && is_string($known['source_url'] ?? null) && hash_equals($known['source_url'], $entry['url'])) {
            $known['stale'] = false;
            $known['updated_at'] = gmdate('c');
            earthquake_cache_write($known);
            return $known;
        }

        $detailResponse = $fetch($entry['url'], 2097152);
        if (is_array($detailResponse) && ($detailResponse['ok'] ?? false) === true && is_string($detailResponse['body'] ?? null)) {
            $earthquake = earthquake_parse_report($detailResponse['body'], $entry['url']);
            if ($earthquake !== null) {
                earthquake_cache_write($earthquake);
                return $earthquake;
            }
        }
    }

    $stale = earthquake_cache_read(true);
    if ($stale !== null) {
        $stale['stale'] = true;
        return $stale;
    }

    throw new RuntimeException('Earthquake information could not be retrieved.');
}

function earthquake_widget_create(int $ownerId, int $location, string $style, int $width, int $height = 1): int
{
    if ($ownerId <= 0 || dashboard_widget_validate_location($location) === null
        || app_normalize_content_style($style) === null || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null) {
        throw new InvalidArgumentException('Earthquake Widget settings are invalid.');
    }
    return information_widget_create_record(
        $ownerId,
        $location,
        'earthquake',
        $style,
        $width,
        $height,
        ['schema' => 1]
    );
}

function earthquake_widget_update(int $ownerId, int $widgetId, string $style, int $width, int $height = 1): bool
{
    if ($ownerId <= 0 || $widgetId <= 0 || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null || dashboard_widget_validate_height($height) === null) {
        throw new InvalidArgumentException('Earthquake Widget settings are invalid.');
    }
    return information_widget_update_record($ownerId, $widgetId, 'earthquake', $style, $width, $height, null);
}

function earthquake_widget_delete(int $ownerId, int $widgetId): bool
{
    return information_widget_delete_record($ownerId, $widgetId, 'earthquake');
}

/** @return array<string,mixed>|null */
function earthquake_widget_owned(int $ownerId, int $widgetId): ?array
{
    return information_widget_owned_record($ownerId, $widgetId, 'earthquake');
}
