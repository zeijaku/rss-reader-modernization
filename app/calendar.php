<?php

declare(strict_types=1);

/** @return array{schema:int,title:string,show_completed_tasks:bool} */
function calendar_widget_defaults(): array
{
    return [
        'schema' => 1,
        'title' => 'Calendar',
        'show_completed_tasks' => false,
    ];
}

function calendar_widget_validate_title(mixed $value): ?string
{
    return app_validate_text($value, 32, false);
}

/** @return array{schema:int,title:string,show_completed_tasks:bool}|null */
function calendar_widget_config_from_input(array $input): ?array
{
    $title = calendar_widget_validate_title($input['calendar_title'] ?? null);
    $showCompleted = dashboard_widget_validate_boolean($input['calendar_show_completed_tasks'] ?? null);
    if ($title === null || $showCompleted === null) {
        return null;
    }
    return [
        'schema' => 1,
        'title' => $title,
        'show_completed_tasks' => $showCompleted,
    ];
}

/** @return array{schema:int,title:string,show_completed_tasks:bool} */
function calendar_widget_config_from_storage(mixed $value): array
{
    $defaults = calendar_widget_defaults();
    $config = dashboard_widget_decode_config($value);
    $title = calendar_widget_validate_title($config['title'] ?? null);
    $showCompleted = dashboard_widget_validate_boolean($config['show_completed_tasks'] ?? null);
    return [
        'schema' => 1,
        'title' => $title ?? $defaults['title'],
        'show_completed_tasks' => $showCompleted ?? $defaults['show_completed_tasks'],
    ];
}

function calendar_validate_year(mixed $value): ?int
{
    $year = app_validate_positive_int($value);
    return $year !== null && $year >= 2000 && $year <= 2100 ? $year : null;
}

function calendar_validate_month(mixed $value): ?int
{
    $month = app_validate_positive_int($value);
    return $month !== null && $month <= 12 ? $month : null;
}

function calendar_validate_date(mixed $value): ?string
{
    if (!is_string($value) || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', $value) !== 1) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date instanceof DateTimeImmutable
        || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
        || $date->format('Y-m-d') !== $value
        || (int) $date->format('Y') < 2000
        || (int) $date->format('Y') > 2100) {
        return null;
    }
    return $value;
}

/** @return array{0:string,1:string}|null */
function calendar_validate_event_range(mixed $startValue, mixed $endValue): ?array
{
    $start = calendar_validate_date($startValue);
    $end = calendar_validate_date($endValue);
    if ($start === null || $end === null || $end < $start) {
        return null;
    }
    $startDate = new DateTimeImmutable($start);
    $endDate = new DateTimeImmutable($end);
    if ((int) $startDate->diff($endDate)->days > 365) {
        return null;
    }
    return [$start, $end];
}

function calendar_validate_event_title(mixed $value): ?string
{
    return app_validate_text($value, 128, false);
}

function calendar_validate_event_note(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    return app_validate_text($value, 2000, true);
}

/** @return array{start:string,end:string} */
function calendar_month_range(int $year, int $month): array
{
    if (calendar_validate_year($year) === null || calendar_validate_month($month) === null) {
        throw new InvalidArgumentException('Calendar month is invalid.');
    }
    $start = DateTimeImmutable::createFromFormat('!Y-n-j', $year . '-' . $month . '-1');
    if (!$start instanceof DateTimeImmutable) {
        throw new InvalidArgumentException('Calendar month is invalid.');
    }
    return [
        'start' => $start->format('Y-m-d'),
        'end' => $start->modify('last day of this month')->format('Y-m-d'),
    ];
}

/** @return array<string,mixed>|null */
function calendar_normalize_event_row(array $row): ?array
{
    $eventId = app_validate_positive_int($row['calendar_event_id'] ?? null);
    $owner = app_validate_positive_int($row['calendar_event_owner'] ?? null);
    $title = calendar_validate_event_title($row['calendar_event_title'] ?? null);
    $note = calendar_validate_event_note($row['calendar_event_note'] ?? '');
    $range = calendar_validate_event_range($row['calendar_event_start_date'] ?? null, $row['calendar_event_end_date'] ?? null);
    if ($eventId === null || $owner === null || $title === null || $note === null || $range === null) {
        return null;
    }
    $row['calendar_event_id'] = $eventId;
    $row['calendar_event_owner'] = $owner;
    $row['calendar_event_title'] = $title;
    $row['calendar_event_note'] = $note;
    $row['calendar_event_start_date'] = $range[0];
    $row['calendar_event_end_date'] = $range[1];
    return $row;
}

