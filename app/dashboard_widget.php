<?php

declare(strict_types=1);

/**
 * V1.1-D Dashboard Widget共通基盤。
 * Feed本体は従来通りcontentを正本とし、このTableでは配置情報だけを扱う。
 */

/** @return list<string> */
function dashboard_widget_types(): array
{
    return ['feed', 'search', 'clock', 'memo', 'task', 'calendar', 'game', 'links', 'weather', 'sun_moon', 'air_quality', 'earthquake', 'health_probe', 'calculator', 'blind_spot', 'x_timeline'];
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

function dashboard_widget_validate_height(mixed $value): ?int
{
    $height = app_validate_positive_int($value);
    return $height !== null && $height <= 2 ? $height : null;
}


function dashboard_widget_validate_boolean(mixed $value): ?bool
{
    if (is_bool($value)) {
        return $value;
    }
    if ($value === 1 || $value === '1' || $value === 'true') {
        return true;
    }
    if ($value === 0 || $value === '0' || $value === 'false') {
        return false;
    }
    return null;
}


/** @return list<int>|null */
function dashboard_widget_decode_order_list(mixed $value, int $maxItems = 200): ?array
{
    if (!is_string($value) || $value === '' || strlen($value) > 4096 || $maxItems < 1) {
        return null;
    }

    try {
        $decoded = json_decode($value, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }

    if (!is_array($decoded) || !array_is_list($decoded) || $decoded === [] || count($decoded) > $maxItems) {
        return null;
    }

    $ids = [];
    $seen = [];
    foreach ($decoded as $valueId) {
        $widgetId = app_validate_positive_int($valueId);
        if ($widgetId === null || isset($seen[$widgetId])) {
            return null;
        }
        $seen[$widgetId] = true;
        $ids[] = $widgetId;
    }

    return $ids;
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
    $height = dashboard_widget_validate_height($row['widget_height'] ?? 1);
    $style = app_normalize_content_style($row['widget_style'] ?? null);

    if ($widgetId === null || $owner === null || $location === null || $type === null || $sortOrder === null || $width === null || $height === null || $style === null) {
        return null;
    }

    $referenceId = $row['widget_reference_id'] === null
        ? null
        : app_validate_positive_int($row['widget_reference_id'] ?? null);
    if (in_array($type, ['feed', 'memo'], true) && $referenceId === null) {
        return null;
    }

    $row['widget_id'] = $widgetId;
    $row['widget_owner'] = $owner;
    $row['widget_location'] = $location;
    $row['widget_type'] = $type;
    $row['widget_reference_id'] = $referenceId;
    $row['widget_sort_order'] = $sortOrder;
    $row['widget_width'] = $width;
    $row['widget_height'] = $height;
    $row['widget_style'] = $style;
    $row['widget_config_data'] = match ($type) {
        'clock' => dashboard_widget_clock_config_from_storage($row['widget_config'] ?? null),
        'task' => dashboard_widget_task_config_from_storage($row['widget_config'] ?? null),
        'calendar' => calendar_widget_config_from_storage($row['widget_config'] ?? null),
        'game' => mini_game_widget_config_from_storage($row['widget_config'] ?? null),
        'search' => search_feed_config_from_storage($row['widget_config'] ?? null),
        'feed' => dashboard_widget_feed_config_from_storage($row['widget_config'] ?? null),
        'links' => links_widget_config_from_storage($row['widget_config'] ?? null),
        'weather' => weather_widget_config_from_storage($row['widget_config'] ?? null),
        'sun_moon' => sun_moon_widget_config_from_storage($row['widget_config'] ?? null),
        'air_quality' => air_quality_widget_config_from_storage($row['widget_config'] ?? null),
        'blind_spot' => dashboard_widget_blind_spot_config_from_storage($row['widget_config'] ?? null),
        'x_timeline' => x_widget_config_from_storage($row['widget_config'] ?? null),
        default => dashboard_widget_decode_config($row['widget_config'] ?? null),
    };
    $row['widget_width_class'] = dashboard_widget_width_class($width);

    return $row;
}

/** @return list<array<string,mixed>> */
function search_dashboard_widgets(int $ownerId, int $location): array
{
    if ($ownerId <= 0 || dashboard_widget_validate_location($location) === null) {
        return [];
    }

    $pdo = conn_db();
    $stmt = $pdo->prepare(
        'SELECT w.widget_id, w.widget_owner, w.widget_location, w.widget_type, '
        . 'w.widget_reference_id, w.widget_sort_order, w.widget_width, w.widget_height, w.widget_style, '
        . 'w.widget_config, w.widget_flag, w.widget_created_at, w.widget_updated_at, '
        . 'c.content_id, c.content_date, c.content_flag, c.content_owner, c.content_location, '
        . 'c.content_style, c.content_value, '
        . 'm.memo_id, m.memo_date, m.memo_updated_at, m.memo_flag, m.memo_owner, m.memo_title, m.memo_body '
        . 'FROM ' . db_table_identifier('dashboard_widget') . ' w '
        . 'LEFT JOIN ' . db_table_identifier('content') . ' c '
        . "ON w.widget_type = 'feed' AND w.widget_reference_id = c.content_id AND w.widget_owner = c.content_owner "
        . 'LEFT JOIN ' . db_table_identifier('memo') . ' m '
        . "ON w.widget_type = 'memo' AND w.widget_reference_id = m.memo_id AND w.widget_owner = m.memo_owner "
        . 'WHERE w.widget_owner = :owner AND w.widget_location = :location AND w.widget_flag = 0 '
        . 'ORDER BY w.widget_sort_order ASC, w.widget_id ASC'
    );
    $stmt->execute([':owner' => $ownerId, ':location' => $location]);

    $result = [];
    $taskWidgetIds = [];
    $linksWidgetIds = [];
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
        if ($normalized['widget_type'] === 'memo') {
            $memoTitle = dashboard_widget_validate_memo_title($normalized['memo_title'] ?? null);
            $memoBody = dashboard_widget_validate_memo_body($normalized['memo_body'] ?? null);
            if ((int) ($normalized['memo_flag'] ?? 1) !== 0
                || (int) ($normalized['memo_owner'] ?? 0) !== $ownerId
                || $memoTitle === null
                || $memoBody === null) {
                continue;
            }
            $normalized['memo_title'] = $memoTitle;
            $normalized['memo_body'] = $memoBody;
        }
        if ($normalized['widget_type'] === 'task') {
            $taskWidgetIds[] = (int) $normalized['widget_id'];
            $normalized['task_items'] = [];
        }
        if ($normalized['widget_type'] === 'links') {
            $linksWidgetIds[] = (int) $normalized['widget_id'];
            $normalized['link_items'] = [];
        }
        $result[] = $normalized;
    }

    if ($taskWidgetIds !== []) {
        $placeholders = [];
        $params = [':task_owner' => $ownerId];
        foreach ($taskWidgetIds as $index => $widgetId) {
            $name = ':task_widget_' . $index;
            $placeholders[] = $name;
            $params[$name] = $widgetId;
        }
        $taskStmt = $pdo->prepare(
            'SELECT task_id, task_date, task_updated_at, task_flag, task_owner, task_widget_id, '
            . 'task_title, task_due_date, task_priority, task_completed, task_completed_at, task_sort_order '
            . 'FROM ' . db_table_identifier('task') . ' '
            . 'WHERE task_owner = :task_owner AND task_flag = 0 '
            . 'AND task_widget_id IN (' . implode(', ', $placeholders) . ') '
            . 'ORDER BY task_widget_id ASC, task_completed ASC, task_sort_order ASC, task_id ASC'
        );
        $taskStmt->execute($params);
        $taskMap = [];
        foreach ($taskStmt->fetchAll() as $taskRow) {
            if (!is_array($taskRow)) {
                continue;
            }
            $task = dashboard_widget_normalize_task_row($taskRow);
            if ($task === null || (int) $task['task_owner'] !== $ownerId) {
                continue;
            }
            $taskMap[(int) $task['task_widget_id']][] = $task;
        }
        foreach ($result as &$widget) {
            if ($widget['widget_type'] === 'task') {
                $widget['task_items'] = $taskMap[(int) $widget['widget_id']] ?? [];
            }
        }
        unset($widget);
    }


    if ($linksWidgetIds !== []) {
        $placeholders = [];
        $params = [':link_owner' => $ownerId];
        foreach ($linksWidgetIds as $index => $widgetId) {
            $name = ':links_widget_' . $index;
            $placeholders[] = $name;
            $params[$name] = $widgetId;
        }
        $linkStmt = $pdo->prepare(
            'SELECT link_id, link_date, link_updated_at, link_flag, link_owner, link_widget_id, '
            . 'link_title, link_url, link_sort_order FROM ' . db_table_identifier('link_item') . ' '
            . 'WHERE link_owner = :link_owner AND link_flag = 0 '
            . 'AND link_widget_id IN (' . implode(', ', $placeholders) . ') '
            . 'ORDER BY link_widget_id ASC, link_sort_order ASC, link_id ASC'
        );
        $linkStmt->execute($params);
        $linkMap = [];
        foreach ($linkStmt->fetchAll() as $linkRow) {
            if (!is_array($linkRow)) {
                continue;
            }
            $link = links_normalize_item($linkRow);
            if ($link === null || (int) $link['link_owner'] !== $ownerId) {
                continue;
            }
            $linkMap[(int) $link['link_widget_id']][] = $link;
        }
        foreach ($result as &$widget) {
            if ($widget['widget_type'] === 'links') {
                $widget['link_items'] = $linkMap[(int) $widget['widget_id']] ?? [];
            }
        }
        unset($widget);
    }

    return $result;
}

/** @return list<array<string,mixed>> */
function dashboard_widget_public_list(int $ownerId, int $location): array
{
    $result = [];
    foreach (search_dashboard_widgets($ownerId, $location) as $row) {
        $public = [
            'widget_id' => $row['widget_id'],
            'widget_location' => $row['widget_location'],
            'widget_type' => $row['widget_type'],
            'widget_reference_id' => $row['widget_reference_id'],
            'widget_sort_order' => $row['widget_sort_order'],
            'widget_width' => $row['widget_width'],
            'widget_height' => $row['widget_height'],
            'widget_style' => $row['widget_style'],
            'widget_config' => $row['widget_config_data'],
        ];
        if ($row['widget_type'] === 'memo') {
            $public['memo'] = [
                'memo_id' => $row['memo_id'],
                'title' => $row['memo_title'],
                'body' => $row['memo_body'],
                'updated_at' => $row['memo_updated_at'],
            ];
        }
        if ($row['widget_type'] === 'links') {
            $public['links'] = array_map(
                static fn(array $link): array => [
                    'link_id' => $link['link_id'],
                    'title' => $link['link_title'],
                    'url' => $link['link_url'],
                    'sort_order' => $link['link_sort_order'],
                    'updated_at' => $link['link_updated_at'],
                ],
                $row['link_items'] ?? []
            );
        }
        if ($row['widget_type'] === 'task') {
            $public['tasks'] = array_map(
                static fn(array $task): array => [
                    'task_id' => $task['task_id'],
                    'title' => $task['task_title'],
                    'due_date' => $task['task_due_date'],
                    'priority' => $task['task_priority'],
                    'completed' => $task['task_completed'],
                    'sort_order' => $task['task_sort_order'],
                    'updated_at' => $task['task_updated_at'],
                ],
                $row['task_items'] ?? []
            );
        }
        $result[] = $public;
    }
    return $result;
}

/** @return list<array{widget_id:int,widget_location:int,title:string}> */
function dashboard_widget_task_targets(int $ownerId): array
{
    if ($ownerId <= 0) {
        return [];
    }

    $stmt = conn_db()->prepare(
        'SELECT widget_id, widget_location, widget_config FROM ' . db_table_identifier('dashboard_widget') . ' '
        . "WHERE widget_owner = :owner AND widget_type = 'task' AND widget_flag = 0 "
        . 'ORDER BY widget_location ASC, widget_sort_order ASC, widget_id ASC'
    );
    $stmt->execute([':owner' => $ownerId]);

    $targets = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $widgetId = app_validate_positive_int($row['widget_id'] ?? null);
        $location = dashboard_widget_validate_location($row['widget_location'] ?? null);
        if ($widgetId === null || $location === null) {
            continue;
        }
        $config = dashboard_widget_task_config_from_storage($row['widget_config'] ?? null);
        $targets[] = [
            'widget_id' => $widgetId,
            'widget_location' => $location,
            'title' => $config['title'],
        ];
    }
    return $targets;
}

