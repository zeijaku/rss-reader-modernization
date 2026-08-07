<?php

declare(strict_types=1);

/** @return array<string,string> */
function japanese_holiday_validate_map(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }

    $holidays = [];
    foreach ($value as $date => $name) {
        if (!is_string($date) || !is_string($name)
            || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', $date) !== 1
            || !app_is_valid_utf8($name)) {
            continue;
        }
        $parts = array_map('intval', explode('-', $date));
        if (count($parts) !== 3 || $parts[0] < 1948 || $parts[0] > 2100
            || !checkdate($parts[1], $parts[2], $parts[0])) {
            continue;
        }
        $name = trim($name);
        if ($name === '' || app_text_length($name) > 64) {
            continue;
        }
        $holidays[$date] = $name;
    }

    ksort($holidays, SORT_STRING);
    return $holidays;
}

/** @return array<string,string> */
function japanese_holiday_snapshot(): array
{
    static $holidays = null;
    if (is_array($holidays)) {
        return $holidays;
    }

    $path = __DIR__ . '/data/japanese_holidays_snapshot.json';
    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        $holidays = [];
        return $holidays;
    }
    $decoded = json_decode($raw, true);
    $holidays = japanese_holiday_validate_map(is_array($decoded) ? ($decoded['holidays'] ?? null) : null);
    return $holidays;
}

/** @return array{schema:int,updated_at:string,source:string,holidays:array<string,string>}|null */
function japanese_holiday_cache_read(): ?array
{
    $path = (string) APP_HOLIDAY_CACHE_PATH;
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '' || strlen($raw) > APP_HOLIDAY_CACHE_MAX_BYTES) {
        return null;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || (int) ($decoded['schema'] ?? 0) !== 1) {
        return null;
    }
    $updatedAt = isset($decoded['updated_at']) && is_string($decoded['updated_at']) ? trim($decoded['updated_at']) : '';
    $source = isset($decoded['source']) && is_string($decoded['source']) ? trim($decoded['source']) : '';
    $holidays = japanese_holiday_validate_map($decoded['holidays'] ?? null);
    if ($updatedAt === '' || strtotime($updatedAt) === false || $source === '' || count($holidays) < 10) {
        return null;
    }

    return [
        'schema' => 1,
        'updated_at' => $updatedAt,
        'source' => $source,
        'holidays' => $holidays,
    ];
}

function japanese_holiday_cache_is_fresh(?array $cache): bool
{
    if (!is_array($cache)) {
        return false;
    }
    if (!hash_equals((string) APP_HOLIDAY_CSV_URL, (string) ($cache['source'] ?? ''))) {
        return false;
    }
    $updated = strtotime((string) ($cache['updated_at'] ?? ''));
    if ($updated === false) {
        return false;
    }
    return $updated >= time() - (APP_HOLIDAY_CACHE_DAYS * 86400);
}

/** @return array{holidays:array<string,string>,refresh_due:bool,source:string} */
function japanese_holiday_current_data(): array
{
    $cache = japanese_holiday_cache_read();
    if ($cache !== null) {
        return [
            'holidays' => $cache['holidays'],
            'refresh_due' => !japanese_holiday_cache_is_fresh($cache),
            'source' => 'cache',
        ];
    }

    return [
        'holidays' => japanese_holiday_snapshot(),
        'refresh_due' => true,
        'source' => 'snapshot',
    ];
}

/** @return array<string,string> */
function japanese_holiday_month(int $year, int $month): array
{
    if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
        return [];
    }
    $prefix = sprintf('%04d-%02d-', $year, $month);
    $result = [];
    foreach (japanese_holiday_current_data()['holidays'] as $date => $name) {
        if (str_starts_with($date, $prefix)) {
            $result[$date] = $name;
        }
    }
    return $result;
}

function japanese_holiday_configured_url(): ?string
{
    $url = trim((string) APP_HOLIDAY_CSV_URL);
    if ($url === '' || strlen($url) > 1024 || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return null;
    }
    $parts = parse_url($url);
    if (!is_array($parts)
        || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
        || (string) ($parts['host'] ?? '') === ''
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
        return null;
    }
    return $url;
}

