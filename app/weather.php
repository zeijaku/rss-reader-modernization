<?php

declare(strict_types=1);

const WEATHER_GEOCODING_HOST = 'geocoding-api.open-meteo.com';
const WEATHER_FORECAST_HOST = 'api.open-meteo.com';

/** @return array{schema:int,title:string,location_query:string,location_name:string,latitude:float,longitude:float,timezone:string,forecast_days:int} */
function weather_widget_defaults(): array
{
    return [
        'schema' => 1,
        'title' => 'Weather',
        'location_query' => '',
        'location_name' => '',
        'latitude' => 0.0,
        'longitude' => 0.0,
        'timezone' => 'Asia/Tokyo',
        'forecast_days' => 3,
    ];
}

function weather_widget_validate_title(mixed $value): ?string
{
    return app_validate_text($value, 32, false);
}

function weather_widget_validate_location_query(mixed $value): ?string
{
    return app_validate_text($value, 80, false);
}

function weather_widget_validate_location_name(mixed $value): ?string
{
    return app_validate_text($value, 80, false);
}

function weather_widget_validate_forecast_days(mixed $value): ?int
{
    $days = app_validate_positive_int($value);
    return $days !== null && in_array($days, [3, 5, 7], true) ? $days : null;
}

function weather_validate_latitude(mixed $value): ?float
{
    if (!is_numeric($value)) {
        return null;
    }
    $number = (float) $value;
    return is_finite($number) && $number >= -90.0 && $number <= 90.0 ? $number : null;
}

function weather_validate_longitude(mixed $value): ?float
{
    if (!is_numeric($value)) {
        return null;
    }
    $number = (float) $value;
    return is_finite($number) && $number >= -180.0 && $number <= 180.0 ? $number : null;
}

function weather_validate_timezone(mixed $value): ?string
{
    if (!is_string($value) || $value === '' || strlen($value) > 64 || preg_match('/\A[A-Za-z0-9_+\-\/]+\z/D', $value) !== 1) {
        return null;
    }
    try {
        new DateTimeZone($value);
    } catch (Throwable) {
        return null;
    }
    return $value;
}

/** @return array{schema:int,title:string,location_query:string,location_name:string,latitude:float,longitude:float,timezone:string,forecast_days:int} */
function weather_widget_config_from_storage(mixed $value): array
{
    $defaults = weather_widget_defaults();
    $config = dashboard_widget_decode_config($value);
    $title = weather_widget_validate_title($config['title'] ?? null);
    $locationQuery = weather_widget_validate_location_query($config['location_query'] ?? null);
    $location = weather_widget_validate_location_name($config['location_name'] ?? null);
    $latitude = weather_validate_latitude($config['latitude'] ?? null);
    $longitude = weather_validate_longitude($config['longitude'] ?? null);
    $timezone = weather_validate_timezone($config['timezone'] ?? null);
    $days = weather_widget_validate_forecast_days($config['forecast_days'] ?? null);
    if ($locationQuery === null || $location === null || $latitude === null || $longitude === null || $timezone === null) {
        return $defaults;
    }
    return [
        'schema' => 1,
        'title' => $title ?? $defaults['title'],
        'location_query' => $locationQuery,
        'location_name' => $location,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'timezone' => $timezone,
        'forecast_days' => $days ?? $defaults['forecast_days'],
    ];
}