function dashboard_widget_next_sort_order(PDO $pdo, int $ownerId, int $location): int
{
    $sql = 'SELECT widget_sort_order FROM ' . db_table_identifier('dashboard_widget') . ' '
        . 'WHERE widget_owner = :owner AND widget_location = :location AND widget_flag = 0 '
        . 'ORDER BY widget_sort_order DESC, widget_id DESC LIMIT 1';
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':owner' => $ownerId, ':location' => $location]);
    $current = $stmt->fetchColumn();
    $maxOrder = $current === false || $current === null ? 0 : (int) $current;
    if ($maxOrder > 4294967285) {
        throw new OverflowException('Dashboard Widget sort order is full.');
    }
    return $maxOrder + 10;
}


/** @return array<string,mixed>|null */
function dashboard_widget_lock_owned_widget(PDO $pdo, int $ownerId, int $widgetId, string $type): ?array
{
    if ($ownerId <= 0 || $widgetId <= 0 || dashboard_widget_validate_type($type) === null) {
        return null;
    }

    $sql = 'SELECT * FROM ' . db_table_identifier('dashboard_widget') . ' '
        . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
        . 'AND widget_type = :widget_type AND widget_flag = 0';
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':widget_id' => $widgetId,
        ':owner' => $ownerId,
        ':widget_type' => $type,
    ]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}