/** @return array{ok:bool,url:string,status:int,body:string,error_code:string} */
function japanese_holiday_safe_fetch(
    string $url,
    ?callable $resolver = null,
    ?callable $transport = null
): array {
    $currentUrl = $url;
    $transportFn = $transport ?? 'app_curl_single_hop';
    $maxRedirects = min(3, APP_HTTP_MAX_REDIRECTS);

    for ($hop = 0; $hop <= $maxRedirects; $hop++) {
        $target = app_validate_fetch_target($currentUrl, $resolver);
        if (($target['ok'] ?? false) !== true) {
            return ['ok' => false, 'url' => $currentUrl, 'status' => 0, 'body' => '', 'error_code' => 'invalid_target'];
        }
        $requestUrl = (string) ($target['url'] ?? $currentUrl);
        $parts = parse_url($requestUrl);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return ['ok' => false, 'url' => $requestUrl, 'status' => 0, 'body' => '', 'error_code' => 'https_required'];
        }

        $response = $transportFn([
            'url' => $requestUrl,
            'host' => (string) $target['host'],
            'port' => (int) $target['port'],
            'ip' => (string) $target['ips'][0],
            'max_bytes' => APP_HOLIDAY_CSV_MAX_BYTES,
            'connect_timeout_ms' => min(APP_HTTP_CONNECT_TIMEOUT_MS, APP_HOLIDAY_TIMEOUT_MS),
            'total_timeout_ms' => APP_HOLIDAY_TIMEOUT_MS,
            'user_agent' => APP_HTTP_USER_AGENT,
            'accept' => 'text/csv, text/plain;q=0.9, */*;q=0.1',
            'request_headers' => [],
        ]);
        if (($response['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'url' => $requestUrl,
                'status' => (int) ($response['status'] ?? 0),
                'body' => '',
                'error_code' => (string) ($response['error_code'] ?? 'transport_error'),
            ];
        }
        $status = (int) ($response['status'] ?? 0);
        if (in_array($status, [301, 302, 303, 307, 308], true)) {
            if ($hop >= $maxRedirects) {
                return ['ok' => false, 'url' => $requestUrl, 'status' => $status, 'body' => '', 'error_code' => 'too_many_redirects'];
            }
            $next = app_resolve_redirect_url($requestUrl, is_string($response['location'] ?? null) ? $response['location'] : '');
            if ($next === null) {
                return ['ok' => false, 'url' => $requestUrl, 'status' => $status, 'body' => '', 'error_code' => 'invalid_redirect'];
            }
            $nextParts = parse_url($next);
            if (!is_array($nextParts) || strtolower((string) ($nextParts['scheme'] ?? '')) !== 'https') {
                return ['ok' => false, 'url' => $requestUrl, 'status' => $status, 'body' => '', 'error_code' => 'https_required'];
            }
            $currentUrl = $next;
            continue;
        }
        if ($status < 200 || $status >= 300) {
            return ['ok' => false, 'url' => $requestUrl, 'status' => $status, 'body' => '', 'error_code' => 'http_status'];
        }
        $body = is_string($response['body'] ?? null) ? $response['body'] : '';
        if ($body === '' || strlen($body) > APP_HOLIDAY_CSV_MAX_BYTES) {
            return ['ok' => false, 'url' => $requestUrl, 'status' => $status, 'body' => '', 'error_code' => 'invalid_body'];
        }
        return ['ok' => true, 'url' => $requestUrl, 'status' => $status, 'body' => $body, 'error_code' => ''];
    }

    return ['ok' => false, 'url' => $currentUrl, 'status' => 0, 'body' => '', 'error_code' => 'too_many_redirects'];
}