/** @return array{ok:bool,url:string,status:int,body:string,error_code:string} */
function weather_safe_fetch(string $url, string $allowedHost, ?callable $resolver = null, ?callable $transport = null): array
{
    $currentUrl = $url;
    $transportFn = $transport ?? 'app_curl_single_hop';
    $maxRedirects = min(2, APP_HTTP_MAX_REDIRECTS);

    for ($hop = 0; $hop <= $maxRedirects; $hop++) {
        $parts = parse_url($currentUrl);
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || strtolower((string) ($parts['host'] ?? '')) !== strtolower($allowedHost)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return ['ok' => false, 'url' => $currentUrl, 'status' => 0, 'body' => '', 'error_code' => 'invalid_target'];
        }
        $target = app_validate_fetch_target($currentUrl, $resolver);
        if (($target['ok'] ?? false) !== true) {
            return ['ok' => false, 'url' => $currentUrl, 'status' => 0, 'body' => '', 'error_code' => 'invalid_target'];
        }
        $requestUrl = (string) ($target['url'] ?? $currentUrl);
        $response = $transportFn([
            'url' => $requestUrl,
            'host' => (string) $target['host'],
            'port' => (int) $target['port'],
            'ip' => (string) $target['ips'][0],
            'max_bytes' => 262144,
            'connect_timeout_ms' => min(APP_HTTP_CONNECT_TIMEOUT_MS, APP_WEATHER_TIMEOUT_MS),
            'total_timeout_ms' => APP_WEATHER_TIMEOUT_MS,
            'user_agent' => APP_HTTP_USER_AGENT,
            'accept' => 'application/json, */*;q=0.1',
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
            if (!is_array($nextParts) || strtolower((string) ($nextParts['host'] ?? '')) !== strtolower($allowedHost)) {
                return ['ok' => false, 'url' => $requestUrl, 'status' => $status, 'body' => '', 'error_code' => 'redirect_host_not_allowed'];
            }
            $currentUrl = $next;
            continue;
        }
        if ($status < 200 || $status >= 300) {
            return ['ok' => false, 'url' => $requestUrl, 'status' => $status, 'body' => '', 'error_code' => 'http_status'];
        }
        $body = is_string($response['body'] ?? null) ? $response['body'] : '';
        if ($body === '' || strlen($body) > 262144) {
            return ['ok' => false, 'url' => $requestUrl, 'status' => $status, 'body' => '', 'error_code' => 'invalid_body'];
        }
        return ['ok' => true, 'url' => $requestUrl, 'status' => $status, 'body' => $body, 'error_code' => ''];
    }
    return ['ok' => false, 'url' => $currentUrl, 'status' => 0, 'body' => '', 'error_code' => 'too_many_redirects'];
}