/** @param array{schema:int,title:string,show_completed_tasks:bool} $config */
function dashboard_widget_create_calendar(
    int $ownerId,
    int $location,
    string $style,
    int $width,
    array $config,
    int $height = 1
): int {
    $config = calendar_widget_config_from_input([
        'calendar_title' => $config['title'] ?? null,
        'calendar_show_completed_tasks' => $config['show_completed_tasks'] ?? null,
    ]);
    if ($ownerId <= 0
        || dashboard_widget_validate_location($location) === null
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null
        || $config === null) {
        throw new InvalidArgumentException('Calendar Widget settings are invalid.');
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
            . "VALUES (:owner, :location, 'calendar', NULL, :sort_order, :width, :height, :style, :config, 0, :created_at, :updated_at)"
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

/** @param array{schema:int,title:string,show_completed_tasks:bool} $config */
function dashboard_widget_update_calendar(
    int $ownerId,
    int $widgetId,
    string $style,
    int $width,
    array $config,
    int $height = 1
): bool {
    $config = calendar_widget_config_from_input([
        'calendar_title' => $config['title'] ?? null,
        'calendar_show_completed_tasks' => $config['show_completed_tasks'] ?? null,
    ]);
    if ($ownerId <= 0 || $widgetId <= 0
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null
        || $config === null) {
        throw new InvalidArgumentException('Calendar Widget settings are invalid.');
    }
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        if (dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'calendar') === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_width = :width, widget_height = :height, widget_style = :style, widget_config = :config, widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
            . "AND widget_type = 'calendar' AND widget_flag = 0"
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

function dashboard_widget_delete_calendar(int $ownerId, int $widgetId): bool
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
        if (dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'calendar') === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_flag = 1, widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
            . "AND widget_type = 'calendar' AND widget_flag = 0"
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

/** @return list<int> */
function dashboard_widget_lock_order(PDO $pdo, int $ownerId, int $location): array
{
    $sql = 'SELECT widget_id FROM ' . db_table_identifier('dashboard_widget') . ' '
        . 'WHERE widget_owner = :owner AND widget_location = :location AND widget_flag = 0 '
        . 'ORDER BY widget_sort_order ASC, widget_id ASC';
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':owner' => $ownerId, ':location' => $location]);

    $ids = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $value) {
        $widgetId = app_validate_positive_int($value);
        if ($widgetId !== null) {
            $ids[] = $widgetId;
        }
    }
    return $ids;
}

