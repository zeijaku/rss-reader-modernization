<?php

declare(strict_types=1);

/** @return array{schema:int,title:string} */
function links_widget_defaults(): array
{
    return [
        'schema' => 1,
        'title' => 'Links',
    ];
}

function links_widget_validate_title(mixed $value): ?string
{
    return app_validate_text($value, 32, false);
}

/** @return array{schema:int,title:string}|null */
function links_widget_config_from_input(array $input): ?array
{
    $title = links_widget_validate_title($input['links_title'] ?? null);
    if ($title === null) {
        return null;
    }
    return ['schema' => 1, 'title' => $title];
}

/** @return array{schema:int,title:string} */
function links_widget_config_from_storage(mixed $value): array
{
    $defaults = links_widget_defaults();
    $config = dashboard_widget_decode_config($value);
    $title = links_widget_validate_title($config['title'] ?? null);
    return ['schema' => 1, 'title' => $title ?? $defaults['title']];
}

function links_validate_item_title(mixed $value): ?string
{
    return app_validate_text($value, 128, false);
}

function links_validate_item_url(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $value = trim($value);
    if ($value === '' || strlen($value) > 2048 || app_has_control_characters($value)) {
        return null;
    }
    $parts = parse_url($value);
    if (!is_array($parts)
        || !isset($parts['scheme'], $parts['host'])
        || !is_string($parts['scheme'])
        || !is_string($parts['host'])
        || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
        || $parts['host'] === ''
        || isset($parts['user'])
        || isset($parts['pass'])) {
        return null;
    }
    return $value;
}

/** @return array<string,mixed>|null */
function links_normalize_item(array $row): ?array
{
    $linkId = app_validate_positive_int($row['link_id'] ?? null);
    $owner = app_validate_positive_int($row['link_owner'] ?? null);
    $widgetId = app_validate_positive_int($row['link_widget_id'] ?? null);
    $title = links_validate_item_title($row['link_title'] ?? null);
    $url = links_validate_item_url($row['link_url'] ?? null);
    $sortOrder = dashboard_widget_non_negative_int($row['link_sort_order'] ?? null);
    if ($linkId === null || $owner === null || $widgetId === null || $title === null || $url === null || $sortOrder === null) {
        return null;
    }
    $row['link_id'] = $linkId;
    $row['link_owner'] = $owner;
    $row['link_widget_id'] = $widgetId;
    $row['link_title'] = $title;
    $row['link_url'] = $url;
    $row['link_sort_order'] = $sortOrder;
    return $row;
}

function links_widget_create(
    int $ownerId,
    int $location,
    string $style,
    int $width,
    array $config,
    int $height = 1
): int {
    if ($ownerId <= 0
        || dashboard_widget_validate_location($location) === null
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null
        || links_widget_config_from_input(['links_title' => $config['title'] ?? null]) === null) {
        throw new InvalidArgumentException('Links Widget settings are invalid.');
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
            . "VALUES (:owner, :location, 'links', NULL, :sort_order, :width, :height, :style, :config, 0, :created_at, :updated_at)"
        );
        $stmt->execute([
            ':owner' => $ownerId,
            ':location' => $location,
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

function links_widget_update(
    int $ownerId,
    int $widgetId,
    string $style,
    int $width,
    array $config,
    int $height = 1
): bool {
    if ($ownerId <= 0 || $widgetId <= 0
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null
        || links_widget_config_from_input(['links_title' => $config['title'] ?? null]) === null) {
        throw new InvalidArgumentException('Links Widget settings are invalid.');
    }
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        if (dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'links') === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_width = :width, widget_height = :height, widget_style = :style, widget_config = :config, widget_updated_at = :updated_at '
            . "WHERE widget_id = :widget_id AND widget_owner = :owner AND widget_type = 'links' AND widget_flag = 0"
        );
        $stmt->execute([
            ':width' => $width,
            ':height' => $height,
            ':style' => $style,
            ':config' => dashboard_widget_encode_config($config),
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

function links_widget_delete(int $ownerId, int $widgetId): bool
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
        if (dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'links') === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $now = app_now();
        $linkStmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('link_item') . ' SET link_flag = 1, link_updated_at = :updated_at '
            . 'WHERE link_owner = :owner AND link_widget_id = :widget_id AND link_flag = 0'
        );
        $linkStmt->execute([':updated_at' => $now, ':owner' => $ownerId, ':widget_id' => $widgetId]);
        $widgetStmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' SET widget_flag = 1, widget_updated_at = :updated_at '
            . "WHERE widget_id = :widget_id AND widget_owner = :owner AND widget_type = 'links' AND widget_flag = 0"
        );
        $widgetStmt->execute([':updated_at' => $now, ':widget_id' => $widgetId, ':owner' => $ownerId]);
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
function links_lock_owned_item(PDO $pdo, int $ownerId, int $linkId): ?array
{
    if ($ownerId <= 0 || $linkId <= 0) {
        return null;
    }
    $sql = 'SELECT * FROM ' . db_table_identifier('link_item') . ' '
        . 'WHERE link_id = :link_id AND link_owner = :owner AND link_flag = 0';
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':link_id' => $linkId, ':owner' => $ownerId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function links_active_count(PDO $pdo, int $ownerId, int $widgetId): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM ' . db_table_identifier('link_item') . ' '
        . 'WHERE link_owner = :owner AND link_widget_id = :widget_id AND link_flag = 0'
    );
    $stmt->execute([':owner' => $ownerId, ':widget_id' => $widgetId]);
    return (int) $stmt->fetchColumn();
}

function links_next_sort_order(PDO $pdo, int $ownerId, int $widgetId): int
{
    $sql = 'SELECT link_sort_order FROM ' . db_table_identifier('link_item') . ' '
        . 'WHERE link_owner = :owner AND link_widget_id = :widget_id AND link_flag = 0 '
        . 'ORDER BY link_sort_order DESC, link_id DESC LIMIT 1';
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':owner' => $ownerId, ':widget_id' => $widgetId]);
    $current = $stmt->fetchColumn();
    $maxOrder = $current === false || $current === null ? 0 : (int) $current;
    if ($maxOrder > 4294967285) {
        throw new OverflowException('Link sort order is full.');
    }
    return $maxOrder + 10;
}

