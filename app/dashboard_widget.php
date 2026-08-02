<?php

declare(strict_types=1);

/**
 * V1.1-D Dashboard Widget共通基盤。
 * Feed本体は従来通りcontentを正本とし、このTableでは配置情報だけを扱う。
 */

/** @return list<string> */
function dashboard_widget_types(): array
{
    return ['feed', 'clock', 'memo', 'task', 'calendar'];
}

function dashboard_widget_validate_type(mixed $value): ?string
{
    if (!is_string($value) || !in_array($value, dashboard_widget_types(), true)) {
        return null;
    }
    return $value;
}

function dashboard_widget_non_negative_int(mixed $value): ?int
{
    if (is_int($value)) {
        return $value >= 0 ? $value : null;
    }
    if (!is_string($value) || preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value) !== 1) {
        return null;
    }
    $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    return is_int($number) ? $number : null;
}

function dashboard_widget_validate_location(mixed $value): ?int
{
    $location = dashboard_widget_non_negative_int($value);
    return $location !== null && $location <= 3 ? $location : null;
}

function dashboard_widget_validate_width(mixed $value): ?int
{
    $width = app_validate_positive_int($value);
    return $width !== null && $width <= 4 ? $width : null;
}

/** @return array<string,mixed> */
function dashboard_widget_decode_config(mixed $value): array
{
    if ($value === null || $value === '') {
        return [];
    }
    if (!is_string($value) || strlen($value) > 4096) {
        return [];
    }

    try {
        $decoded = json_decode($value, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return [];
    }

    return is_array($decoded) && !array_is_list($decoded) ? $decoded : [];
}

/** @param array<string,mixed> $config */
function dashboard_widget_encode_config(array $config): string
{
    $encoded = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (strlen($encoded) > 4096) {
        throw new InvalidArgumentException('Widget config is too large.');
    }
    return $encoded;
}

function dashboard_widget_width_class(int $width): string
{
    return match ($width) {
        2 => 'col-12 col-md-12 col-lg-6',
        3 => 'col-12 col-lg-9',
        4 => 'col-12',
        default => 'col-12 col-md-6 col-lg-3',
    };
}

/** @return array<string,mixed>|null */
function dashboard_widget_normalize_row(array $row): ?array
{
    $widgetId = app_validate_positive_int($row['widget_id'] ?? null);
    $owner = app_validate_positive_int($row['widget_owner'] ?? null);
    $location = dashboard_widget_validate_location($row['widget_location'] ?? null);
    $type = dashboard_widget_validate_type($row['widget_type'] ?? null);
    $sortOrder = dashboard_widget_non_negative_int($row['widget_sort_order'] ?? null);
    $width = dashboard_widget_validate_width($row['widget_width'] ?? null);
    $style = app_normalize_content_style($row['widget_style'] ?? null);

    if ($widgetId === null || $owner === null || $location === null || $type === null || $sortOrder === null || $width === null || $style === null) {
        return null;
    }

    $referenceId = $row['widget_reference_id'] === null
        ? null
        : app_validate_positive_int($row['widget_reference_id'] ?? null);
    if ($type === 'feed' && $referenceId === null) {
        return null;
    }

    $row['widget_id'] = $widgetId;
    $row['widget_owner'] = $owner;
    $row['widget_location'] = $location;
    $row['widget_type'] = $type;
    $row['widget_reference_id'] = $referenceId;
    $row['widget_sort_order'] = $sortOrder;
    $row['widget_width'] = $width;
    $row['widget_style'] = $style;
    $row['widget_config_data'] = dashboard_widget_decode_config($row['widget_config'] ?? null);
    $row['widget_width_class'] = dashboard_widget_width_class($width);

    return $row;
}

/** @return list<array<string,mixed>> */
function search_dashboard_widgets(int $ownerId, int $location): array
{
    if ($ownerId <= 0 || dashboard_widget_validate_location($location) === null) {
        return [];
    }

    $stmt = conn_db()->prepare(
        'SELECT w.widget_id, w.widget_owner, w.widget_location, w.widget_type, '
        . 'w.widget_reference_id, w.widget_sort_order, w.widget_width, w.widget_style, '
        . 'w.widget_config, w.widget_flag, w.widget_created_at, w.widget_updated_at, '
        . 'c.content_id, c.content_date, c.content_flag, c.content_owner, c.content_location, '
        . 'c.content_style, c.content_value '
        . 'FROM ' . db_table_identifier('dashboard_widget') . ' w '
        . 'LEFT JOIN ' . db_table_identifier('content') . ' c '
        . "ON w.widget_type = 'feed' AND w.widget_reference_id = c.content_id AND w.widget_owner = c.content_owner "
        . 'WHERE w.widget_owner = :owner AND w.widget_location = :location AND w.widget_flag = 0 '
        . 'ORDER BY w.widget_sort_order ASC, w.widget_id ASC'
    );
    $stmt->execute([':owner' => $ownerId, ':location' => $location]);

    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $normalized = dashboard_widget_normalize_row($row);
        if ($normalized === null) {
            continue;
        }
        if ($normalized['widget_type'] === 'feed') {
            if ((int) ($normalized['content_flag'] ?? 1) !== 0 || (int) ($normalized['content_owner'] ?? 0) !== $ownerId) {
                continue;
            }
        }
        $result[] = $normalized;
    }

    return $result;
}

