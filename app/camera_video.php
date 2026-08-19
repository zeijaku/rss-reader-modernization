<?php

declare(strict_types=1);

/**
 * V1.17-B Camera / Video Widget foundation.
 *
 * This phase stores and validates the media source only. Snapshot / YouTube /
 * video renderers are added in later V1.17 phases. No outbound HTTP request is
 * performed here.
 */

/** @return list<string> */
function camera_video_source_types(): array
{
    return ['auto', 'snapshot', 'youtube', 'video', 'mjpeg', 'hls', 'iframe'];
}

function camera_video_validate_source_type(mixed $value): ?string
{
    return is_string($value) && in_array($value, camera_video_source_types(), true) ? $value : null;
}

function camera_video_validate_title(mixed $value): ?string
{
    return app_validate_text($value, 64, false);
}

function camera_video_validate_media_url(mixed $value): ?string
{
    return app_normalize_http_url($value, 2048, false, false);
}

function camera_video_validate_source_page_url(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return '';
    }
    return app_normalize_http_url($value, 2048, false, true);
}

function camera_video_validate_refresh_seconds(mixed $value): ?int
{
    if (is_int($value)) {
        $seconds = $value;
    } elseif (is_string($value) && preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value) === 1) {
        $seconds = (int) $value;
    } else {
        return null;
    }

    return in_array($seconds, [0, 10, 30, 60, 300, 600], true) ? $seconds : null;
}

/** @return array{schema:int,title:string,source_type:string,media_url:string,refresh_seconds:int,source_page_url:string} */
function camera_video_defaults(): array
{
    return [
        'schema' => 1,
        'title' => 'Camera / Video',
        'source_type' => 'auto',
        'media_url' => '',
        'refresh_seconds' => 600,
        'source_page_url' => '',
    ];
}

/** @return array{schema:int,title:string,source_type:string,media_url:string,refresh_seconds:int,source_page_url:string}|null */
function camera_video_config_from_input(array $input): ?array
{
    $title = camera_video_validate_title($input['camera_title'] ?? null);
    $sourceType = camera_video_validate_source_type($input['camera_source_type'] ?? null);
    $mediaUrl = camera_video_validate_media_url($input['camera_url'] ?? null);
    $refreshSeconds = camera_video_validate_refresh_seconds($input['camera_refresh_seconds'] ?? null);
    $sourcePageUrl = camera_video_validate_source_page_url($input['camera_source_page_url'] ?? null);

    if ($title === null || $sourceType === null || $mediaUrl === null
        || $refreshSeconds === null || $sourcePageUrl === null) {
        return null;
    }

    return [
        'schema' => 1,
        'title' => $title,
        'source_type' => $sourceType,
        'media_url' => $mediaUrl,
        'refresh_seconds' => $refreshSeconds,
        'source_page_url' => $sourcePageUrl,
    ];
}

/** @return array{schema:int,title:string,source_type:string,media_url:string,refresh_seconds:int,source_page_url:string} */
function camera_video_config_from_storage(mixed $value): array
{
    $defaults = camera_video_defaults();
    $config = dashboard_widget_decode_config($value);

    $title = camera_video_validate_title($config['title'] ?? null);
    $sourceType = camera_video_validate_source_type($config['source_type'] ?? null);
    $mediaUrl = camera_video_validate_media_url($config['media_url'] ?? null);
    $refreshSeconds = camera_video_validate_refresh_seconds($config['refresh_seconds'] ?? null);
    $sourcePageUrl = camera_video_validate_source_page_url($config['source_page_url'] ?? null);

    return [
        'schema' => 1,
        'title' => $title ?? $defaults['title'],
        'source_type' => $sourceType ?? $defaults['source_type'],
        'media_url' => $mediaUrl ?? $defaults['media_url'],
        'refresh_seconds' => $refreshSeconds ?? $defaults['refresh_seconds'],
        'source_page_url' => $sourcePageUrl ?? $defaults['source_page_url'],
    ];
}

function camera_video_source_label(string $sourceType): string
{
    return match ($sourceType) {
        'snapshot' => 'Snapshot',
        'youtube' => 'YouTube',
        'video' => 'Video File',
        'mjpeg' => 'MJPEG',
        'hls' => 'HLS',
        'iframe' => 'iframe',
        default => 'Auto',
    };
}

