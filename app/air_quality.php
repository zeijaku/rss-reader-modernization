<?php

declare(strict_types=1);

const AIR_QUALITY_HOST = 'air-quality-api.open-meteo.com';
const AIR_QUALITY_API_URL = 'https://air-quality-api.open-meteo.com/v1/air-quality';
const AIR_QUALITY_CACHE_TTL_SECONDS = 900;
const AIR_QUALITY_STALE_MAX_AGE_SECONDS = 86400;

/** @return array{schema:int,title:string,location_query:string,location_name:string,latitude:float,longitude:float,timezone:string} */
function air_quality_widget_defaults(): array
{
    return [
        'schema' => 1,
        'title' => 'Air Quality',
        'location_query' => '',
        'location_name' => '',
        'latitude' => 0.0,
        'longitude' => 0.0,
        'timezone' => 'Asia/Tokyo',
    ];
}

function air_quality_widget_validate_title(mixed $value): ?string
{
    return app_validate_text($value, 32, false);
}

/** @return array{schema:int,title:string,location_query:string,location_name:string,latitude:float,longitude:float,timezone:string} */
function air_quality_widget_config_from_storage(mixed $value): array
{
    $defaults = air_quality_widget_defaults();
    $config = dashboard_widget_decode_config($value);
    $title = air_quality_widget_validate_title($config['title'] ?? null);
    $locationQuery = information_widget_validate_location_query($config['location_query'] ?? null);
    $locationName = information_widget_validate_location_name($config['location_name'] ?? null);
    $latitude = information_widget_validate_latitude($config['latitude'] ?? null);
    $longitude = information_widget_validate_longitude($config['longitude'] ?? null);
    $timezone = information_widget_validate_timezone($config['timezone'] ?? null);
    if ($locationQuery === null || $locationName === null || $latitude === null || $longitude === null || $timezone === null) {
        return $defaults;
    }
    return [
        'schema' => 1,
        'title' => $title ?? $defaults['title'],
        'location_query' => $locationQuery,
        'location_name' => $locationName,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'timezone' => $timezone,
    ];
}

/** @return array{schema:int,title:string,location_query:string,location_name:string,latitude:float,longitude:float,timezone:string}|null */
function air_quality_widget_config_from_input(array $input, ?callable $geocoder = null): ?array
{
    $title = air_quality_widget_validate_title($input['air_quality_title'] ?? null);
    $locationQuery = information_widget_validate_location_query($input['air_quality_location'] ?? null);
    if ($title === null || $locationQuery === null) {
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
    ];
}

function air_quality_validate_number(mixed $value, float $minimum, float $maximum): ?float
{
    if (!is_numeric($value)) {
        return null;
    }
    $number = (float) $value;
    return is_finite($number) && $number >= $minimum && $number <= $maximum ? $number : null;
}

function air_quality_aqi_label(int $aqi): string
{
    return match (true) {
        $aqi <= 50 => '良好',
        $aqi <= 100 => '普通',
        $aqi <= 150 => '敏感な人は注意',
        $aqi <= 200 => '健康に悪い',
        $aqi <= 300 => '非常に悪い',
        default => '危険',
    };
}

function air_quality_uv_label(float $uvIndex): string
{
    return match (true) {
        $uvIndex < 3.0 => '弱い',
        $uvIndex < 6.0 => '中程度',
        $uvIndex < 8.0 => '強い',
        $uvIndex < 11.0 => '非常に強い',
        default => '極端に強い',
    };
}

function air_quality_cache_dir(): string
{
    return dirname(rtrim((string) APP_WEATHER_CACHE_DIR, '/\\')) . '/air-quality';
}

/** @param array<string,mixed> $config */
function air_quality_cache_path(array $config): string
{
    $key = hash('sha256', sprintf('%.5f|%.5f|%s', $config['latitude'], $config['longitude'], $config['timezone']));
    return rtrim(air_quality_cache_dir(), '/\\') . '/' . $key . '.json';
}

/** @return array<string,mixed>|null */
function air_quality_cache_read(array $config, bool $allowStale = false): ?array
{
    return information_widget_cache_read(
        air_quality_cache_path($config),
        'air_quality',
        AIR_QUALITY_CACHE_TTL_SECONDS,
        AIR_QUALITY_STALE_MAX_AGE_SECONDS,
        65536,
        $allowStale,
        true
    );
}

/** @param array<string,mixed> $config @param array<string,mixed> $airQuality */
function air_quality_cache_write(array $config, array $airQuality): void
{
    information_widget_cache_write(
        air_quality_cache_dir(),
        air_quality_cache_path($config),
        '.air-quality-',
        'air_quality',
        $airQuality,
        65536
    );
}

