<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_exception.php';

const CALENDAR_RANGE_MAX_DAYS = 42;
const CALENDAR_RANGE_MAX_SOURCE_EVENTS = 500;
const CALENDAR_RANGE_MAX_OCCURRENCES = 2000;
const CALENDAR_RANGE_MAX_TASKS = 500;

/** @return array{start:string,end:string,days:int}|null */
function calendar_range_validate(mixed $startValue, mixed $endValue): ?array
{
    $start = calendar_validate_date($startValue);
    $end = calendar_validate_date($endValue);
    if ($start === null || $end === null || $end < $start) {
        return null;
    }
    $days = (int) (new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days + 1;
    if ($days < 1 || $days > CALENDAR_RANGE_MAX_DAYS) {
        return null;
    }
    return ['start' => $start, 'end' => $end, 'days' => $days];
}

/** @return array<string,mixed>|null */
function calendar_range_normalize_non_recurring_row(array $row): ?array
{
    $event = calendar_normalize_event_row($row);
    if ($event === null) {
        return null;
    }
    $allDay = calendar_event_time_validate_all_day($row['calendar_event_all_day'] ?? '1') ?? true;
    $urlValue = calendar_event_time_validate_url($row['calendar_event_url'] ?? '');
    $eventId = (int) $event['calendar_event_id'];
    $originalStart = (string) $event['calendar_event_start_date'];
    return [
        'kind' => 'event',
        'event_id' => $eventId,
        'occurrence_key' => calendar_event_occurrence_key($eventId, $originalStart),
        'original_occurrence_start_date' => $originalStart,
        'title' => $event['calendar_event_title'],
        'note' => $event['calendar_event_note'],
        'color' => calendar_event_color_validate($row['calendar_event_color'] ?? null) ?? 'blue',
        'occurrence_start_date' => $originalStart,
        'occurrence_end_date' => $event['calendar_event_end_date'],
        'source_start_date' => $originalStart,
        'source_end_date' => $event['calendar_event_end_date'],
        'all_day' => $allDay,
        'start_time' => $allDay ? null : calendar_event_time_public_clock($row['calendar_event_start_time'] ?? null),
        'end_time' => $allDay ? null : calendar_event_time_public_clock($row['calendar_event_end_time'] ?? null),
        'url' => $urlValue === false || $urlValue === '' ? null : $urlValue,
        'repeat_type' => 'none',
        'repeat_until' => null,
        'updated_at' => (string) ($event['calendar_event_updated_at'] ?? ''),
    ];
}

/** @return array{events:list<array<string,mixed>>,cancelled_occurrences:list<array<string,mixed>>} */
function calendar_range_event_state(int $ownerId, string $rangeStart, string $rangeEnd): array
{
    $range = calendar_range_validate($rangeStart, $rangeEnd);
    if ($ownerId <= 0 || $range === null) {
        throw new InvalidArgumentException('Calendar range is invalid.');
    }

    $stmt = conn_db()->prepare(
        'SELECT calendar_event_id, calendar_event_date, calendar_event_updated_at, calendar_event_flag, '
        . 'calendar_event_owner, calendar_event_title, calendar_event_start_date, calendar_event_end_date, '
        . 'calendar_event_note, calendar_event_color, calendar_event_all_day, calendar_event_start_time, '
        . 'calendar_event_end_time, calendar_event_url, calendar_event_repeat_type, calendar_event_repeat_until '
        . 'FROM ' . db_table_identifier('calendar_event') . ' '
        . 'WHERE calendar_event_owner = :owner AND calendar_event_flag = 0 '
        . 'AND calendar_event_start_date <= :range_end '
        . "AND (calendar_event_repeat_type <> 'none' OR calendar_event_end_date >= :range_start) "
        . 'ORDER BY calendar_event_id ASC LIMIT ' . (CALENDAR_RANGE_MAX_SOURCE_EVENTS + 1)
    );
    $stmt->execute([
        ':owner' => $ownerId,
        ':range_start' => $range['start'],
        ':range_end' => $range['end'],
    ]);
    $rows = $stmt->fetchAll();
    if (count($rows) > CALENDAR_RANGE_MAX_SOURCE_EVENTS) {
        throw new LengthException('Calendar contains too many source events for one range.');
    }

    $events = [];
    $seen = [];
    foreach ($rows as $row) {
        if (!is_array($row) || (int) ($row['calendar_event_owner'] ?? 0) !== $ownerId) {
            continue;
        }
        $repeatType = calendar_event_recurrence_validate_type($row['calendar_event_repeat_type'] ?? null) ?? 'none';
        $items = [];
        if ($repeatType === 'none') {
            $item = calendar_range_normalize_non_recurring_row($row);
            if ($item !== null) {
                $items[] = $item;
            }
        } else {
            $items = calendar_event_recurrence_expand_row($row, $range['start'], $range['end']);
        }

        foreach ($items as $item) {
            $eventId = (int) ($item['event_id'] ?? 0);
            $originalStart = (string) ($item['original_occurrence_start_date']
                ?? $item['occurrence_start_date'] ?? '');
            $occurrenceRange = calendar_validate_event_range(
                $item['occurrence_start_date'] ?? null,
                $item['occurrence_end_date'] ?? null
            );
            if ($eventId <= 0 || calendar_validate_date($originalStart) === null || $occurrenceRange === null
                || $occurrenceRange[1] < $range['start'] || $occurrenceRange[0] > $range['end']) {
                continue;
            }
            $key = (string) ($item['occurrence_key'] ?? calendar_event_occurrence_key($eventId, $originalStart));
            if (isset($seen[$key])) {
                continue;
            }
            $item['kind'] = 'event';
            $item['occurrence_key'] = $key;
            $item['original_occurrence_start_date'] = $originalStart;
            $seen[$key] = true;
            $events[] = $item;
            if (count($events) > CALENDAR_RANGE_MAX_OCCURRENCES) {
                throw new LengthException('Calendar occurrence expansion is too large for one range.');
            }
        }
    }

    usort($events, static function (array $left, array $right): int {
        $leftTime = ($left['all_day'] ?? true) === true ? '' : (string) ($left['start_time'] ?? '');
        $rightTime = ($right['all_day'] ?? true) === true ? '' : (string) ($right['start_time'] ?? '');
        return [
            (string) ($left['occurrence_start_date'] ?? ''),
            $leftTime,
            (string) ($left['occurrence_end_date'] ?? ''),
            (int) ($left['event_id'] ?? 0),
            (string) ($left['occurrence_key'] ?? ''),
        ] <=> [
            (string) ($right['occurrence_start_date'] ?? ''),
            $rightTime,
            (string) ($right['occurrence_end_date'] ?? ''),
            (int) ($right['event_id'] ?? 0),
            (string) ($right['occurrence_key'] ?? ''),
        ];
    });
    return calendar_event_exception_apply_range($ownerId, $range['start'], $range['end'], $events);
}

/** @return list<array<string,mixed>> */
function calendar_range_event_list(int $ownerId, string $rangeStart, string $rangeEnd): array
{
    return calendar_range_event_state($ownerId, $rangeStart, $rangeEnd)['events'];
}

/** @return list<array<string,mixed>> */
function calendar_range_task_list(
    int $ownerId,
    string $rangeStart,
    string $rangeEnd,
    bool $showCompletedTasks
): array {
    $range = calendar_range_validate($rangeStart, $rangeEnd);
    if ($ownerId <= 0 || $range === null) {
        throw new InvalidArgumentException('Calendar task range is invalid.');
    }
    $sql = 'SELECT t.task_id, t.task_title, t.task_due_date, t.task_priority, t.task_completed, t.task_updated_at '
        . 'FROM ' . db_table_identifier('task') . ' t '
        . 'INNER JOIN ' . db_table_identifier('dashboard_widget') . ' w '
        . 'ON w.widget_id = t.task_widget_id AND w.widget_owner = t.task_owner '
        . "AND w.widget_type = 'task' AND w.widget_flag = 0 "
        . 'WHERE t.task_owner = :owner AND t.task_flag = 0 '
        . 'AND t.task_due_date BETWEEN :range_start AND :range_end ';
    if (!$showCompletedTasks) {
        $sql .= 'AND t.task_completed = 0 ';
    }
    $sql .= 'ORDER BY t.task_due_date ASC, t.task_completed ASC, t.task_sort_order ASC, t.task_id ASC '
        . 'LIMIT ' . (CALENDAR_RANGE_MAX_TASKS + 1);
    $stmt = conn_db()->prepare($sql);
    $stmt->execute([
        ':owner' => $ownerId,
        ':range_start' => $range['start'],
        ':range_end' => $range['end'],
    ]);
    $rows = $stmt->fetchAll();
    if (count($rows) > CALENDAR_RANGE_MAX_TASKS) {
        throw new LengthException('Calendar contains too many Tasks for one range.');
    }

    $tasks = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $taskId = app_validate_positive_int($row['task_id'] ?? null);
        $title = dashboard_widget_validate_task_title($row['task_title'] ?? null);
        $dueDate = dashboard_widget_validate_task_due_date($row['task_due_date'] ?? null);
        $priority = dashboard_widget_validate_task_priority($row['task_priority'] ?? null);
        $completed = dashboard_widget_validate_boolean($row['task_completed'] ?? null);
        if ($taskId === null || $title === null || $dueDate === null || $dueDate === ''
            || $priority === null || $completed === null) {
            continue;
        }
        $tasks[] = [
            'kind' => 'task',
            'task_id' => $taskId,
            'title' => $title,
            'due_date' => $dueDate,
            'priority' => $priority,
            'completed' => $completed,
            'updated_at' => (string) ($row['task_updated_at'] ?? ''),
        ];
    }
    return $tasks;
}