/** @return array<string,mixed>|null */
function calendar_lock_owned_event(PDO $pdo, int $ownerId, int $eventId): ?array
{
    if ($ownerId <= 0 || $eventId <= 0) {
        return null;
    }
    $sql = 'SELECT * FROM ' . db_table_identifier('calendar_event') . ' '
        . 'WHERE calendar_event_id = :event_id AND calendar_event_owner = :owner AND calendar_event_flag = 0';
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':event_id' => $eventId, ':owner' => $ownerId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function calendar_active_event_count(PDO $pdo, int $ownerId): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM ' . db_table_identifier('calendar_event') . ' '
        . 'WHERE calendar_event_owner = :owner AND calendar_event_flag = 0'
    );
    $stmt->execute([':owner' => $ownerId]);
    return (int) $stmt->fetchColumn();
}

function calendar_create_event(int $ownerId, string $title, string $startDate, string $endDate, string $note): int
{
    $title = calendar_validate_event_title($title);
    $note = calendar_validate_event_note($note);
    $range = calendar_validate_event_range($startDate, $endDate);
    if ($ownerId <= 0 || $title === null || $note === null || $range === null) {
        throw new InvalidArgumentException('Calendar event settings are invalid.');
    }
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        if (calendar_active_event_count($pdo, $ownerId) >= 500) {
            if ($started) {
                $pdo->rollBack();
            }
            throw new LengthException('Calendar can contain up to 500 active events.');
        }
        $now = app_now();
        $stmt = $pdo->prepare(
            'INSERT INTO ' . db_table_identifier('calendar_event') . ' '
            . '(calendar_event_date, calendar_event_updated_at, calendar_event_flag, calendar_event_owner, '
            . 'calendar_event_title, calendar_event_start_date, calendar_event_end_date, calendar_event_note) '
            . 'VALUES (:created_at, :updated_at, 0, :owner, :title, :start_date, :end_date, :note)'
        );
        $stmt->execute([
            ':created_at' => $now,
            ':updated_at' => $now,
            ':owner' => $ownerId,
            ':title' => $title,
            ':start_date' => $range[0],
            ':end_date' => $range[1],
            ':note' => $note,
        ]);
        $eventId = (int) $pdo->lastInsertId();
        if ($started) {
            $pdo->commit();
        }
        return $eventId;
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function calendar_update_event(int $ownerId, int $eventId, string $title, string $startDate, string $endDate, string $note): bool
{
    $title = calendar_validate_event_title($title);
    $note = calendar_validate_event_note($note);
    $range = calendar_validate_event_range($startDate, $endDate);
    if ($ownerId <= 0 || $eventId <= 0 || $title === null || $note === null || $range === null) {
        throw new InvalidArgumentException('Calendar event settings are invalid.');
    }
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        if (calendar_lock_owned_event($pdo, $ownerId, $eventId) === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('calendar_event') . ' SET '
            . 'calendar_event_title = :title, calendar_event_start_date = :start_date, '
            . 'calendar_event_end_date = :end_date, calendar_event_note = :note, '
            . 'calendar_event_updated_at = :updated_at '
            . 'WHERE calendar_event_id = :event_id AND calendar_event_owner = :owner AND calendar_event_flag = 0'
        );
        $stmt->execute([
            ':title' => $title,
            ':start_date' => $range[0],
            ':end_date' => $range[1],
            ':note' => $note,
            ':updated_at' => app_now(),
            ':event_id' => $eventId,
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

function calendar_delete_event(int $ownerId, int $eventId): bool
{
    if ($ownerId <= 0 || $eventId <= 0) {
        return false;
    }
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        if (calendar_lock_owned_event($pdo, $ownerId, $eventId) === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('calendar_event') . ' '
            . 'SET calendar_event_flag = 1, calendar_event_updated_at = :updated_at '
            . 'WHERE calendar_event_id = :event_id AND calendar_event_owner = :owner AND calendar_event_flag = 0'
        );
        $stmt->execute([':updated_at' => app_now(), ':event_id' => $eventId, ':owner' => $ownerId]);
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


/** @return array{schema:int,title:string,show_completed_tasks:bool}|null */
function calendar_owned_widget_config(int $ownerId, int $widgetId): ?array
{
    if ($ownerId <= 0 || $widgetId <= 0) {
        return null;
    }
    $stmt = conn_db()->prepare(
        'SELECT widget_config FROM ' . db_table_identifier('dashboard_widget') . ' '
        . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
        . "AND widget_type = 'calendar' AND widget_flag = 0"
    );
    $stmt->execute([':widget_id' => $widgetId, ':owner' => $ownerId]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : calendar_widget_config_from_storage($value);
}

/** @return array{year:int,month:int,month_start:string,month_end:string,events:list<array<string,mixed>>,tasks:list<array<string,mixed>>} */
function calendar_month_data(int $ownerId, int $year, int $month, bool $showCompletedTasks): array
{
    if ($ownerId <= 0 || calendar_validate_year($year) === null || calendar_validate_month($month) === null) {
        throw new InvalidArgumentException('Calendar month is invalid.');
    }
    $range = calendar_month_range($year, $month);
    $pdo = conn_db();

    $eventStmt = $pdo->prepare(
        'SELECT calendar_event_id, calendar_event_date, calendar_event_updated_at, calendar_event_flag, '
        . 'calendar_event_owner, calendar_event_title, calendar_event_start_date, calendar_event_end_date, calendar_event_note '
        . 'FROM ' . db_table_identifier('calendar_event') . ' '
        . 'WHERE calendar_event_owner = :owner AND calendar_event_flag = 0 '
        . 'AND calendar_event_start_date <= :month_end AND calendar_event_end_date >= :month_start '
        . 'ORDER BY calendar_event_start_date ASC, calendar_event_end_date ASC, calendar_event_id ASC LIMIT 500'
    );
    $eventStmt->execute([':owner' => $ownerId, ':month_start' => $range['start'], ':month_end' => $range['end']]);
    $events = [];
    foreach ($eventStmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $event = calendar_normalize_event_row($row);
        if ($event === null || (int) $event['calendar_event_owner'] !== $ownerId) {
            continue;
        }
        $events[] = [
            'event_id' => $event['calendar_event_id'],
            'title' => $event['calendar_event_title'],
            'start_date' => $event['calendar_event_start_date'],
            'end_date' => $event['calendar_event_end_date'],
            'note' => $event['calendar_event_note'],
            'updated_at' => (string) ($event['calendar_event_updated_at'] ?? ''),
        ];
    }

    $taskSql = 'SELECT t.task_id, t.task_title, t.task_due_date, t.task_priority, t.task_completed, t.task_updated_at '
        . 'FROM ' . db_table_identifier('task') . ' t '
        . 'INNER JOIN ' . db_table_identifier('dashboard_widget') . ' w '
        . 'ON w.widget_id = t.task_widget_id AND w.widget_owner = t.task_owner '
        . "AND w.widget_type = 'task' AND w.widget_flag = 0 "
        . 'WHERE t.task_owner = :owner AND t.task_flag = 0 '
        . 'AND t.task_due_date BETWEEN :month_start AND :month_end ';
    if (!$showCompletedTasks) {
        $taskSql .= 'AND t.task_completed = 0 ';
    }
    $taskSql .= 'ORDER BY t.task_due_date ASC, t.task_completed ASC, t.task_sort_order ASC, t.task_id ASC LIMIT 500';
    $taskStmt = $pdo->prepare($taskSql);
    $taskStmt->execute([':owner' => $ownerId, ':month_start' => $range['start'], ':month_end' => $range['end']]);
    $tasks = [];
    foreach ($taskStmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $taskId = app_validate_positive_int($row['task_id'] ?? null);
        $title = dashboard_widget_validate_task_title($row['task_title'] ?? null);
        $dueDate = dashboard_widget_validate_task_due_date($row['task_due_date'] ?? null);
        $priority = dashboard_widget_validate_task_priority($row['task_priority'] ?? null);
        $completed = dashboard_widget_validate_boolean($row['task_completed'] ?? null);
        if ($taskId === null || $title === null || $dueDate === null || $dueDate === '' || $priority === null || $completed === null) {
            continue;
        }
        $tasks[] = [
            'task_id' => $taskId,
            'title' => $title,
            'due_date' => $dueDate,
            'priority' => $priority,
            'completed' => $completed,
            'updated_at' => (string) ($row['task_updated_at'] ?? ''),
        ];
    }

    $holidayState = function_exists('japanese_holiday_current_data')
        ? japanese_holiday_current_data()
        : ['holidays' => [], 'refresh_due' => false, 'source' => 'unavailable'];
    $holidayPrefix = sprintf('%04d-%02d-', $year, $month);
    $holidays = [];
    foreach ($holidayState['holidays'] as $holidayDate => $holidayName) {
        if (str_starts_with($holidayDate, $holidayPrefix)) {
            $holidays[$holidayDate] = $holidayName;
        }
    }

    return [
        'year' => $year,
        'month' => $month,
        'month_start' => $range['start'],
        'month_end' => $range['end'],
        'events' => $events,
        'tasks' => $tasks,
        'holidays' => $holidays,
        'holiday_refresh_due' => (bool) $holidayState['refresh_due'],
        'holiday_source' => (string) $holidayState['source'],
    ];
}