/** @return array<string,mixed>|null */
function air_quality_parse_current(string $body, array $config): ?array
{
    try {
        $json = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }
    if (!is_array($json) || ($json['error'] ?? false) === true || !is_array($json['current'] ?? null)) {
        return null;
    }

    $current = $json['current'];
    $observedAt = $current['time'] ?? null;
    if (!is_string($observedAt)
        || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}(?::[0-9]{2})?\z/D', $observedAt) !== 1) {
        return null;
    }

    $aqiValue = air_quality_validate_number($current['us_aqi'] ?? null, 0.0, 1000.0);
    $pm25 = air_quality_validate_number($current['pm2_5'] ?? null, 0.0, 5000.0);
    $pm10 = air_quality_validate_number($current['pm10'] ?? null, 0.0, 5000.0);
    $uv = air_quality_validate_number($current['uv_index'] ?? null, 0.0, 30.0);
    if ($aqiValue === null || $pm25 === null || $pm10 === null || $uv === null) {
        return null;
    }

    $aqi = max(0, (int) round($aqiValue));
    $uvRounded = round($uv, 1);
    return [
        'location_name' => (string) $config['location_name'],
        'timezone' => (string) $config['timezone'],
        'observed_at' => $observedAt,
        'us_aqi' => $aqi,
        'aqi_label' => air_quality_aqi_label($aqi),
        'pm2_5' => round($pm25, 1),
        'pm10' => round($pm10, 1),
        'uv_index' => $uvRounded,
        'uv_label' => air_quality_uv_label($uvRounded),
        'updated_at' => gmdate('c'),
        'stale' => false,
    ];
}

/** @return array<string,mixed> */
function air_quality_current(array $config, bool $force = false, ?callable $fetcher = null): array
{
    $config = air_quality_widget_config_from_storage(dashboard_widget_encode_config($config));
    if ($config['location_name'] === '') {
        throw new InvalidArgumentException('Air Quality location is invalid.');
    }

    if (!$force) {
        $cached = air_quality_cache_read($config);
        if ($cached !== null) {
            return $cached;
        }
    }

    $url = AIR_QUALITY_API_URL . '?' . http_build_query([
        'latitude' => sprintf('%.5f', $config['latitude']),
        'longitude' => sprintf('%.5f', $config['longitude']),
        'current' => 'us_aqi,pm2_5,pm10,uv_index',
        'timezone' => $config['timezone'],
    ], '', '&', PHP_QUERY_RFC3986);

    $response = $fetcher !== null ? $fetcher($url) : weather_safe_fetch($url, AIR_QUALITY_HOST);
    if (is_array($response) && ($response['ok'] ?? false) === true && is_string($response['body'] ?? null)) {
        $airQuality = air_quality_parse_current($response['body'], $config);
        if ($airQuality !== null) {
            air_quality_cache_write($config, $airQuality);
            return $airQuality;
        }
    }

    $stale = air_quality_cache_read($config, true);
    if ($stale !== null) {
        $stale['stale'] = true;
        return $stale;
    }

    throw new RuntimeException('Air Quality could not be retrieved.');
}

function air_quality_widget_create(int $ownerId, int $location, string $style, int $width, array $config, int $height = 1): int
{
    if ($ownerId <= 0 || dashboard_widget_validate_location($location) === null
        || app_normalize_content_style($style) === null || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null
        || air_quality_widget_config_from_storage(dashboard_widget_encode_config($config))['location_name'] === '') {
        throw new InvalidArgumentException('Air Quality Widget settings are invalid.');
    }
    return information_widget_create_record($ownerId, $location, 'air_quality', $style, $width, $height, $config);
}

function air_quality_widget_update(int $ownerId, int $widgetId, string $style, int $width, array $config, int $height = 1): bool
{
    if ($ownerId <= 0 || $widgetId <= 0 || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null || dashboard_widget_validate_height($height) === null
        || air_quality_widget_config_from_storage(dashboard_widget_encode_config($config))['location_name'] === '') {
        throw new InvalidArgumentException('Air Quality Widget settings are invalid.');
    }
    return information_widget_update_record($ownerId, $widgetId, 'air_quality', $style, $width, $height, $config);
}

function air_quality_widget_delete(int $ownerId, int $widgetId): bool
{
    return information_widget_delete_record($ownerId, $widgetId, 'air_quality');
}

/** @return array<string,mixed>|null */
function air_quality_widget_owned_config(int $ownerId, int $widgetId): ?array
{
    return information_widget_owned_config($ownerId, $widgetId, 'air_quality', 'air_quality_widget_config_from_storage');
}