/** @return array<string,mixed> */
function calendar_range_data(int $ownerId, int $widgetId, string $rangeStart, string $rangeEnd): array
{
    $range = calendar_range_validate($rangeStart, $rangeEnd);
    if ($ownerId <= 0 || $widgetId <= 0 || $range === null) {
        throw new InvalidArgumentException('Calendar range request is invalid.');
    }
    $config = calendar_owned_widget_config($ownerId, $widgetId);
    if ($config === null) {
        throw new OutOfBoundsException('Calendar Widget was not found.');
    }
    $holidayState = function_exists('japanese_holiday_current_data')
        ? japanese_holiday_current_data()
        : ['holidays' => [], 'refresh_due' => false, 'source' => 'unavailable'];
    $holidays = [];
    foreach ($holidayState['holidays'] as $date => $name) {
        if ($date >= $range['start'] && $date <= $range['end']) {
            $holidays[$date] = $name;
        }
    }
    $eventState = calendar_range_event_state($ownerId, $range['start'], $range['end']);
    return [
        'range_start' => $range['start'],
        'range_end' => $range['end'],
        'range_days' => $range['days'],
        'events' => $eventState['events'],
        'cancelled_occurrences' => $eventState['cancelled_occurrences'],
        'tasks' => calendar_range_task_list(
            $ownerId,
            $range['start'],
            $range['end'],
            $config['show_completed_tasks']
        ),
        'holidays' => $holidays,
        'holiday_refresh_due' => (bool) $holidayState['refresh_due'],
        'holiday_source' => (string) $holidayState['source'],
    ];
}