/** @return array{name:string,latitude:float,longitude:float,timezone:string}|null */
function weather_resolve_location(string $query, ?callable $fetcher = null): ?array
{
    $query = weather_widget_validate_location_query($query);
    if ($query === null) {
        return null;
    }
    $url = APP_WEATHER_GEOCODING_URL . '?' . http_build_query([
        'name' => $query,
        'count' => 1,
        'language' => 'ja',
        'format' => 'json',
    ], '', '&', PHP_QUERY_RFC3986);
    $response = $fetcher !== null ? $fetcher($url) : weather_safe_fetch($url, WEATHER_GEOCODING_HOST);
    if (!is_array($response) || ($response['ok'] ?? false) !== true || !is_string($response['body'] ?? null)) {
        return null;
    }
    try {
        $json = json_decode($response['body'], true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }
    $item = is_array($json['results'][0] ?? null) ? $json['results'][0] : null;
    if ($item === null) {
        return null;
    }
    $name = weather_widget_validate_location_query($item['name'] ?? null);
    $latitude = weather_validate_latitude($item['latitude'] ?? null);
    $longitude = weather_validate_longitude($item['longitude'] ?? null);
    $timezone = weather_validate_timezone($item['timezone'] ?? null);
    if ($name === null || $latitude === null || $longitude === null || $timezone === null) {
        return null;
    }
    $suffix = [];
    foreach (['admin1', 'country'] as $key) {
        $part = weather_widget_validate_location_query($item[$key] ?? null);
        if ($part !== null && $part !== $name && !in_array($part, $suffix, true)) {
            $suffix[] = $part;
        }
    }
    $display = $name . ($suffix !== [] ? ' / ' . implode(' / ', $suffix) : '');
    $display = weather_widget_validate_location_name($display) ?? $name;
    return ['name' => $display, 'latitude' => $latitude, 'longitude' => $longitude, 'timezone' => $timezone];
}

/** @return array{label:string,icon:string} */
function weather_code_meta(int $code): array
{
    return match (true) {
        $code === 0 => ['label' => '快晴', 'icon' => 'fas fa-sun'],
        in_array($code, [1, 2], true) => ['label' => '晴れ時々曇り', 'icon' => 'fas fa-cloud-sun'],
        $code === 3 => ['label' => '曇り', 'icon' => 'fas fa-cloud'],
        in_array($code, [45, 48], true) => ['label' => '霧', 'icon' => 'fas fa-smog'],
        $code >= 51 && $code <= 67 => ['label' => '雨', 'icon' => 'fas fa-cloud-rain'],
        $code >= 71 && $code <= 77 => ['label' => '雪', 'icon' => 'fas fa-snowflake'],
        $code >= 80 && $code <= 82 => ['label' => 'にわか雨', 'icon' => 'fas fa-cloud-showers-heavy'],
        $code >= 85 && $code <= 86 => ['label' => 'にわか雪', 'icon' => 'fas fa-snowflake'],
        $code >= 95 && $code <= 99 => ['label' => '雷雨', 'icon' => 'fas fa-bolt'],
        default => ['label' => '天気不明', 'icon' => 'fas fa-cloud'],
    };
}

function weather_cache_path(array $config): string
{
    $key = hash('sha256', sprintf('%.5f|%.5f|%s|%d', $config['latitude'], $config['longitude'], $config['timezone'], $config['forecast_days']));
    return rtrim((string) APP_WEATHER_CACHE_DIR, '/\\') . '/' . $key . '.json';
}

/** @return array<string,mixed>|null */
function weather_cache_read(array $config, bool $allowStale = false): ?array
{
    $path = weather_cache_path($config);
    if (!is_file($path) || filesize($path) === false || filesize($path) > 131072) {
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
    if (!is_array($cache) || !is_int($cache['cached_at'] ?? null) || !is_array($cache['forecast'] ?? null)) {
        return null;
    }
    $age = time() - $cache['cached_at'];
    if ($age < 0 || (!$allowStale && $age > APP_WEATHER_CACHE_TTL_SECONDS) || ($allowStale && $age > 86400)) {
        return null;
    }
    return $cache['forecast'];
}

/** @param array<string,mixed> $forecast */
function weather_cache_write(array $config, array $forecast): void
{
    $dir = (string) APP_WEATHER_CACHE_DIR;
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        return;
    }
    $payload = json_encode(['schema' => 1, 'cached_at' => time(), 'forecast' => $forecast], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payload) || strlen($payload) > 131072) {
        return;
    }
    $path = weather_cache_path($config);
    $tmp = tempnam($dir, '.weather-');
    if (!is_string($tmp)) {
        return;
    }
    if (file_put_contents($tmp, $payload . "\n", LOCK_EX) !== false) {
        @chmod($tmp, 0640);
        @rename($tmp, $path);
    }
    if (is_file($tmp)) {
        @unlink($tmp);
    }
}

