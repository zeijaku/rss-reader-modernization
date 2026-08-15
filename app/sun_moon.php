<?php

declare(strict_types=1);

const SUN_MOON_SYNODIC_MONTH_DAYS = 29.530588853;

/** @return array{schema:int,title:string,location_query:string,location_name:string,latitude:float,longitude:float,timezone:string} */
function sun_moon_widget_defaults(): array
{
    return [
        'schema' => 1,
        'title' => 'Sun / Moon',
        'location_query' => '',
        'location_name' => '',
        'latitude' => 0.0,
        'longitude' => 0.0,
        'timezone' => 'Asia/Tokyo',
    ];
}

function sun_moon_widget_validate_title(mixed $value): ?string
{
    return app_validate_text($value, 32, false);
}

/** @return array{schema:int,title:string,location_query:string,location_name:string,latitude:float,longitude:float,timezone:string} */
function sun_moon_widget_config_from_storage(mixed $value): array
{
    $defaults = sun_moon_widget_defaults();
    $config = dashboard_widget_decode_config($value);
    $title = sun_moon_widget_validate_title($config['title'] ?? null);
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
function sun_moon_widget_config_from_input(array $input, ?callable $geocoder = null): ?array
{
    $title = sun_moon_widget_validate_title($input['sun_moon_title'] ?? null);
    $locationQuery = information_widget_validate_location_query($input['sun_moon_location'] ?? null);
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

function sun_moon_sin_degrees(float $degrees): float
{
    return sin(deg2rad(fmod($degrees, 360.0)));
}

function sun_moon_delta_t_seconds(float $decimalYear): float
{
    if ($decimalYear >= 1986.0 && $decimalYear < 2005.0) {
        $t = $decimalYear - 2000.0;
        return 63.86 + 0.3345 * $t - 0.060374 * $t ** 2 + 0.0017275 * $t ** 3
            + 0.000651814 * $t ** 4 + 0.00002373599 * $t ** 5;
    }
    if ($decimalYear >= 2005.0 && $decimalYear < 2050.0) {
        $t = $decimalYear - 2000.0;
        return 62.92 + 0.32217 * $t + 0.005589 * $t ** 2;
    }
    if ($decimalYear >= 2050.0 && $decimalYear < 2150.0) {
        $u = ($decimalYear - 1820.0) / 100.0;
        return -20.0 + 32.0 * $u ** 2 - 0.5628 * (2150.0 - $decimalYear);
    }
    $u = ($decimalYear - 1820.0) / 100.0;
    return -20.0 + 32.0 * $u ** 2;
}

/**
 * New Moon / Full Moonの時刻をJDEの周期項から求める。
 * Dashboard表示用のローカル計算で、秒単位の天文暦を目的としない。
 */
function sun_moon_phase_timestamp(float $k, bool $fullMoon): int
{
    $t = $k / 1236.85;
    $t2 = $t * $t;
    $t3 = $t2 * $t;
    $t4 = $t3 * $t;
    $jde = 2451550.09765 + 29.530588853 * $k + 0.0001337 * $t2 - 0.000000150 * $t3 + 0.00000000073 * $t4;
    $e = 1.0 - 0.002516 * $t - 0.0000074 * $t2;
    $m = 2.5534 + 29.10535670 * $k - 0.0000014 * $t2 - 0.00000011 * $t3;
    $moonM = 201.5643 + 385.81693528 * $k + 0.0107582 * $t2 + 0.00001238 * $t3 - 0.000000058 * $t4;
    $f = 160.7108 + 390.67050284 * $k - 0.0016118 * $t2 - 0.00000227 * $t3 + 0.000000011 * $t4;
    $omega = 124.7746 - 1.56375588 * $k + 0.0020672 * $t2 + 0.00000215 * $t3;

    $correction = ($fullMoon ? -0.40614 : -0.40720) * sun_moon_sin_degrees($moonM)
        + ($fullMoon ? 0.17302 : 0.17241) * $e * sun_moon_sin_degrees($m)
        + ($fullMoon ? 0.01614 : 0.01608) * sun_moon_sin_degrees(2.0 * $moonM)
        + ($fullMoon ? 0.01043 : 0.01039) * sun_moon_sin_degrees(2.0 * $f)
        + ($fullMoon ? 0.00734 : 0.00739) * $e * sun_moon_sin_degrees($moonM - $m)
        - ($fullMoon ? 0.00515 : 0.00514) * $e * sun_moon_sin_degrees($moonM + $m)
        + ($fullMoon ? 0.00209 : 0.00208) * $e * $e * sun_moon_sin_degrees(2.0 * $m)
        - 0.00111 * sun_moon_sin_degrees($moonM - 2.0 * $f)
        - 0.00057 * sun_moon_sin_degrees($moonM + 2.0 * $f)
        + 0.00056 * $e * sun_moon_sin_degrees(2.0 * $moonM + $m)
        - 0.00042 * sun_moon_sin_degrees(3.0 * $moonM)
        + 0.00042 * $e * sun_moon_sin_degrees($m + 2.0 * $f)
        + 0.00038 * $e * sun_moon_sin_degrees($m - 2.0 * $f)
        - 0.00024 * $e * sun_moon_sin_degrees(2.0 * $moonM - $m)
        - 0.00017 * sun_moon_sin_degrees($omega)
        - 0.00007 * sun_moon_sin_degrees($moonM + 2.0 * $m)
        + 0.00004 * sun_moon_sin_degrees(2.0 * $moonM - 2.0 * $f)
        + 0.00004 * sun_moon_sin_degrees(3.0 * $m)
        + 0.00003 * sun_moon_sin_degrees($moonM + $m - 2.0 * $f)
        + 0.00003 * sun_moon_sin_degrees(2.0 * $moonM + 2.0 * $f)
        - 0.00003 * sun_moon_sin_degrees($moonM + $m + 2.0 * $f)
        + 0.00003 * sun_moon_sin_degrees($moonM - $m + 2.0 * $f)
        - 0.00002 * sun_moon_sin_degrees($moonM - $m - 2.0 * $f)
        - 0.00002 * sun_moon_sin_degrees(3.0 * $moonM + $m)
        + 0.00002 * sun_moon_sin_degrees(4.0 * $moonM);

    $planetaryTerms = [
        [299.77 + 0.107408 * $k - 0.009173 * $t2, 0.000325],
        [251.88 + 0.016321 * $k, 0.000165],
        [251.83 + 26.651886 * $k, 0.000164],
        [349.42 + 36.412478 * $k, 0.000126],
        [84.66 + 18.206239 * $k, 0.000110],
        [141.74 + 53.303771 * $k, 0.000062],
        [207.14 + 2.453732 * $k, 0.000060],
        [154.84 + 7.306860 * $k, 0.000056],
        [34.52 + 27.261239 * $k, 0.000047],
        [207.19 + 0.121824 * $k, 0.000042],
        [291.34 + 1.844379 * $k, 0.000040],
        [161.72 + 24.198154 * $k, 0.000037],
        [239.56 + 25.513099 * $k, 0.000035],
        [331.55 + 3.592518 * $k, 0.000023],
    ];
    foreach ($planetaryTerms as [$angle, $coefficient]) {
        $correction += $coefficient * sun_moon_sin_degrees($angle);
    }
    $jde += $correction;

    $decimalYear = 2000.0 + ($k / 12.3685);
    $deltaT = sun_moon_delta_t_seconds($decimalYear);
    return (int) round(($jde - 2440587.5) * 86400.0 - $deltaT);
}

function sun_moon_estimated_k(int $timestamp): float
{
    $utc = (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('UTC'));
    $year = (int) $utc->format('Y');
    $day = (int) $utc->format('z') + (((int) $utc->format('G')) + ((int) $utc->format('i')) / 60.0) / 24.0;
    return (($year + $day / 365.2425) - 2000.0) * 12.3685;
}

/** @return array{previous_new:int,next_new:int,next_full:int} */
function sun_moon_phase_events(int $timestamp): array
{
    $center = (int) round(sun_moon_estimated_k($timestamp));
    $newMoons = [];
    for ($index = $center - 3; $index <= $center + 3; $index++) {
        $newMoons[] = ['k' => $index, 'timestamp' => sun_moon_phase_timestamp((float) $index, false)];
    }
    usort($newMoons, static fn(array $left, array $right): int => $left['timestamp'] <=> $right['timestamp']);

    $previous = null;
    $next = null;
    foreach ($newMoons as $event) {
        if ($event['timestamp'] <= $timestamp) {
            $previous = $event;
            continue;
        }
        $next = $event;
        break;
    }
    if (!is_array($previous) || !is_array($next)) {
        throw new RuntimeException('Moon phase interval could not be calculated.');
    }

    $nextFull = sun_moon_phase_timestamp(((float) $previous['k']) + 0.5, true);
    if ($nextFull <= $timestamp) {
        $nextFull = sun_moon_phase_timestamp(((float) $previous['k']) + 1.5, true);
    }
    return [
        'previous_new' => (int) $previous['timestamp'],
        'next_new' => (int) $next['timestamp'],
        'next_full' => $nextFull,
    ];
}

/** @return array{age_days:float,phase_fraction:float,phase_label:string,illumination_percent:int,next_full_moon_at:string,days_until_full_moon:float,waxing:bool} */
function sun_moon_moon_info(int $timestamp, DateTimeZone $timezone): array
{
    $events = sun_moon_phase_events($timestamp);
    $interval = max(1, $events['next_new'] - $events['previous_new']);
    $phase = max(0.0, min(0.999999, ($timestamp - $events['previous_new']) / $interval));
    $ageDays = ($timestamp - $events['previous_new']) / 86400.0;
    $sector = ((int) floor(($phase * 8.0) + 0.5)) % 8;
    $labels = [
        '新月',
        '満ちていく三日月',
        '上弦',
        '満ちていく月',
        '満月',
        '欠けていく月',
        '下弦',
        '欠けていく三日月',
    ];
    $illumination = (int) round((1.0 - cos(2.0 * M_PI * $phase)) * 50.0);
    $illumination = max(0, min(100, $illumination));
    $nextFull = (new DateTimeImmutable('@' . $events['next_full']))->setTimezone($timezone);
    $secondsUntilFull = max(0, $events['next_full'] - $timestamp);

    return [
        'age_days' => round($ageDays, 1),
        'phase_fraction' => round($phase, 6),
        'phase_label' => $labels[$sector],
        'illumination_percent' => $illumination,
        'next_full_moon_at' => $nextFull->format(DATE_ATOM),
        'days_until_full_moon' => round($secondsUntilFull / 86400.0, 1),
        'waxing' => $timestamp < $events['next_full'],
    ];
}

function sun_moon_sun_time(mixed $value, DateTimeZone $timezone): ?string
{
    if (!is_int($value)) {
        return null;
    }
    return (new DateTimeImmutable('@' . $value))->setTimezone($timezone)->format('H:i');
}

/** @return array<string,mixed> */
function sun_moon_current(array $config, ?int $timestamp = null): array
{
    $config = sun_moon_widget_config_from_storage(dashboard_widget_encode_config($config));
    if ($config['location_name'] === '') {
        throw new InvalidArgumentException('Sun / Moon location is invalid.');
    }
    $timezone = new DateTimeZone($config['timezone']);
    $now = $timestamp ?? time();
    $localNow = (new DateTimeImmutable('@' . $now))->setTimezone($timezone);
    $localNoon = new DateTimeImmutable($localNow->format('Y-m-d') . ' 12:00:00', $timezone);
    $sun = date_sun_info($localNoon->getTimestamp(), $config['latitude'], $config['longitude']);

    return [
        'location_name' => $config['location_name'],
        'timezone' => $config['timezone'],
        'local_date' => $localNow->format('Y-m-d'),
        'sunrise' => sun_moon_sun_time($sun['sunrise'] ?? null, $timezone),
        'sunset' => sun_moon_sun_time($sun['sunset'] ?? null, $timezone),
        'civil_twilight_begin' => sun_moon_sun_time($sun['civil_twilight_begin'] ?? null, $timezone),
        'civil_twilight_end' => sun_moon_sun_time($sun['civil_twilight_end'] ?? null, $timezone),
        'solar_transit' => sun_moon_sun_time($sun['transit'] ?? null, $timezone),
        'moon' => sun_moon_moon_info($now, $timezone),
        'updated_at' => $localNow->format(DATE_ATOM),
    ];
}

function sun_moon_widget_create(int $ownerId, int $location, string $style, int $width, array $config, int $height = 1): int
{
    if ($ownerId <= 0 || dashboard_widget_validate_location($location) === null
        || app_normalize_content_style($style) === null || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null
        || sun_moon_widget_config_from_storage(dashboard_widget_encode_config($config))['location_name'] === '') {
        throw new InvalidArgumentException('Sun / Moon Widget settings are invalid.');
    }
    return information_widget_create_record($ownerId, $location, 'sun_moon', $style, $width, $height, $config);
}

function sun_moon_widget_update(int $ownerId, int $widgetId, string $style, int $width, array $config, int $height = 1): bool
{
    if ($ownerId <= 0 || $widgetId <= 0 || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null || dashboard_widget_validate_height($height) === null
        || sun_moon_widget_config_from_storage(dashboard_widget_encode_config($config))['location_name'] === '') {
        throw new InvalidArgumentException('Sun / Moon Widget settings are invalid.');
    }
    return information_widget_update_record($ownerId, $widgetId, 'sun_moon', $style, $width, $height, $config);
}

function sun_moon_widget_delete(int $ownerId, int $widgetId): bool
{
    return information_widget_delete_record($ownerId, $widgetId, 'sun_moon');
}

/** @return array<string,mixed>|null */
function sun_moon_widget_owned_config(int $ownerId, int $widgetId): ?array
{
    return information_widget_owned_config($ownerId, $widgetId, 'sun_moon', 'sun_moon_widget_config_from_storage');
}