/** @return array{link_id:int,widget_id:int} */
function links_item_create(int $ownerId, int $widgetId, string $title, string $url): array
{
    $title = links_validate_item_title($title);
    $url = links_validate_item_url($url);
    if ($ownerId <= 0 || $widgetId <= 0 || $title === null || $url === null) {
        throw new InvalidArgumentException('Link settings are invalid.');
    }
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        if (dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'links') === null) {
            if ($started) {
                $pdo->rollBack();
            }
            throw new RuntimeException('Links Widget was not found.');
        }
        if (links_active_count($pdo, $ownerId, $widgetId) >= 100) {
            if ($started) {
                $pdo->rollBack();
            }
            throw new LengthException('A Links Widget can contain up to 100 links.');
        }
        $now = app_now();
        $stmt = $pdo->prepare(
            'INSERT INTO ' . db_table_identifier('link_item') . ' '
            . '(link_date, link_updated_at, link_flag, link_owner, link_widget_id, link_title, link_url, link_sort_order) '
            . 'VALUES (:created_at, :updated_at, 0, :owner, :widget_id, :title, :url, :sort_order)'
        );
        $stmt->execute([
            ':created_at' => $now,
            ':updated_at' => $now,
            ':owner' => $ownerId,
            ':widget_id' => $widgetId,
            ':title' => $title,
            ':url' => $url,
            ':sort_order' => links_next_sort_order($pdo, $ownerId, $widgetId),
        ]);
        $linkId = (int) $pdo->lastInsertId();
        if ($started) {
            $pdo->commit();
        }
        return ['link_id' => $linkId, 'widget_id' => $widgetId];
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function links_item_update(int $ownerId, int $linkId, string $title, string $url): bool
{
    $title = links_validate_item_title($title);
    $url = links_validate_item_url($url);
    if ($ownerId <= 0 || $linkId <= 0 || $title === null || $url === null) {
        throw new InvalidArgumentException('Link settings are invalid.');
    }
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        $item = links_lock_owned_item($pdo, $ownerId, $linkId);
        $widgetId = is_array($item) ? app_validate_positive_int($item['link_widget_id'] ?? null) : null;
        if ($widgetId === null || dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'links') === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('link_item') . ' '
            . 'SET link_title = :title, link_url = :url, link_updated_at = :updated_at '
            . 'WHERE link_id = :link_id AND link_owner = :owner AND link_flag = 0'
        );
        $stmt->execute([
            ':title' => $title,
            ':url' => $url,
            ':updated_at' => app_now(),
            ':link_id' => $linkId,
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

function links_item_delete(int $ownerId, int $linkId): bool
{
    if ($ownerId <= 0 || $linkId <= 0) {
        return false;
    }
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        $item = links_lock_owned_item($pdo, $ownerId, $linkId);
        $widgetId = is_array($item) ? app_validate_positive_int($item['link_widget_id'] ?? null) : null;
        if ($widgetId === null || dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'links') === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('link_item') . ' SET link_flag = 1, link_updated_at = :updated_at '
            . 'WHERE link_id = :link_id AND link_owner = :owner AND link_flag = 0'
        );
        $stmt->execute([':updated_at' => app_now(), ':link_id' => $linkId, ':owner' => $ownerId]);
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
