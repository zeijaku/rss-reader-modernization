<?php

declare(strict_types=1);

/**
 * V1.1-D Dashboard Widget共通基盤。
 * Feed本体は従来通りcontentを正本とし、このTableでは配置情報だけを扱う。
 */

/** @return list<string> */
function dashboard_widget_types(): array
{
    return ['feed', 'search', 'clock', 'memo', 'task', 'calendar'];
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

/** @return array{schema:int,title:string,hour_format:string,show_seconds:bool,show_date:bool} */
function dashboard_widget_clock_defaults(): array
{
    return [
        'schema' => 1,
        'title' => 'Clock',
        'hour_format' => '24',
        'show_seconds' => false,
        'show_date' => true,
    ];
}

function dashboard_widget_validate_clock_title(mixed $value): ?string
{
    return app_validate_text($value, 32, false);
}

function dashboard_widget_validate_clock_hour_format(mixed $value): ?string
{
    return is_string($value) && in_array($value, ['12', '24'], true) ? $value : null;
}

/**
 * @return array{schema:int,title:string,hour_format:string,show_seconds:bool,show_date:bool}|null
 */
function dashboard_widget_clock_config_from_input(array $input): ?array
{
    $title = dashboard_widget_validate_clock_title($input['clock_title'] ?? null);
    $hourFormat = dashboard_widget_validate_clock_hour_format($input['clock_hour_format'] ?? null);
    $showSeconds = dashboard_widget_validate_boolean($input['clock_show_seconds'] ?? null);
    $showDate = dashboard_widget_validate_boolean($input['clock_show_date'] ?? null);
    if ($title === null || $hourFormat === null || $showSeconds === null || $showDate === null) {
        return null;
    }

    return [
        'schema' => 1,
        'title' => $title,
        'hour_format' => $hourFormat,
        'show_seconds' => $showSeconds,
        'show_date' => $showDate,
    ];
}

/** @return array{schema:int,title:string,hour_format:string,show_seconds:bool,show_date:bool} */
function dashboard_widget_clock_config_from_storage(mixed $value): array
{
    $defaults = dashboard_widget_clock_defaults();
    $config = dashboard_widget_decode_config($value);

    $title = dashboard_widget_validate_clock_title($config['title'] ?? null);
    $hourFormat = dashboard_widget_validate_clock_hour_format($config['hour_format'] ?? null);
    $showSeconds = dashboard_widget_validate_boolean($config['show_seconds'] ?? null);
    $showDate = dashboard_widget_validate_boolean($config['show_date'] ?? null);

    return [
        'schema' => 1,
        'title' => $title ?? $defaults['title'],
        'hour_format' => $hourFormat ?? $defaults['hour_format'],
        'show_seconds' => $showSeconds ?? $defaults['show_seconds'],
        'show_date' => $showDate ?? $defaults['show_date'],
    ];
}


function dashboard_widget_validate_memo_title(mixed $value): ?string
{
    return app_validate_text($value, 32, false);
}

function dashboard_widget_validate_memo_body(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    return app_validate_text($value, 4000, false);
}


/** @return array{schema:int,title:string} */
function dashboard_widget_task_defaults(): array
{
    return [
        'schema' => 1,
        'title' => 'Task',
    ];
}

function dashboard_widget_validate_task_widget_title(mixed $value): ?string
{
    return app_validate_text($value, 32, false);
}

/** @return array{schema:int,title:string}|null */
function dashboard_widget_task_config_from_input(array $input): ?array
{
    $title = dashboard_widget_validate_task_widget_title($input['task_widget_title'] ?? null);
    if ($title === null) {
        return null;
    }
    return ['schema' => 1, 'title' => $title];
}

/** @return array{schema:int,title:string} */
function dashboard_widget_task_config_from_storage(mixed $value): array
{
    $defaults = dashboard_widget_task_defaults();
    $config = dashboard_widget_decode_config($value);
    $title = dashboard_widget_validate_task_widget_title($config['title'] ?? null);
    return ['schema' => 1, 'title' => $title ?? $defaults['title']];
}

function dashboard_widget_validate_task_title(mixed $value): ?string
{
    return app_validate_text($value, 128, false);
}

function dashboard_widget_validate_task_due_date(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return '';
    }
    if (!is_string($value) || preg_match('/\\A[0-9]{4}-[0-9]{2}-[0-9]{2}\\z/D', $value) !== 1) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date instanceof DateTimeImmutable
        || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
        || $date->format('Y-m-d') !== $value) {
        return null;
    }
    return $value;
}

