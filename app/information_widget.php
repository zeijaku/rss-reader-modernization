<?php

declare(strict_types=1);

/**
 * Small shared helpers for V1.15 Information Widgets.
 *
 * Keep the individual widgets independent. This file only owns the parts that
 * became genuinely identical across Weather / Sun & Moon / Air Quality /
 * Earthquake: location validation, dashboard_widget row persistence and the
 * simple JSON cache envelope used by Weather/Air Quality.
 */

/** @return list<string> */
function information_widget_types(): array
{
    return ['weather', 'earthquake', 'sun_moon', 'air_quality'];
}

function information_widget_validate_type(mixed $value): ?string
{
    return is_string($value) && in_array($value, information_widget_types(), true) ? $value : null;
}

function information_widget_validate_location_query(mixed $value): ?string
{
    return app_validate_text($value, 80, false);
}

function information_widget_validate_location_name(mixed $value): ?string
{
    return app_validate_text($value, 80, false);
}

function information_widget_validate_latitude(mixed $value): ?float
{
    if (!is_numeric($value)) {
        return null;
    }
    $number = (float) $value;
    return is_finite($number) && $number >= -90.0 && $number <= 90.0 ? $number : null;
}

function information_widget_validate_longitude(mixed $value): ?float
{
    if (!is_numeric($value)) {
        return null;
    }
    $number = (float) $value;
    return is_finite($number) && $number >= -180.0 && $number <= 180.0 ? $number : null;
}

function information_widget_validate_timezone(mixed $value): ?string
{
    if (!is_string($value) || $value === '' || strlen($value) > 64
        || preg_match('/\A[A-Za-z0-9_+\-\/]+\z/D', $value) !== 1) {
        return null;
    }
    try {
        new DateTimeZone($value);
    } catch (Throwable) {
        return null;
    }
    return $value;
}

/** @return array{name:string,latitude:float,longitude:float,timezone:string}|null */
function information_widget_normalize_location(mixed $value): ?array
{
    if (!is_array($value)) {
        return null;
    }
    $name = information_widget_validate_location_name($value['name'] ?? null);
    $latitude = information_widget_validate_latitude($value['latitude'] ?? null);
    $longitude = information_widget_validate_longitude($value['longitude'] ?? null);
    $timezone = information_widget_validate_timezone($value['timezone'] ?? null);
    if ($name === null || $latitude === null || $longitude === null || $timezone === null) {
        return null;
    }
    return [
        'name' => $name,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'timezone' => $timezone,
    ];
}

/** @return array{name:string,latitude:float,longitude:float,timezone:string}|null */
function information_widget_resolve_location(string $query, ?callable $geocoder = null): ?array
{
    $query = information_widget_validate_location_query($query);
    if ($query === null) {
        return null;
    }
    $resolver = $geocoder ?? 'weather_resolve_location';
    if (!is_callable($resolver)) {
        return null;
    }
    return information_widget_normalize_location($resolver($query));
}

function information_widget_base_settings_valid(
    int $ownerId,
    int $location,
    string $type,
    string $style,
    int $width,
    int $height
): bool {
    return $ownerId > 0
        && information_widget_validate_type($type) !== null
        && dashboard_widget_validate_location($location) !== null
        && app_normalize_content_style($style) !== null
        && dashboard_widget_validate_width($width) !== null
        && dashboard_widget_validate_height($height) !== null;
}

