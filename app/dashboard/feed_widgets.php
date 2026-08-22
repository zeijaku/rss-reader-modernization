<?php

declare(strict_types=1);

/**
 * V1.19-B broad module extracted from the v1.18.0 facade.
 * Function bodies are intentionally kept unchanged.
 */

/** @return array{schema:int,item_limit:int|string} */
function dashboard_widget_feed_defaults(): array
{
    return [
        'schema' => 1,
        'item_limit' => 'auto',
    ];
}

function dashboard_widget_validate_feed_item_limit(mixed $value): int|string|null
{
    if ($value === null || $value === '' || $value === 'auto') {
        return 'auto';
    }

    $limit = app_validate_positive_int($value);
    return $limit !== null && $limit <= 30 ? $limit : null;
}

/** @return array{schema:int,item_limit:int|string}|null */
function dashboard_widget_feed_config_from_input(array $input): ?array
{
    $itemLimit = dashboard_widget_validate_feed_item_limit($input['feed_item_limit'] ?? null);
    if ($itemLimit === null) {
        return null;
    }

    return [
        'schema' => 1,
        'item_limit' => $itemLimit,
    ];
}

/** @return array{schema:int,item_limit:int|string} */
function dashboard_widget_feed_config_from_storage(mixed $value): array
{
    $defaults = dashboard_widget_feed_defaults();
    $config = dashboard_widget_decode_config($value);
    $itemLimit = dashboard_widget_validate_feed_item_limit($config['item_limit'] ?? null);
    if ($itemLimit === null) {
        return $defaults;
    }

    return [
        'schema' => 1,
        'item_limit' => $itemLimit,
    ];
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

function dashboard_widget_insert_feed(PDO $pdo, int $ownerId, int $contentId, int $location, string $style, string $createdAt, int $width = 1, int $height = 1, mixed $itemLimit = null): int
{
    $config = dashboard_widget_feed_config_from_input(['feed_item_limit' => $itemLimit]);
    if ($config === null) {
        throw new InvalidArgumentException('Feed Widget item limit is invalid.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO ' . db_table_identifier('dashboard_widget') . ' '
        . '(widget_owner, widget_location, widget_type, widget_reference_id, widget_sort_order, '
        . 'widget_width, widget_height, widget_style, widget_config, widget_flag, widget_created_at, widget_updated_at) '
        . "VALUES (:owner, :location, 'feed', :reference_id, :sort_order, :width, :height, :style, :config, 0, :created_at, :updated_at)"
    );
    $stmt->execute([
        ':owner' => $ownerId,
        ':location' => $location,
        ':reference_id' => $contentId,
        ':sort_order' => dashboard_widget_next_sort_order($pdo, $ownerId, $location),
        ':width' => $width,
        ':height' => $height,
        ':style' => $style,
        ':config' => dashboard_widget_encode_config($config),
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

function dashboard_widget_create_feed(int $ownerId, string $url, string $style, int $location, int $width = 1, int $height = 1, mixed $itemLimit = null): int
{
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }

    try {
        $contentId = entry_content($ownerId, $url, $style, $location);
        if (dashboard_widget_validate_width($width) === null || dashboard_widget_validate_height($height) === null) {
            throw new InvalidArgumentException('Feed Widget size is invalid.');
        }
        if (dashboard_widget_validate_feed_item_limit($itemLimit) === null) {
            throw new InvalidArgumentException('Feed Widget item limit is invalid.');
        }
        dashboard_widget_insert_feed($pdo, $ownerId, $contentId, $location, $style, app_now(), $width, $height, $itemLimit);
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

function dashboard_widget_update_feed(int $ownerId, int $contentId, string $url, string $style, int $width = 1, int $height = 1, mixed $itemLimit = null): bool
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
        if (dashboard_widget_validate_width($width) === null || dashboard_widget_validate_height($height) === null) {
            throw new InvalidArgumentException('Feed Widget size is invalid.');
        }
        $config = dashboard_widget_feed_config_from_input(['feed_item_limit' => $itemLimit]);
        if ($config === null) {
            throw new InvalidArgumentException('Feed Widget item limit is invalid.');
        }
        update_content_owned($ownerId, $contentId, $url, $style);
        $sizeStmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_width = :width, widget_height = :height, widget_config = :config, widget_updated_at = :updated_at '
            . "WHERE widget_owner = :owner AND widget_type = 'feed' AND widget_reference_id = :reference_id AND widget_flag = 0"
        );
        $sizeStmt->execute([
            ':width' => $width,
            ':height' => $height,
            ':config' => dashboard_widget_encode_config($config),
            ':updated_at' => app_now(),
            ':owner' => $ownerId,
            ':reference_id' => $contentId,
        ]);
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
