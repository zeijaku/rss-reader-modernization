<?php

declare(strict_types=1);

/** V1.25-B: strict all-day flag used by Calendar event metadata. */
function calendar_event_time_validate_all_day(mixed $value): ?bool
{
    if ($value === true || $value === 1 || $value === '1') {
        return true;
    }
    if ($value === false || $value === 0 || $value === '0') {
        return false;
    }
    return null;
}

/**
 * Validate a Calendar clock value.
 * Empty string means "not set"; valid values are normalized to HH:MM:SS.
 */
function calendar_event_time_validate_clock(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return '';
    }
    if (!is_string($value)) {
        return null;
    }
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?\z/D', $value) !== 1) {
        return null;
    }
    return strlen($value) === 5 ? $value . ':00' : $value;
}

/**
 * Empty Calendar URLs are stored as NULL. Invalid/non-http(s) URLs return false.
 * No outbound request is performed here.
 */
function calendar_event_time_validate_url(mixed $value): string|false
{
    if ($value === null || $value === '') {
        return '';
    }
    if (!is_string($value)) {
        return false;
    }
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $url = app_validate_external_link($value, 2048);
    return $url === null ? false : $url;
}

/** @return array{all_day:bool,start_time:?string,end_time:?string,url:?string}|null */
function calendar_event_time_settings(
    mixed $allDayValue,
    mixed $startTimeValue,
    mixed $endTimeValue,
    mixed $urlValue,
    string $startDate,
    string $endDate
): ?array {
    $range = calendar_validate_event_range($startDate, $endDate);
    $allDay = calendar_event_time_validate_all_day($allDayValue);
    $startTime = calendar_event_time_validate_clock($startTimeValue);
    $endTime = calendar_event_time_validate_clock($endTimeValue);
    $url = calendar_event_time_validate_url($urlValue);
    if ($range === null || $allDay === null || $startTime === null || $endTime === null || $url === false) {
        return null;
    }

    if ($allDay) {
        $startTime = '';
        $endTime = '';
    } elseif ($startTime === '') {
        return null;
    } elseif ($range[0] === $range[1] && $endTime !== '' && $endTime < $startTime) {
        return null;
    }

    return [
        'all_day' => $allDay,
        'start_time' => $startTime === '' ? null : $startTime,
        'end_time' => $endTime === '' ? null : $endTime,
        'url' => $url === '' ? null : $url,
    ];
}

function calendar_event_time_public_clock(mixed $value): ?string
{
    $time = calendar_event_time_validate_clock($value);
    return $time === null || $time === '' ? null : substr($time, 0, 5);
}

/** @return list<array{event_id:int,all_day:bool,start_time:?string,end_time:?string,url:?string}> */
function calendar_event_time_month_list(int $ownerId, int $year, int $month): array
{
    if ($ownerId <= 0 || calendar_validate_year($year) === null || calendar_validate_month($month) === null) {
        throw new InvalidArgumentException('Calendar event metadata month is invalid.');
    }
    $range = calendar_month_range($year, $month);
    $stmt = conn_db()->prepare(
        'SELECT calendar_event_id, calendar_event_all_day, calendar_event_start_time, '
        . 'calendar_event_end_time, calendar_event_url FROM ' . db_table_identifier('calendar_event') . ' '
        . 'WHERE calendar_event_owner = :owner AND calendar_event_flag = 0 '
        . 'AND calendar_event_start_date <= :month_end AND calendar_event_end_date >= :month_start '
        . 'ORDER BY calendar_event_id ASC LIMIT 500'
    );
    $stmt->execute([
        ':owner' => $ownerId,
        ':month_start' => $range['start'],
        ':month_end' => $range['end'],
    ]);

    $events = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $eventId = app_validate_positive_int($row['calendar_event_id'] ?? null);
        if ($eventId === null) {
            continue;
        }
        $allDay = calendar_event_time_validate_all_day($row['calendar_event_all_day'] ?? '1');
        $url = calendar_event_time_validate_url($row['calendar_event_url'] ?? '');
        $events[] = [
            'event_id' => $eventId,
            'all_day' => $allDay ?? true,
            'start_time' => calendar_event_time_public_clock($row['calendar_event_start_time'] ?? null),
            'end_time' => calendar_event_time_public_clock($row['calendar_event_end_time'] ?? null),
            'url' => $url === false || $url === '' ? null : $url,
        ];
    }
    return $events;
}

/** @param array{all_day:bool,start_time:?string,end_time:?string,url:?string} $settings */
function calendar_event_time_apply(PDO $pdo, int $ownerId, int $eventId, array $settings): void
{
    if ($ownerId <= 0 || $eventId <= 0) {
        throw new InvalidArgumentException('Calendar event metadata target is invalid.');
    }
    $stmt = $pdo->prepare(
        'UPDATE ' . db_table_identifier('calendar_event') . ' SET '
        . 'calendar_event_all_day = :all_day, calendar_event_start_time = :start_time, '
        . 'calendar_event_end_time = :end_time, calendar_event_url = :url '
        . 'WHERE calendar_event_id = :event_id AND calendar_event_owner = :owner AND calendar_event_flag = 0'
    );
    $stmt->execute([
        ':all_day' => $settings['all_day'] ? 1 : 0,
        ':start_time' => $settings['start_time'],
        ':end_time' => $settings['end_time'],
        ':url' => $settings['url'],
        ':event_id' => $eventId,
        ':owner' => $ownerId,
    ]);
}

/** @param array{all_day:bool,start_time:?string,end_time:?string,url:?string} $settings */
function calendar_event_time_color_create(
    int $ownerId,
    string $title,
    string $startDate,
    string $endDate,
    string $note,
    string $color,
    array $settings
): int {
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        $eventId = calendar_event_color_create($ownerId, $title, $startDate, $endDate, $note, $color);
        calendar_event_time_apply($pdo, $ownerId, $eventId, $settings);
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

/** @param array{all_day:bool,start_time:?string,end_time:?string,url:?string} $settings */
function calendar_event_time_color_update(
    int $ownerId,
    int $eventId,
    string $title,
    string $startDate,
    string $endDate,
    string $note,
    string $color,
    array $settings
): bool {
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        if (!calendar_event_color_update($ownerId, $eventId, $title, $startDate, $endDate, $note, $color)) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        calendar_event_time_apply($pdo, $ownerId, $eventId, $settings);
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
