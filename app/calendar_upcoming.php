<?php

declare(strict_types=1);

const CALENDAR_UPCOMING_DAYS = 14;
const CALENDAR_UPCOMING_LIMIT = 8;

/** @return list<array<string,mixed>> */
function calendar_event_upcoming_list(int $ownerId, string $today): array
{
    $windowStart = calendar_recurrence_date($today);
    if ($ownerId <= 0 || !$windowStart) {
        throw new InvalidArgumentException('Calendar upcoming range is invalid.');
    }
    $windowEnd = $windowStart->modify('+' . (CALENDAR_UPCOMING_DAYS - 1) . ' days');
    $windowStartValue = $windowStart->format('Y-m-d');
    $windowEndValue = $windowEnd->format('Y-m-d');

    $stmt = conn_db()->prepare(
        'SELECT calendar_event_id, calendar_event_date, calendar_event_updated_at, calendar_event_flag, '
        . 'calendar_event_owner, calendar_event_title, calendar_event_start_date, calendar_event_end_date, '
        . 'calendar_event_note, calendar_event_color, calendar_event_all_day, calendar_event_start_time, '
        . 'calendar_event_end_time, calendar_event_url, calendar_event_repeat_type, calendar_event_repeat_until '
        . 'FROM ' . db_table_identifier('calendar_event') . ' '
        . 'WHERE calendar_event_owner = :owner AND calendar_event_flag = 0 '
        . "AND calendar_event_repeat_type = 'none' "
        . 'AND calendar_event_start_date <= :window_end AND calendar_event_end_date >= :window_start '
        . 'ORDER BY calendar_event_start_date ASC, calendar_event_end_date ASC, calendar_event_id ASC LIMIT 500'
    );
    $stmt->execute([
        ':owner' => $ownerId,
        ':window_start' => $windowStartValue,
        ':window_end' => $windowEndValue,
    ]);

    $events = [];
    $seen = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $event = calendar_normalize_event_row($row);
        if ($event === null || (int) $event['calendar_event_owner'] !== $ownerId) {
            continue;
        }
        $allDay = calendar_event_time_validate_all_day($row['calendar_event_all_day'] ?? '1') ?? true;
        $urlValue = calendar_event_time_validate_url($row['calendar_event_url'] ?? '');
        $item = [
            'event_id' => (int) $event['calendar_event_id'],
            'title' => $event['calendar_event_title'],
            'note' => $event['calendar_event_note'],
            'color' => calendar_event_color_validate($row['calendar_event_color'] ?? null) ?? 'blue',
            'occurrence_start_date' => $event['calendar_event_start_date'],
            'occurrence_end_date' => $event['calendar_event_end_date'],
            'source_start_date' => $event['calendar_event_start_date'],
            'source_end_date' => $event['calendar_event_end_date'],
            'all_day' => $allDay,
            'start_time' => $allDay ? null : calendar_event_time_public_clock($row['calendar_event_start_time'] ?? null),
            'end_time' => $allDay ? null : calendar_event_time_public_clock($row['calendar_event_end_time'] ?? null),
            'url' => $urlValue === false || $urlValue === '' ? null : $urlValue,
            'repeat_type' => 'none',
            'repeat_until' => null,
            'updated_at' => (string) ($event['calendar_event_updated_at'] ?? ''),
        ];
        $key = $item['event_id'] . ':' . $item['occurrence_start_date'];
        $seen[$key] = true;
        $events[] = $item;
    }

    $month = $windowStart->modify('first day of this month');
    $lastMonth = $windowEnd->modify('first day of this month');
    while ($month <= $lastMonth) {
        foreach (calendar_event_recurrence_month_list(
            $ownerId,
            (int) $month->format('Y'),
            (int) $month->format('n')
        ) as $item) {
            $start = (string) ($item['occurrence_start_date'] ?? '');
            $end = (string) ($item['occurrence_end_date'] ?? '');
            if ($start === '' || $end === '' || $end < $windowStartValue || $start > $windowEndValue) {
                continue;
            }
            $key = (int) ($item['event_id'] ?? 0) . ':' . $start;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $events[] = $item;
        }
        $month = $month->modify('first day of next month');
    }

    usort($events, static function (array $left, array $right) use ($windowStartValue): int {
        $leftDate = max($windowStartValue, (string) ($left['occurrence_start_date'] ?? ''));
        $rightDate = max($windowStartValue, (string) ($right['occurrence_start_date'] ?? ''));
        $leftTime = ($left['all_day'] ?? true) === true ? '' : (string) ($left['start_time'] ?? '');
        $rightTime = ($right['all_day'] ?? true) === true ? '' : (string) ($right['start_time'] ?? '');
        if ((string) ($left['occurrence_start_date'] ?? '') < $windowStartValue) {
            $leftTime = '';
        }
        if ((string) ($right['occurrence_start_date'] ?? '') < $windowStartValue) {
            $rightTime = '';
        }
        return [$leftDate, $leftTime, (int) ($left['event_id'] ?? 0)]
            <=> [$rightDate, $rightTime, (int) ($right['event_id'] ?? 0)];
    });

    return array_slice($events, 0, CALENDAR_UPCOMING_LIMIT);
}