function camera_video_refresh_label(int $seconds): string
{
    return match ($seconds) {
        10 => '10秒',
        30 => '30秒',
        60 => '1分',
        300 => '5分',
        600 => '10分',
        default => 'OFF',
    };
}

/** @return array<string,mixed>|null */
function camera_video_lock_owned_widget(PDO $pdo, int $ownerId, int $widgetId): ?array
{
    if ($ownerId <= 0 || $widgetId <= 0) {
        return null;
    }

    $sql = 'SELECT * FROM ' . db_table_identifier('dashboard_widget') . ' '
        . "WHERE widget_id = :widget_id AND widget_owner = :owner AND widget_type = 'camera_video' AND widget_flag = 0";
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':widget_id' => $widgetId, ':owner' => $ownerId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

/** @return list<array<string,mixed>> */
function camera_video_list_widgets(int $ownerId, int $location): array
{
    if ($ownerId <= 0 || dashboard_widget_validate_location($location) === null) {
        return [];
    }

    $stmt = conn_db()->prepare(
        'SELECT widget_id, widget_location, widget_sort_order, widget_width, widget_height, widget_style, widget_config '
        . 'FROM ' . db_table_identifier('dashboard_widget') . ' '
        . "WHERE widget_owner = :owner AND widget_location = :location AND widget_type = 'camera_video' AND widget_flag = 0 "
        . 'ORDER BY widget_sort_order ASC, widget_id ASC'
    );
    $stmt->execute([':owner' => $ownerId, ':location' => $location]);

    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $widgetId = app_validate_positive_int($row['widget_id'] ?? null);
        $widgetLocation = dashboard_widget_validate_location($row['widget_location'] ?? null);
        $sortOrder = dashboard_widget_non_negative_int($row['widget_sort_order'] ?? null);
        $width = dashboard_widget_validate_width($row['widget_width'] ?? null);
        $height = dashboard_widget_validate_height($row['widget_height'] ?? null);
        $style = app_normalize_content_style($row['widget_style'] ?? null);
        if ($widgetId === null || $widgetLocation === null || $sortOrder === null
            || $width === null || $height === null || $style === null) {
            continue;
        }
        $config = camera_video_config_from_storage($row['widget_config'] ?? null);
        if ($config['media_url'] === '') {
            continue;
        }
        $result[] = [
            'widget_id' => $widgetId,
            'widget_location' => $widgetLocation,
            'widget_sort_order' => $sortOrder,
            'widget_width' => $width,
            'widget_height' => $height,
            'widget_style' => $style,
            'widget_config' => $config,
        ];
    }
    return $result;
}