/** @return array<string,string>|null */
function japanese_holiday_parse_csv(string $body): ?array
{
    if ($body === '' || strlen($body) > APP_HOLIDAY_CSV_MAX_BYTES) {
        return null;
    }
    if (str_starts_with($body, "\xEF\xBB\xBF")) {
        $body = substr($body, 3);
    }
    if (!app_is_valid_utf8($body)) {
        if (!function_exists('mb_detect_encoding') || !function_exists('mb_convert_encoding')) {
            return null;
        }
        $encoding = mb_detect_encoding($body, ['SJIS-win', 'CP932', 'UTF-8', 'EUC-JP'], true);
        if (!is_string($encoding)) {
            return null;
        }
        $body = mb_convert_encoding($body, 'UTF-8', $encoding);
    }
    if (!app_is_valid_utf8($body)) {
        return null;
    }

    $stream = fopen('php://temp', 'r+');
    if ($stream === false) {
        return null;
    }
    fwrite($stream, $body);
    rewind($stream);

    $holidays = [];
    $rows = 0;
    while (($row = fgetcsv($stream, 0, ',', '"', '\\')) !== false) {
        $rows++;
        if ($rows > 5000 || !isset($row[0], $row[1]) || !is_string($row[0]) || !is_string($row[1])) {
            continue;
        }
        $dateText = trim($row[0]);
        $name = trim($row[1]);
        if (preg_match('/\A([0-9]{4})[\/\-]([0-9]{1,2})[\/\-]([0-9]{1,2})\z/D', $dateText, $matches) !== 1) {
            continue;
        }
        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        if ($year < 1948 || $year > 2100 || !checkdate($month, $day, $year)
            || $name === '' || app_text_length($name) > 64) {
            continue;
        }
        $holidays[sprintf('%04d-%02d-%02d', $year, $month, $day)] = $name;
    }
    fclose($stream);

    ksort($holidays, SORT_STRING);
    if (count($holidays) < 10) {
        return null;
    }
    $maxYear = 0;
    foreach (array_keys($holidays) as $date) {
        $maxYear = max($maxYear, (int) substr($date, 0, 4));
    }
    if ($maxYear < ((int) date('Y') - 1)) {
        return null;
    }
    return $holidays;
}

/** @param array<string,string> $holidays */
function japanese_holiday_cache_write(array $holidays, string $sourceUrl): bool
{
    $holidays = japanese_holiday_validate_map($holidays);
    if (count($holidays) < 10) {
        return false;
    }
    $path = (string) APP_HOLIDAY_CACHE_PATH;
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        return false;
    }
    if (!is_writable($dir)) {
        return false;
    }

    $payload = json_encode([
        'schema' => 1,
        'updated_at' => gmdate('c'),
        'source' => $sourceUrl,
        'holidays' => $holidays,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($payload) || strlen($payload) > APP_HOLIDAY_CACHE_MAX_BYTES) {
        return false;
    }
    $tmp = tempnam($dir, '.holiday-');
    if (!is_string($tmp)) {
        return false;
    }
    $ok = file_put_contents($tmp, $payload . "\n", LOCK_EX) !== false;
    if ($ok) {
        @chmod($tmp, 0640);
        $ok = @rename($tmp, $path);
    }
    if (is_file($tmp)) {
        @unlink($tmp);
    }
    return $ok;
}

/** @return array{refreshed:bool,count:int,reason:string} */
function japanese_holiday_refresh(?callable $fetcher = null): array
{
    $url = japanese_holiday_configured_url();
    if ($url === null) {
        return ['refreshed' => false, 'count' => 0, 'reason' => 'invalid_url'];
    }

    $lockDir = dirname((string) APP_HOLIDAY_CACHE_PATH);
    if (!is_dir($lockDir) && !@mkdir($lockDir, 0750, true) && !is_dir($lockDir)) {
        return ['refreshed' => false, 'count' => 0, 'reason' => 'cache_unavailable'];
    }
    $lock = @fopen((string) APP_HOLIDAY_LOCK_PATH, 'c');
    if ($lock === false || !@flock($lock, LOCK_EX | LOCK_NB)) {
        if (is_resource($lock)) {
            fclose($lock);
        }
        return ['refreshed' => false, 'count' => 0, 'reason' => 'busy'];
    }

    try {
        $cache = japanese_holiday_cache_read();
        if (japanese_holiday_cache_is_fresh($cache)) {
            return ['refreshed' => false, 'count' => count($cache['holidays']), 'reason' => 'fresh'];
        }
        $response = $fetcher !== null ? $fetcher($url) : japanese_holiday_safe_fetch($url);
        if (!is_array($response) || ($response['ok'] ?? false) !== true || !is_string($response['body'] ?? null)) {
            return ['refreshed' => false, 'count' => 0, 'reason' => 'fetch_failed'];
        }
        $holidays = japanese_holiday_parse_csv($response['body']);
        if ($holidays === null) {
            return ['refreshed' => false, 'count' => 0, 'reason' => 'invalid_csv'];
        }
        if (!japanese_holiday_cache_write($holidays, $url)) {
            return ['refreshed' => false, 'count' => 0, 'reason' => 'cache_write_failed'];
        }
        return ['refreshed' => true, 'count' => count($holidays), 'reason' => 'updated'];
    } finally {
        @flock($lock, LOCK_UN);
        fclose($lock);
    }
}
