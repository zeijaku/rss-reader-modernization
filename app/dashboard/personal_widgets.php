<?php

declare(strict_types=1);

/**
 * V1.19-B broad module extracted from the v1.18.0 facade.
 * Function bodies are intentionally kept unchanged.
 */

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
    string $body,
    int $height = 1
): array {
    $title = dashboard_widget_validate_memo_title($title);
    $body = dashboard_widget_validate_memo_body($body);
    if ($ownerId <= 0
        || dashboard_widget_validate_location($location) === null
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null
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
            . 'widget_width, widget_height, widget_style, widget_config, widget_flag, widget_created_at, widget_updated_at) '
            . "VALUES (:owner, :location, 'memo', :reference_id, :sort_order, :width, :height, :style, NULL, 0, :created_at, :updated_at)"
        );
        $widgetStmt->execute([
            ':owner' => $ownerId,
            ':location' => $location,
            ':reference_id' => $memoId,
            ':sort_order' => dashboard_widget_next_sort_order($pdo, $ownerId, $location),
            ':width' => $width,
            ':height' => $height,
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
    string $body,
    int $height = 1
): bool {
    $title = dashboard_widget_validate_memo_title($title);
    $body = dashboard_widget_validate_memo_body($body);
    if ($ownerId <= 0
        || $widgetId <= 0
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null
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
            . 'SET widget_width = :width, widget_height = :height, widget_style = :style, widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
            . "AND widget_type = 'memo' AND widget_flag = 0"
        );
        $widgetStmt->execute([
            ':width' => $width,
            ':height' => $height,
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
    array $config,
    int $height = 1
): int {
    $config = dashboard_widget_task_config_from_input([
        'task_widget_title' => $config['title'] ?? null,
    ]);
    if ($ownerId <= 0
        || dashboard_widget_validate_location($location) === null
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null
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
            . 'widget_width, widget_height, widget_style, widget_config, widget_flag, widget_created_at, widget_updated_at) '
            . "VALUES (:owner, :location, 'task', NULL, :sort_order, :width, :height, :style, :config, 0, :created_at, :updated_at)"
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

function dashboard_widget_update_task_widget(
    int $ownerId,
    int $widgetId,
    string $style,
    int $width,
    array $config,
    int $height = 1
): bool {
    $config = dashboard_widget_task_config_from_input([
        'task_widget_title' => $config['title'] ?? null,
    ]);
    if ($ownerId <= 0 || $widgetId <= 0
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null
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
            . 'SET widget_width = :width, widget_height = :height, widget_style = :style, widget_config = :config, widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
            . "AND widget_type = 'task' AND widget_flag = 0"
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