/**
 * @param list<int> $previousIds
 * @param list<int> $orderedIds
 * @return array{updated:bool,conflict:bool,widget_ids:list<int>,sort_orders:array<int,int>}
 */
function dashboard_widget_reorder(int $ownerId, int $location, array $previousIds, array $orderedIds): array
{
    if ($ownerId <= 0 || dashboard_widget_validate_location($location) === null) {
        throw new InvalidArgumentException('Dashboard Widget reorder scope is invalid.');
    }
    if ($previousIds === [] || count($previousIds) !== count($orderedIds)) {
        throw new InvalidArgumentException('Dashboard Widget order is invalid.');
    }

    foreach (array_merge($previousIds, $orderedIds) as $widgetId) {
        if (!is_int($widgetId) || $widgetId <= 0) {
            throw new InvalidArgumentException('Dashboard Widget order contains an invalid ID.');
        }
    }

    $previousSet = $previousIds;
    $orderedSet = $orderedIds;
    sort($previousSet, SORT_NUMERIC);
    sort($orderedSet, SORT_NUMERIC);
    if ($previousSet !== $orderedSet
        || count(array_unique($previousIds)) !== count($previousIds)
        || count(array_unique($orderedIds)) !== count($orderedIds)) {
        throw new InvalidArgumentException('Dashboard Widget order does not contain the same Widgets.');
    }

    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }

    try {
        $currentIds = dashboard_widget_lock_order($pdo, $ownerId, $location);
        if ($currentIds !== $previousIds) {
            if ($started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'updated' => false,
                'conflict' => true,
                'widget_ids' => $currentIds,
                'sort_orders' => [],
            ];
        }

        $update = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_sort_order = :sort_order, widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
            . 'AND widget_location = :location AND widget_flag = 0'
        );
        $now = app_now();
        $sortOrders = [];
        foreach ($orderedIds as $index => $widgetId) {
            $sortOrder = ($index + 1) * 10;
            $update->execute([
                ':sort_order' => $sortOrder,
                ':updated_at' => $now,
                ':widget_id' => $widgetId,
                ':owner' => $ownerId,
                ':location' => $location,
            ]);
            $sortOrders[$widgetId] = $sortOrder;
        }

        if ($started) {
            $pdo->commit();
        }
        return [
            'updated' => $orderedIds !== $previousIds,
            'conflict' => false,
            'widget_ids' => $orderedIds,
            'sort_orders' => $sortOrders,
        ];
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

// V1.19-B: feature persistence is grouped broadly while generic widget primitives stay in this facade.
require_once __DIR__ . '/dashboard/feed_widgets.php';
require_once __DIR__ . '/dashboard/personal_widgets.php';
require_once __DIR__ . '/dashboard/utility_widgets.php';
