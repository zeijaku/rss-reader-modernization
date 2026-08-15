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
    return information_widget_validate_location_query($value);
}

function weather_widget_validate_location_name(mixed $value): ?string
{
    return information_widget_validate_location_name($value);
}

function weather_widget_validate_forecast_days(mixed $value): ?int
{
    $days = app_validate_positive_int($value);
    return $days !== null && in_array($days, [3, 5, 7], true) ? $days : null;
}

function weather_validate_latitude(mixed $value): ?float
{
    return information_widget_validate_latitude($value);
}

function weather_validate_longitude(mixed $value): ?float
{
    return information_widget_validate_longitude($value);
}

function weather_validate_timezone(mixed $value): ?string
{
    return information_widget_validate_timezone($value);
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
    return information_widget_cache_read(
        weather_cache_path($config),
        'forecast',
        APP_WEATHER_CACHE_TTL_SECONDS,
        86400,
        131072,
        $allowStale
    );
}

/** @param array<string,mixed> $forecast */
function weather_cache_write(array $config, array $forecast): void
{
    information_widget_cache_write(
        (string) APP_WEATHER_CACHE_DIR,
        weather_cache_path($config),
        '.weather-',
        'forecast',
        $forecast,
        131072
    );
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
    $locationQuery = information_widget_validate_location_query($input['weather_location'] ?? null);
    $days = weather_widget_validate_forecast_days($input['weather_forecast_days'] ?? null);
    if ($title === null || $locationQuery === null || $days === null) {
        return null;
    }
    $location = information_widget_resolve_location($locationQuery, $geocoder);
    if ($location === null) {
        return null;
    }
    return [
        'schema' => 1,
        'title' => $title,
        'location_query' => $locationQuery,
        'location_name' => $location['name'],
        'latitude' => $location['latitude'],
        'longitude' => $location['longitude'],
        'timezone' => $location['timezone'],
        'forecast_days' => $days,
    ];
}

function weather_widget_create(int $ownerId, int $location, string $style, int $width, array $config, int $height = 1): int
{
    if ($ownerId <= 0 || dashboard_widget_validate_location($location) === null
        || app_normalize_content_style($style) === null || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null
        || weather_widget_config_from_storage(dashboard_widget_encode_config($config))['location_name'] === '') {
        throw new InvalidArgumentException('Weather Widget settings are invalid.');
    }
    return information_widget_create_record($ownerId, $location, 'weather', $style, $width, $height, $config);
}

function weather_widget_update(int $ownerId, int $widgetId, string $style, int $width, array $config, int $height = 1): bool
{
    if ($ownerId <= 0 || $widgetId <= 0 || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null || dashboard_widget_validate_height($height) === null
        || weather_widget_config_from_storage(dashboard_widget_encode_config($config))['location_name'] === '') {
        throw new InvalidArgumentException('Weather Widget settings are invalid.');
    }
    return information_widget_update_record($ownerId, $widgetId, 'weather', $style, $width, $height, $config);
}

function weather_widget_delete(int $ownerId, int $widgetId): bool
{
    return information_widget_delete_record($ownerId, $widgetId, 'weather');
}

/** @return array<string,mixed>|null */
function weather_widget_owned_config(int $ownerId, int $widgetId): ?array
{
    return information_widget_owned_config($ownerId, $widgetId, 'weather', 'weather_widget_config_from_storage');
}