/** @param array<string,mixed> $config */
function information_widget_create_record(
    int $ownerId,
    int $location,
    string $type,
    string $style,
    int $width,
    int $height,
    array $config
): int {
    if (!information_widget_base_settings_valid($ownerId, $location, $type, $style, $width, $height)) {
        throw new InvalidArgumentException('Information Widget settings are invalid.');
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
            . 'VALUES (:owner, :location, :type, NULL, :sort_order, :width, :height, :style, :config, 0, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':owner' => $ownerId,
            ':location' => $location,
            ':type' => $type,
            ':sort_order' => dashboard_widget_next_sort_order($pdo, $ownerId, $location),
            ':width' => $width,
            ':height' => $height,
            ':style' => $style,
            ':config' => dashboard_widget_encode_config($config),
            ':created_at' => $now,
            ':updated_at' => $now,
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

/** @param array<string,mixed>|null $config */
function information_widget_update_record(
    int $ownerId,
    int $widgetId,
    string $type,
    string $style,
    int $width,
    int $height,
    ?array $config
): bool {
    if ($ownerId <= 0 || $widgetId <= 0 || information_widget_validate_type($type) === null
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null) {
        throw new InvalidArgumentException('Information Widget settings are invalid.');
    }

    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        if (dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, $type) === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }

        $sql = 'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_width = :width, widget_height = :height, widget_style = :style, ';
        if ($config !== null) {
            $sql .= 'widget_config = :config, ';
        }
        $sql .= 'widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner AND widget_type = :type AND widget_flag = 0';
        $stmt = $pdo->prepare($sql);
        $params = [
            ':width' => $width,
            ':height' => $height,
            ':style' => $style,
            ':updated_at' => app_now(),
            ':widget_id' => $widgetId,
            ':owner' => $ownerId,
            ':type' => $type,
        ];
        if ($config !== null) {
            $params[':config'] = dashboard_widget_encode_config($config);
        }
        $stmt->execute($params);
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

function information_widget_delete_record(int $ownerId, int $widgetId, string $type): bool
{
    if ($ownerId <= 0 || $widgetId <= 0 || information_widget_validate_type($type) === null) {
        return false;
    }
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        if (dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, $type) === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_flag = 1, widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner AND widget_type = :type AND widget_flag = 0'
        );
        $stmt->execute([
            ':updated_at' => app_now(),
            ':widget_id' => $widgetId,
            ':owner' => $ownerId,
            ':type' => $type,
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

/** @return array<string,mixed>|null */
function information_widget_owned_record(int $ownerId, int $widgetId, string $type): ?array
{
    if ($ownerId <= 0 || $widgetId <= 0 || information_widget_validate_type($type) === null) {
        return null;
    }
    $stmt = conn_db()->prepare(
        'SELECT widget_id, widget_location, widget_width, widget_height, widget_style, widget_config FROM '
        . db_table_identifier('dashboard_widget') . ' '
        . 'WHERE widget_id = :widget_id AND widget_owner = :owner AND widget_type = :type AND widget_flag = 0'
    );
    $stmt->execute([':widget_id' => $widgetId, ':owner' => $ownerId, ':type' => $type]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

/** @return array<string,mixed>|null */
function information_widget_owned_config(
    int $ownerId,
    int $widgetId,
    string $type,
    callable $normalizer,
    string $requiredKey = 'location_name'
): ?array {
    $row = information_widget_owned_record($ownerId, $widgetId, $type);
    if ($row === null || !is_string($row['widget_config'] ?? null)) {
        return null;
    }
    $config = $normalizer($row['widget_config']);
    if (!is_array($config)) {
        return null;
    }
    if ($requiredKey !== '' && trim((string) ($config[$requiredKey] ?? '')) === '') {
        return null;
    }
    return $config;
}

/** @return array<string,mixed>|null */
function information_widget_cache_read(
    string $path,
    string $payloadKey,
    int $freshTtl,
    int $staleTtl,
    int $maxBytes,
    bool $allowStale = false,
    bool $requireSchema = false
): ?array {
    $size = is_file($path) ? filesize($path) : false;
    if ($size === false || $size < 1 || $size > $maxBytes) {
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
    if (!is_array($cache)
        || ($requireSchema && ($cache['schema'] ?? null) !== 1)
        || !is_int($cache['cached_at'] ?? null)
        || !is_array($cache[$payloadKey] ?? null)) {
        return null;
    }
    $age = time() - $cache['cached_at'];
    $maxAge = $allowStale ? $staleTtl : $freshTtl;
    if ($age < 0 || $age > $maxAge) {
        return null;
    }
    return $cache[$payloadKey];
}

/** @param array<string,mixed> $payload */
function information_widget_cache_write(
    string $dir,
    string $path,
    string $tempPrefix,
    string $payloadKey,
    array $payload,
    int $maxBytes
): void {
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        return;
    }
    $encoded = json_encode([
        'schema' => 1,
        'cached_at' => time(),
        $payloadKey => $payload,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded) || strlen($encoded) > $maxBytes) {
        return;
    }
    $tmp = tempnam($dir, $tempPrefix);
    if (!is_string($tmp)) {
        return;
    }
    if (file_put_contents($tmp, $encoded . "\n", LOCK_EX) !== false) {
        @chmod($tmp, 0640);
        @rename($tmp, $path);
    }
    if (is_file($tmp)) {
        @unlink($tmp);
    }
}