function dashboard_widget_validate_task_priority(mixed $value): ?string
{
    return is_string($value) && in_array($value, ['low', 'normal', 'high'], true) ? $value : null;
}

function dashboard_widget_task_priority_label(string $priority): string
{
    return match ($priority) {
        'high' => '高',
        'low' => '低',
        default => '通常',
    };
}

/** @return array<string,mixed>|null */
function dashboard_widget_normalize_task_row(array $row): ?array
{
    $taskId = app_validate_positive_int($row['task_id'] ?? null);
    $owner = app_validate_positive_int($row['task_owner'] ?? null);
    $widgetId = app_validate_positive_int($row['task_widget_id'] ?? null);
    $sortOrder = dashboard_widget_non_negative_int($row['task_sort_order'] ?? null);
    $title = dashboard_widget_validate_task_title($row['task_title'] ?? null);
    $dueDate = dashboard_widget_validate_task_due_date($row['task_due_date'] ?? null);
    $priority = dashboard_widget_validate_task_priority($row['task_priority'] ?? null);
    $completed = dashboard_widget_validate_boolean($row['task_completed'] ?? null);
    if ($taskId === null || $owner === null || $widgetId === null || $sortOrder === null
        || $title === null || $dueDate === null || $priority === null || $completed === null) {
        return null;
    }
    $row['task_id'] = $taskId;
    $row['task_owner'] = $owner;
    $row['task_widget_id'] = $widgetId;
    $row['task_sort_order'] = $sortOrder;
    $row['task_title'] = $title;
    $row['task_due_date'] = $dueDate;
    $row['task_priority'] = $priority;
    $row['task_priority_label'] = dashboard_widget_task_priority_label($priority);
    $row['task_completed'] = $completed;
    return $row;
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
    $style = app_normalize_content_style($row['widget_style'] ?? null);

    if ($widgetId === null || $owner === null || $location === null || $type === null || $sortOrder === null || $width === null || $style === null) {
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
    $row['widget_style'] = $style;
    $row['widget_config_data'] = match ($type) {
        'clock' => dashboard_widget_clock_config_from_storage($row['widget_config'] ?? null),
        'task' => dashboard_widget_task_config_from_storage($row['widget_config'] ?? null),
        'calendar' => calendar_widget_config_from_storage($row['widget_config'] ?? null),
        'search' => search_feed_config_from_storage($row['widget_config'] ?? null),
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
        . 'w.widget_reference_id, w.widget_sort_order, w.widget_width, w.widget_style, '
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
        ':sort_order' => dashboard_widget_next_sort_order($pdo, $ownerId, $location),
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

/** @param array{schema:int,title:string,hour_format:string,show_seconds:bool,show_date:bool} $config */
function dashboard_widget_create_clock(
    int $ownerId,
    int $location,
    string $style,
    int $width,
    array $config
): int {
    if ($ownerId <= 0
        || dashboard_widget_validate_location($location) === null
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || dashboard_widget_clock_config_from_input([
            'clock_title' => $config['title'] ?? null,
            'clock_hour_format' => $config['hour_format'] ?? null,
            'clock_show_seconds' => $config['show_seconds'] ?? null,
            'clock_show_date' => $config['show_date'] ?? null,
        ]) === null) {
        throw new InvalidArgumentException('Clock Widget settings are invalid.');
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
            . 'widget_width, widget_style, widget_config, widget_flag, widget_created_at, widget_updated_at) '
            . "VALUES (:owner, :location, 'clock', NULL, :sort_order, :width, :style, :config, 0, :created_at, :updated_at)"
        );
        $stmt->execute([
            ':owner' => $ownerId,
            ':location' => $location,
            ':sort_order' => dashboard_widget_next_sort_order($pdo, $ownerId, $location),
            ':width' => $width,
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

/** @param array{schema:int,title:string,hour_format:string,show_seconds:bool,show_date:bool} $config */
function dashboard_widget_update_clock(
    int $ownerId,
    int $widgetId,
    string $style,
    int $width,
    array $config
): bool {
    if ($ownerId <= 0
        || $widgetId <= 0
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || dashboard_widget_clock_config_from_input([
            'clock_title' => $config['title'] ?? null,
            'clock_hour_format' => $config['hour_format'] ?? null,
            'clock_show_seconds' => $config['show_seconds'] ?? null,
            'clock_show_date' => $config['show_date'] ?? null,
        ]) === null) {
        throw new InvalidArgumentException('Clock Widget settings are invalid.');
    }

    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }

    try {
        if (dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'clock') === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_width = :width, widget_style = :style, widget_config = :config, '
            . 'widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
            . "AND widget_type = 'clock' AND widget_flag = 0"
        );
        $stmt->execute([
            ':width' => $width,
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

function dashboard_widget_delete_clock(int $ownerId, int $widgetId): bool
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
        if (dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'clock') === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_flag = 1, widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
            . "AND widget_type = 'clock' AND widget_flag = 0"
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

/** @return array<string,mixed>|null */
function dashboard_widget_lock_owned_memo(PDO $pdo, int $ownerId, int $memoId): ?array
{
    if ($ownerId <= 0 || $memoId <= 0) {
        return null;
    }

    $sql = 'SELECT * FROM ' . db_table_identifier('memo') . ' '
        . 'WHERE memo_id = :memo_id AND memo_owner = :owner AND memo_flag = 0';
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':memo_id' => $memoId, ':owner' => $ownerId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function dashboard_widget_create_memo(
    int $ownerId,
    int $location,
    string $style,
    int $width,
    string $title,
    string $body
): array {
    $title = dashboard_widget_validate_memo_title($title);
    $body = dashboard_widget_validate_memo_body($body);
    if ($ownerId <= 0
        || dashboard_widget_validate_location($location) === null
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || $title === null
        || $body === null) {
        throw new InvalidArgumentException('Memo Widget settings are invalid.');
    }

    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }

    try {
        $now = app_now();
        $memoStmt = $pdo->prepare(
            'INSERT INTO ' . db_table_identifier('memo') . ' '
            . '(memo_date, memo_updated_at, memo_flag, memo_owner, memo_title, memo_body) '
            . 'VALUES (:memo_date, :memo_updated_at, 0, :memo_owner, :memo_title, :memo_body)'
        );
        $memoStmt->execute([
            ':memo_date' => $now,
            ':memo_updated_at' => $now,
            ':memo_owner' => $ownerId,
            ':memo_title' => $title,
            ':memo_body' => $body,
        ]);
        $memoId = (int) $pdo->lastInsertId();

        $widgetStmt = $pdo->prepare(
            'INSERT INTO ' . db_table_identifier('dashboard_widget') . ' '
            . '(widget_owner, widget_location, widget_type, widget_reference_id, widget_sort_order, '
            . 'widget_width, widget_style, widget_config, widget_flag, widget_created_at, widget_updated_at) '
            . "VALUES (:owner, :location, 'memo', :reference_id, :sort_order, :width, :style, NULL, 0, :created_at, :updated_at)"
        );
        $widgetStmt->execute([
            ':owner' => $ownerId,
            ':location' => $location,
            ':reference_id' => $memoId,
            ':sort_order' => dashboard_widget_next_sort_order($pdo, $ownerId, $location),
            ':width' => $width,
            ':style' => $style,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $widgetId = (int) $pdo->lastInsertId();

        if ($started) {
            $pdo->commit();
        }
        return ['memo_id' => $memoId, 'widget_id' => $widgetId];
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function dashboard_widget_update_memo(
    int $ownerId,
    int $widgetId,
    string $style,
    int $width,
    string $title,
    string $body
): bool {
    $title = dashboard_widget_validate_memo_title($title);
    $body = dashboard_widget_validate_memo_body($body);
    if ($ownerId <= 0
        || $widgetId <= 0
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || $title === null
        || $body === null) {
        throw new InvalidArgumentException('Memo Widget settings are invalid.');
    }

    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }

    try {
        $widget = dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'memo');
        $memoId = is_array($widget) ? app_validate_positive_int($widget['widget_reference_id'] ?? null) : null;
        if ($memoId === null || dashboard_widget_lock_owned_memo($pdo, $ownerId, $memoId) === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }

        $now = app_now();
        $memoStmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('memo') . ' '
            . 'SET memo_title = :memo_title, memo_body = :memo_body, memo_updated_at = :updated_at '
            . 'WHERE memo_id = :memo_id AND memo_owner = :owner AND memo_flag = 0'
        );
        $memoStmt->execute([
            ':memo_title' => $title,
            ':memo_body' => $body,
            ':updated_at' => $now,
            ':memo_id' => $memoId,
            ':owner' => $ownerId,
        ]);

        $widgetStmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_width = :width, widget_style = :style, widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
            . "AND widget_type = 'memo' AND widget_flag = 0"
        );
        $widgetStmt->execute([
            ':width' => $width,
            ':style' => $style,
            ':updated_at' => $now,
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

function dashboard_widget_delete_memo(int $ownerId, int $widgetId): bool
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
        $widget = dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'memo');
        $memoId = is_array($widget) ? app_validate_positive_int($widget['widget_reference_id'] ?? null) : null;
        if ($memoId === null || dashboard_widget_lock_owned_memo($pdo, $ownerId, $memoId) === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }

        $now = app_now();
        $memoStmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('memo') . ' '
            . 'SET memo_flag = 1, memo_updated_at = :updated_at '
            . 'WHERE memo_id = :memo_id AND memo_owner = :owner AND memo_flag = 0'
        );
        $memoStmt->execute([
            ':updated_at' => $now,
            ':memo_id' => $memoId,
            ':owner' => $ownerId,
        ]);

        $widgetStmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_flag = 1, widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
            . "AND widget_type = 'memo' AND widget_flag = 0"
        );
        $widgetStmt->execute([
            ':updated_at' => $now,
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

function dashboard_widget_create_task_widget(
    int $ownerId,
    int $location,
    string $style,
    int $width,
    array $config
): int {
    $config = dashboard_widget_task_config_from_input([
        'task_widget_title' => $config['title'] ?? null,
    ]);
    if ($ownerId <= 0
        || dashboard_widget_validate_location($location) === null
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || $config === null) {
        throw new InvalidArgumentException('Task Widget settings are invalid.');
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
            . 'widget_width, widget_style, widget_config, widget_flag, widget_created_at, widget_updated_at) '
            . "VALUES (:owner, :location, 'task', NULL, :sort_order, :width, :style, :config, 0, :created_at, :updated_at)"
        );
        $stmt->execute([
            ':owner' => $ownerId,
            ':location' => $location,
            ':sort_order' => dashboard_widget_next_sort_order($pdo, $ownerId, $location),
            ':width' => $width,
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

function dashboard_widget_update_task_widget(
    int $ownerId,
    int $widgetId,
    string $style,
    int $width,
    array $config
): bool {
    $config = dashboard_widget_task_config_from_input([
        'task_widget_title' => $config['title'] ?? null,
    ]);
    if ($ownerId <= 0 || $widgetId <= 0
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || $config === null) {
        throw new InvalidArgumentException('Task Widget settings are invalid.');
    }

    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        if (dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'task') === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_width = :width, widget_style = :style, widget_config = :config, widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
            . "AND widget_type = 'task' AND widget_flag = 0"
        );
        $stmt->execute([
            ':width' => $width,
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

function dashboard_widget_delete_task_widget(int $ownerId, int $widgetId): bool
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
        if (dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'task') === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $now = app_now();
        $taskStmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('task') . ' '
            . 'SET task_flag = 1, task_updated_at = :updated_at '
            . 'WHERE task_widget_id = :widget_id AND task_owner = :owner AND task_flag = 0'
        );
        $taskStmt->execute([':updated_at' => $now, ':widget_id' => $widgetId, ':owner' => $ownerId]);
        $widgetStmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_flag = 1, widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
            . "AND widget_type = 'task' AND widget_flag = 0"
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
function dashboard_widget_lock_owned_task(PDO $pdo, int $ownerId, int $taskId): ?array
{
    if ($ownerId <= 0 || $taskId <= 0) {
        return null;
    }
    $sql = 'SELECT * FROM ' . db_table_identifier('task') . ' '
        . 'WHERE task_id = :task_id AND task_owner = :owner AND task_flag = 0';
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':task_id' => $taskId, ':owner' => $ownerId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function dashboard_widget_task_active_count(PDO $pdo, int $ownerId, int $widgetId): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM ' . db_table_identifier('task') . ' '
        . 'WHERE task_owner = :owner AND task_widget_id = :widget_id AND task_flag = 0'
    );
    $stmt->execute([':owner' => $ownerId, ':widget_id' => $widgetId]);
    return (int) $stmt->fetchColumn();
}

function dashboard_widget_task_next_sort_order(PDO $pdo, int $ownerId, int $widgetId): int
{
    $sql = 'SELECT task_sort_order FROM ' . db_table_identifier('task') . ' '
        . 'WHERE task_owner = :owner AND task_widget_id = :widget_id AND task_flag = 0 '
        . 'ORDER BY task_sort_order DESC, task_id DESC LIMIT 1';
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':owner' => $ownerId, ':widget_id' => $widgetId]);
    $current = $stmt->fetchColumn();
    $maxOrder = $current === false || $current === null ? 0 : (int) $current;
    if ($maxOrder > 4294967285) {
        throw new OverflowException('Task sort order is full.');
    }
    return $maxOrder + 10;
}

/** @return array{task_id:int,widget_id:int} */
function dashboard_widget_create_task_item(
    int $ownerId,
    int $widgetId,
    string $title,
    string $dueDate,
    string $priority
): array {
    $title = dashboard_widget_validate_task_title($title);
    $dueDate = dashboard_widget_validate_task_due_date($dueDate);
    $priority = dashboard_widget_validate_task_priority($priority);
    if ($ownerId <= 0 || $widgetId <= 0 || $title === null || $dueDate === null || $priority === null) {
        throw new InvalidArgumentException('Task settings are invalid.');
    }

    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        if (dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'task') === null) {
            if ($started) {
                $pdo->rollBack();
            }
            throw new RuntimeException('Task Widget was not found.');
        }
        if (dashboard_widget_task_active_count($pdo, $ownerId, $widgetId) >= 100) {
            if ($started) {
                $pdo->rollBack();
            }
            throw new LengthException('A Task Widget can contain up to 100 Tasks.');
        }
        $now = app_now();
        $stmt = $pdo->prepare(
            'INSERT INTO ' . db_table_identifier('task') . ' '
            . '(task_date, task_updated_at, task_flag, task_owner, task_widget_id, task_title, '
            . 'task_due_date, task_priority, task_completed, task_completed_at, task_sort_order) '
            . 'VALUES (:task_date, :task_updated_at, 0, :task_owner, :task_widget_id, :task_title, '
            . ':task_due_date, :task_priority, 0, NULL, :task_sort_order)'
        );
        $stmt->execute([
            ':task_date' => $now,
            ':task_updated_at' => $now,
            ':task_owner' => $ownerId,
            ':task_widget_id' => $widgetId,
            ':task_title' => $title,
            ':task_due_date' => $dueDate === '' ? null : $dueDate,
            ':task_priority' => $priority,
            ':task_sort_order' => dashboard_widget_task_next_sort_order($pdo, $ownerId, $widgetId),
        ]);
        $taskId = (int) $pdo->lastInsertId();
        if ($started) {
            $pdo->commit();
        }
        return ['task_id' => $taskId, 'widget_id' => $widgetId];
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function dashboard_widget_update_task_item(
    int $ownerId,
    int $taskId,
    string $title,
    string $dueDate,
    string $priority
): bool {
    $title = dashboard_widget_validate_task_title($title);
    $dueDate = dashboard_widget_validate_task_due_date($dueDate);
    $priority = dashboard_widget_validate_task_priority($priority);
    if ($ownerId <= 0 || $taskId <= 0 || $title === null || $dueDate === null || $priority === null) {
        throw new InvalidArgumentException('Task settings are invalid.');
    }
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        $task = dashboard_widget_lock_owned_task($pdo, $ownerId, $taskId);
        $widgetId = is_array($task) ? app_validate_positive_int($task['task_widget_id'] ?? null) : null;
        if ($widgetId === null || dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'task') === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('task') . ' '
            . 'SET task_title = :task_title, task_due_date = :task_due_date, task_priority = :task_priority, '
            . 'task_updated_at = :updated_at '
            . 'WHERE task_id = :task_id AND task_owner = :owner AND task_flag = 0'
        );
        $stmt->execute([
            ':task_title' => $title,
            ':task_due_date' => $dueDate === '' ? null : $dueDate,
            ':task_priority' => $priority,
            ':updated_at' => app_now(),
            ':task_id' => $taskId,
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

function dashboard_widget_toggle_task_item(int $ownerId, int $taskId, bool $completed): bool
{
    if ($ownerId <= 0 || $taskId <= 0) {
        return false;
    }
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        $task = dashboard_widget_lock_owned_task($pdo, $ownerId, $taskId);
        $widgetId = is_array($task) ? app_validate_positive_int($task['task_widget_id'] ?? null) : null;
        if ($widgetId === null || dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'task') === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $now = app_now();
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('task') . ' '
            . 'SET task_completed = :completed, task_completed_at = :completed_at, task_updated_at = :updated_at '
            . 'WHERE task_id = :task_id AND task_owner = :owner AND task_flag = 0'
        );
        $stmt->execute([
            ':completed' => $completed ? 1 : 0,
            ':completed_at' => $completed ? $now : null,
            ':updated_at' => $now,
            ':task_id' => $taskId,
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

function dashboard_widget_delete_task_item(int $ownerId, int $taskId): bool
{
    if ($ownerId <= 0 || $taskId <= 0) {
        return false;
    }
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        $task = dashboard_widget_lock_owned_task($pdo, $ownerId, $taskId);
        $widgetId = is_array($task) ? app_validate_positive_int($task['task_widget_id'] ?? null) : null;
        if ($widgetId === null || dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'task') === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('task') . ' '
            . 'SET task_flag = 1, task_updated_at = :updated_at '
            . 'WHERE task_id = :task_id AND task_owner = :owner AND task_flag = 0'
        );
        $stmt->execute([':updated_at' => app_now(), ':task_id' => $taskId, ':owner' => $ownerId]);
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


/** @param array{schema:int,title:string,show_completed_tasks:bool} $config */
function dashboard_widget_create_calendar(
    int $ownerId,
    int $location,
    string $style,
    int $width,
    array $config
): int {
    $config = calendar_widget_config_from_input([
        'calendar_title' => $config['title'] ?? null,
        'calendar_show_completed_tasks' => $config['show_completed_tasks'] ?? null,
    ]);
    if ($ownerId <= 0
        || dashboard_widget_validate_location($location) === null
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
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
            . 'widget_width, widget_style, widget_config, widget_flag, widget_created_at, widget_updated_at) '
            . "VALUES (:owner, :location, 'calendar', NULL, :sort_order, :width, :style, :config, 0, :created_at, :updated_at)"
        );
        $stmt->execute([
            ':owner' => $ownerId,
            ':location' => $location,
            ':sort_order' => dashboard_widget_next_sort_order($pdo, $ownerId, $location),
            ':width' => $width,
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
    array $config
): bool {
    $config = calendar_widget_config_from_input([
        'calendar_title' => $config['title'] ?? null,
        'calendar_show_completed_tasks' => $config['show_completed_tasks'] ?? null,
    ]);
    if ($ownerId <= 0 || $widgetId <= 0
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
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
            . 'SET widget_width = :width, widget_style = :style, widget_config = :config, widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
            . "AND widget_type = 'calendar' AND widget_flag = 0"
        );
        $stmt->execute([
            ':width' => $width,
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