/** @return list<array<string,mixed>> */
function dashboard_widget_public_list(int $ownerId, int $location): array
{
    $result = [];
    foreach (search_dashboard_widgets($ownerId, $location) as $row) {
        $result[] = [
            'widget_id' => $row['widget_id'],
            'widget_location' => $row['widget_location'],
            'widget_type' => $row['widget_type'],
            'widget_reference_id' => $row['widget_reference_id'],
            'widget_sort_order' => $row['widget_sort_order'],
            'widget_width' => $row['widget_width'],
            'widget_style' => $row['widget_style'],
            'widget_config' => $row['widget_config_data'],
        ];
    }
    return $result;
}

/** @return array<string,mixed>|null */
function dashboard_widget_lock_owned_content(PDO $pdo, int $ownerId, int $contentId): ?array
{
    $sql = 'SELECT * FROM ' . db_table_identifier('content') . ' '
        . 'WHERE content_id = :content_id AND content_owner = :owner AND content_flag = 0';
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':content_id' => $contentId, ':owner' => $ownerId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function dashboard_widget_insert_feed(PDO $pdo, int $ownerId, int $contentId, int $location, string $style, string $createdAt): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO ' . db_table_identifier('dashboard_widget') . ' '
        . '(widget_owner, widget_location, widget_type, widget_reference_id, widget_sort_order, '
        . 'widget_width, widget_style, widget_config, widget_flag, widget_created_at, widget_updated_at) '
        . "VALUES (:owner, :location, 'feed', :reference_id, :sort_order, 1, :style, NULL, 0, :created_at, :updated_at)"
    );
    $stmt->execute([
        ':owner' => $ownerId,
        ':location' => $location,
        ':reference_id' => $contentId,
        ':sort_order' => $contentId,
        ':style' => $style,
        ':created_at' => $createdAt,
        ':updated_at' => $createdAt,
    ]);
    return (int) $pdo->lastInsertId();
}

function dashboard_widget_ensure_feed(PDO $pdo, array $content): int
{
    $ownerId = (int) ($content['content_owner'] ?? 0);
    $contentId = (int) ($content['content_id'] ?? 0);
    $location = dashboard_widget_validate_location($content['content_location'] ?? null);
    $style = app_normalize_content_style($content['content_style'] ?? null);
    if ($ownerId <= 0 || $contentId <= 0 || $location === null || $style === null) {
        throw new InvalidArgumentException('Feed Widget source content is invalid.');
    }

    $stmt = $pdo->prepare(
        'SELECT widget_id FROM ' . db_table_identifier('dashboard_widget') . ' '
        . "WHERE widget_owner = :owner AND widget_type = 'feed' AND widget_reference_id = :reference_id LIMIT 1"
    );
    $stmt->execute([':owner' => $ownerId, ':reference_id' => $contentId]);
    $widgetId = $stmt->fetchColumn();
    $now = app_now();

    if ($widgetId !== false) {
        $update = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_location = :location, widget_style = :style, widget_flag = 0, widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner'
        );
        $update->execute([
            ':location' => $location,
            ':style' => $style,
            ':updated_at' => $now,
            ':widget_id' => (int) $widgetId,
            ':owner' => $ownerId,
        ]);
        return (int) $widgetId;
    }

    $createdAt = isset($content['content_date']) && is_string($content['content_date']) && $content['content_date'] !== ''
        ? $content['content_date']
        : $now;
    return dashboard_widget_insert_feed($pdo, $ownerId, $contentId, $location, $style, $createdAt);
}

function dashboard_widget_create_feed(int $ownerId, string $url, string $style, int $location): int
{
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }

    try {
        $contentId = entry_content($ownerId, $url, $style, $location);
        dashboard_widget_insert_feed($pdo, $ownerId, $contentId, $location, $style, app_now());
        if ($started) {
            $pdo->commit();
        }
        return $contentId;
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function dashboard_widget_update_feed(int $ownerId, int $contentId, string $url, string $style): bool
{
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }

    try {
        $content = dashboard_widget_lock_owned_content($pdo, $ownerId, $contentId);
        if ($content === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        update_content_owned($ownerId, $contentId, $url, $style);
        $content['content_value'] = $url;
        $content['content_style'] = $style;
        dashboard_widget_ensure_feed($pdo, $content);
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

function dashboard_widget_delete_feed(int $ownerId, int $contentId): bool
{
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }

    try {
        if (dashboard_widget_lock_owned_content($pdo, $ownerId, $contentId) === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        delete_content_owned($ownerId, $contentId);
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_flag = 1, widget_updated_at = :updated_at '
            . "WHERE widget_owner = :owner AND widget_type = 'feed' AND widget_reference_id = :reference_id AND widget_flag = 0"
        );
        $stmt->execute([
            ':updated_at' => app_now(),
            ':owner' => $ownerId,
            ':reference_id' => $contentId,
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