/** @return array<string,mixed>|null */
function weather_parse_forecast(string $body, array $config): ?array
{
    try {
        $json = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }
    if (!is_array($json) || !is_array($json['current'] ?? null) || !is_array($json['daily'] ?? null)) {
        return null;
    }
    $currentTemp = isset($json['current']['temperature_2m']) && is_numeric($json['current']['temperature_2m']) ? (float) $json['current']['temperature_2m'] : null;
    $currentCode = isset($json['current']['weather_code']) && is_numeric($json['current']['weather_code']) ? (int) $json['current']['weather_code'] : null;
    if ($currentTemp === null || $currentCode === null) {
        return null;
    }
    $times = $json['daily']['time'] ?? null;
    $codes = $json['daily']['weather_code'] ?? null;
    $maxes = $json['daily']['temperature_2m_max'] ?? null;
    $mins = $json['daily']['temperature_2m_min'] ?? null;
    $rain = $json['daily']['precipitation_probability_max'] ?? null;
    if (!is_array($times) || !is_array($codes) || !is_array($maxes) || !is_array($mins) || !is_array($rain)) {
        return null;
    }
    $days = [];
    $limit = min($config['forecast_days'], count($times), count($codes), count($maxes), count($mins), count($rain));
    for ($i = 0; $i < $limit; $i++) {
        if (!is_string($times[$i]) || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', $times[$i]) !== 1
            || !is_numeric($codes[$i]) || !is_numeric($maxes[$i]) || !is_numeric($mins[$i])) {
            continue;
        }
        $code = (int) $codes[$i];
        $meta = weather_code_meta($code);
        $days[] = [
            'date' => $times[$i],
            'weather_code' => $code,
            'label' => $meta['label'],
            'icon' => $meta['icon'],
            'temperature_max' => round((float) $maxes[$i], 1),
            'temperature_min' => round((float) $mins[$i], 1),
            'precipitation_probability' => is_numeric($rain[$i]) ? max(0, min(100, (int) round((float) $rain[$i]))) : null,
        ];
    }
    if ($days === []) {
        return null;
    }
    $currentMeta = weather_code_meta($currentCode);
    return [
        'location_name' => $config['location_name'],
        'timezone' => $config['timezone'],
        'current' => [
            'temperature' => round($currentTemp, 1),
            'weather_code' => $currentCode,
            'label' => $currentMeta['label'],
            'icon' => $currentMeta['icon'],
        ],
        'days' => $days,
        'updated_at' => gmdate('c'),
        'stale' => false,
    ];
}

/** @return array<string,mixed> */
function weather_forecast(array $config, bool $force = false, ?callable $fetcher = null): array
{
    $config = weather_widget_config_from_storage(dashboard_widget_encode_config($config));
    if ($config['location_name'] === '') {
        throw new InvalidArgumentException('Weather location is invalid.');
    }
    if (!$force) {
        $cached = weather_cache_read($config);
        if ($cached !== null) {
            return $cached;
        }
    }
    $url = APP_WEATHER_FORECAST_URL . '?' . http_build_query([
        'latitude' => sprintf('%.5f', $config['latitude']),
        'longitude' => sprintf('%.5f', $config['longitude']),
        'current' => 'temperature_2m,weather_code',
        'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max',
        'timezone' => $config['timezone'],
        'forecast_days' => $config['forecast_days'],
    ], '', '&', PHP_QUERY_RFC3986);
    $response = $fetcher !== null ? $fetcher($url) : weather_safe_fetch($url, WEATHER_FORECAST_HOST);
    if (is_array($response) && ($response['ok'] ?? false) === true && is_string($response['body'] ?? null)) {
        $forecast = weather_parse_forecast($response['body'], $config);
        if ($forecast !== null) {
            weather_cache_write($config, $forecast);
            return $forecast;
        }
    }
    $stale = weather_cache_read($config, true);
    if ($stale !== null) {
        $stale['stale'] = true;
        return $stale;
    }
    throw new RuntimeException('Weather forecast could not be retrieved.');
}

/** @return array{schema:int,title:string,location_query:string,location_name:string,latitude:float,longitude:float,timezone:string,forecast_days:int}|null */
function weather_widget_config_from_input(array $input, ?callable $geocoder = null): ?array
{
    $title = weather_widget_validate_title($input['weather_title'] ?? null);
    $locationQuery = weather_widget_validate_location_query($input['weather_location'] ?? null);
    $days = weather_widget_validate_forecast_days($input['weather_forecast_days'] ?? null);
    if ($title === null || $locationQuery === null || $days === null) {
        return null;
    }
    $resolver = $geocoder ?? 'weather_resolve_location';
    $location = $resolver($locationQuery);
    if (!is_array($location)) {
        return null;
    }
    return [
        'schema' => 1,
        'title' => $title,
        'location_query' => $locationQuery,
        'location_name' => (string) $location['name'],
        'latitude' => (float) $location['latitude'],
        'longitude' => (float) $location['longitude'],
        'timezone' => (string) $location['timezone'],
        'forecast_days' => $days,
    ];
}