/** @param array{schema:int,title:string,source_type:string,media_url:string,refresh_seconds:int,source_page_url:string} $config */
function camera_video_create_widget(
    int $ownerId,
    int $location,
    string $style,
    int $width,
    int $height,
    array $config
): int {
    $validatedConfig = camera_video_config_from_input([
        'camera_title' => $config['title'] ?? null,
        'camera_source_type' => $config['source_type'] ?? null,
        'camera_url' => $config['media_url'] ?? null,
        'camera_refresh_seconds' => $config['refresh_seconds'] ?? null,
        'camera_source_page_url' => $config['source_page_url'] ?? null,
    ]);
    if ($ownerId <= 0
        || dashboard_widget_validate_location($location) === null
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null
        || $validatedConfig === null) {
        throw new InvalidArgumentException('Camera / Video Widget settings are invalid.');
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
            . '(widget_owner, widget_location, widget_type, widget_reference_id, widget_sort_order, '
            . 'widget_width, widget_height, widget_style, widget_config, widget_flag, widget_created_at, widget_updated_at) '
            . "VALUES (:owner, :location, 'camera_video', NULL, :sort_order, :width, :height, :style, :config, 0, :created_at, :updated_at)"
        );
        $stmt->execute([
            ':owner' => $ownerId,
            ':location' => $location,
            ':sort_order' => dashboard_widget_next_sort_order($pdo, $ownerId, $location),
            ':width' => $width,
            ':height' => $height,
            ':style' => $style,
            ':config' => dashboard_widget_encode_config($validatedConfig),
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

/** @param array{schema:int,title:string,source_type:string,media_url:string,refresh_seconds:int,source_page_url:string} $config */
function camera_video_update_widget(
    int $ownerId,
    int $widgetId,
    string $style,
    int $width,
    int $height,
    array $config
): bool {
    $validatedConfig = camera_video_config_from_input([
        'camera_title' => $config['title'] ?? null,
        'camera_source_type' => $config['source_type'] ?? null,
        'camera_url' => $config['media_url'] ?? null,
        'camera_refresh_seconds' => $config['refresh_seconds'] ?? null,
        'camera_source_page_url' => $config['source_page_url'] ?? null,
    ]);
    if ($ownerId <= 0 || $widgetId <= 0
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null
        || $validatedConfig === null) {
        throw new InvalidArgumentException('Camera / Video Widget settings are invalid.');
    }

    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }

    try {
        if (camera_video_lock_owned_widget($pdo, $ownerId, $widgetId) === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_width = :width, widget_height = :height, widget_style = :style, '
            . 'widget_config = :config, widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
            . "AND widget_type = 'camera_video' AND widget_flag = 0"
        );
        $stmt->execute([
            ':width' => $width,
            ':height' => $height,
            ':style' => $style,
            ':config' => dashboard_widget_encode_config($validatedConfig),
            ':updated_at' => app_now(),
            ':widget_id' => $widgetId,
            ':owner' => $ownerId,
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

function camera_video_delete_widget(int $ownerId, int $widgetId): bool
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
        if (camera_video_lock_owned_widget($pdo, $ownerId, $widgetId) === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_flag = 1, widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
            . "AND widget_type = 'camera_video' AND widget_flag = 0"
        );
        $stmt->execute([
            ':updated_at' => app_now(),
            ':widget_id' => $widgetId,
            ':owner' => $ownerId,
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

/** @return array{status:int,body:array<string,mixed>} */
function camera_video_api_dispatch(string $action, int $userId, array $input): array
{
    return match ($action) {
        'camera.widget.list' => camera_video_api_list($userId, $input),
        'camera.widget.create' => camera_video_api_create($userId, $input),
        'camera.widget.update' => camera_video_api_update($userId, $input),
        'camera.widget.delete' => camera_video_api_delete($userId, $input),
        default => api_error('unknown_action', 'Unknown API action.', 400),
    };
}

/** @return array{status:int,body:array<string,mixed>} */
function camera_video_api_list(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    if ($location === null) {
        return api_validation_error('widget_location must be 0, 1, 2, or 3.');
    }

    try {
        $widgets = camera_video_list_widgets($userId, $location);
    } catch (PDOException $exception) {
        error_log('Camera / Video Widget list failed: ' . $exception->getMessage());
        return api_error('dashboard_widget_unavailable', 'Dashboard Widget migration is required.', 503);
    }
    return api_success(['widgets' => $widgets]);
}

/** @return array{status:int,body:array<string,mixed>} */
function camera_video_api_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = camera_video_config_from_input($input);

    if ($location === null || $style === null || $width === null || $height === null || $config === null) {
        return api_validation_error('Camera / Video Widget settings are invalid.');
    }

    try {
        $widgetId = camera_video_create_widget($userId, $location, $style, $width, $height, $config);
    } catch (PDOException $exception) {
        error_log('Camera / Video Widget create failed: ' . $exception->getMessage());
        return api_error('dashboard_widget_unavailable', 'Dashboard Widget migration is required.', 503);
    }
    return api_success(['widget_id' => $widgetId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function camera_video_api_update(int $userId, array $input): array
{
    $widgetId = app_validate_positive_int($input['widget_id'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = camera_video_config_from_input($input);

    if ($widgetId === null || $style === null || $width === null || $height === null || $config === null) {
        return api_validation_error('Camera / Video Widget settings are invalid.');
    }

    try {
        if (!camera_video_update_widget($userId, $widgetId, $style, $width, $height, $config)) {
            return api_error('not_found', 'Camera / Video Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Camera / Video Widget update failed: ' . $exception->getMessage());
        return api_error('dashboard_widget_unavailable', 'Dashboard Widget migration is required.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function camera_video_api_delete(int $userId, array $input): array
{
    $widgetId = app_validate_positive_int($input['widget_id'] ?? null);
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }

    try {
        if (!camera_video_delete_widget($userId, $widgetId)) {
            return api_error('not_found', 'Camera / Video Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Camera / Video Widget delete failed: ' . $exception->getMessage());
        return api_error('dashboard_widget_unavailable', 'Dashboard Widget migration is required.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}