function weather_widget_create(int $ownerId, int $location, string $style, int $width, array $config, int $height = 1): int
{
    if ($ownerId <= 0 || dashboard_widget_validate_location($location) === null
        || app_normalize_content_style($style) === null || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null || weather_widget_config_from_storage(dashboard_widget_encode_config($config))['location_name'] === '') {
        throw new InvalidArgumentException('Weather Widget settings are invalid.');
    }
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        $now = app_now();
        $stmt = $pdo->prepare(
            'INSERT INTO ' . db_table_identifier('dashboard_widget') . ' '
            . '(widget_owner, widget_location, widget_type, widget_reference_id, widget_sort_order, widget_width, widget_height, widget_style, widget_config, widget_flag, widget_created_at, widget_updated_at) '
            . "VALUES (:owner, :location, 'weather', NULL, :sort_order, :width, :height, :style, :config, 0, :created_at, :updated_at)"
        );
        $stmt->execute([
            ':owner' => $ownerId, ':location' => $location,
            ':sort_order' => dashboard_widget_next_sort_order($pdo, $ownerId, $location),
            ':width' => $width, ':height' => $height, ':style' => $style,
            ':config' => dashboard_widget_encode_config($config), ':created_at' => $now, ':updated_at' => $now,
        ]);
        $widgetId = (int) $pdo->lastInsertId();
        if ($started) {
            $pdo->commit();
        }
        return $widgetId;
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function weather_widget_update(int $ownerId, int $widgetId, string $style, int $width, array $config, int $height = 1): bool
{
    if ($ownerId <= 0 || $widgetId <= 0 || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null || dashboard_widget_validate_height($height) === null
        || weather_widget_config_from_storage(dashboard_widget_encode_config($config))['location_name'] === '') {
        throw new InvalidArgumentException('Weather Widget settings are invalid.');
    }
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        if (dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'weather') === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' SET widget_width = :width, widget_height = :height, '
            . 'widget_style = :style, widget_config = :config, widget_updated_at = :updated_at '
            . "WHERE widget_id = :widget_id AND widget_owner = :owner AND widget_type = 'weather' AND widget_flag = 0"
        );
        $stmt->execute([
            ':width' => $width, ':height' => $height, ':style' => $style,
            ':config' => dashboard_widget_encode_config($config), ':updated_at' => app_now(),
            ':widget_id' => $widgetId, ':owner' => $ownerId,
        ]);
        if ($started) {
            $pdo->commit();
        }
        return true;
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function weather_widget_delete(int $ownerId, int $widgetId): bool
{
    if ($ownerId <= 0 || $widgetId <= 0) {
        return false;
    }
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        if (dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'weather') === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' SET widget_flag = 1, widget_updated_at = :updated_at '
            . "WHERE widget_id = :widget_id AND widget_owner = :owner AND widget_type = 'weather' AND widget_flag = 0"
        );
        $stmt->execute([':updated_at' => app_now(), ':widget_id' => $widgetId, ':owner' => $ownerId]);
        if ($started) {
            $pdo->commit();
        }
        return true;
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/** @return array<string,mixed>|null */
function weather_widget_owned_config(int $ownerId, int $widgetId): ?array
{
    if ($ownerId <= 0 || $widgetId <= 0) {
        return null;
    }
    $stmt = conn_db()->prepare(
        'SELECT widget_config FROM ' . db_table_identifier('dashboard_widget') . ' '
        . "WHERE widget_id = :widget_id AND widget_owner = :owner AND widget_type = 'weather' AND widget_flag = 0"
    );
    $stmt->execute([':widget_id' => $widgetId, ':owner' => $ownerId]);
    $config = $stmt->fetchColumn();
    if (!is_string($config)) {
        return null;
    }
    $normalized = weather_widget_config_from_storage($config);
    return $normalized['location_name'] === '' ? null : $normalized;
}
