<?php

declare(strict_types=1);

const CALENDAR_RECURRENCE_MAX_ACTIVE_SERIES = 50;
const CALENDAR_RECURRENCE_MAX_MONTH_OCCURRENCES = 2000;

function calendar_event_recurrence_validate_type(mixed $value): ?string
{
    return is_string($value) && in_array($value, ['none', 'daily', 'weekly', 'monthly', 'yearly'], true)
        ? $value
        : null;
}

/** @return array{repeat_type:string,repeat_until:?string}|null */
function calendar_event_recurrence_settings(
    mixed $repeatTypeValue,
    mixed $repeatUntilValue,
    string $startDate,
    string $endDate
): ?array {
    $range = calendar_validate_event_range($startDate, $endDate);
    $repeatType = calendar_event_recurrence_validate_type($repeatTypeValue);
    if ($range === null || $repeatType === null) {
        return null;
    }

    $repeatUntil = null;
    if ($repeatUntilValue !== null && $repeatUntilValue !== '') {
        $repeatUntil = calendar_validate_date($repeatUntilValue);
        if ($repeatUntil === null) {
            return null;
        }
    }

    if ($repeatType === 'none') {
        return ['repeat_type' => 'none', 'repeat_until' => null];
    }
    if ($repeatUntil !== null && $repeatUntil < $range[0]) {
        return null;
    }

    $start = new DateTimeImmutable($range[0]);
    $end = new DateTimeImmutable($range[1]);
    $spanDays = (int) $start->diff($end)->days;
    $spanValid = match ($repeatType) {
        'daily' => $spanDays === 0,
        'weekly' => $spanDays <= 6,
        'monthly' => $spanDays <= 27,
        'yearly' => $spanDays <= 365,
        default => false,
    };
    if (!$spanValid) {
        return null;
    }

    return ['repeat_type' => $repeatType, 'repeat_until' => $repeatUntil];
}

function calendar_event_recurrence_active_count(PDO $pdo, int $ownerId, int $excludeEventId = 0): int
{
    if ($ownerId <= 0) {
        return 0;
    }
    $sql = 'SELECT COUNT(*) FROM ' . db_table_identifier('calendar_event') . ' '
        . 'WHERE calendar_event_owner = :owner AND calendar_event_flag = 0 '
        . "AND calendar_event_repeat_type <> 'none'";
    $params = [':owner' => $ownerId];
    if ($excludeEventId > 0) {
        $sql .= ' AND calendar_event_id <> :event_id';
        $params[':event_id'] = $excludeEventId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/** @param array{repeat_type:string,repeat_until:?string} $settings */
function calendar_event_recurrence_apply(PDO $pdo, int $ownerId, int $eventId, array $settings): void
{
    if ($ownerId <= 0 || $eventId <= 0) {
        throw new InvalidArgumentException('Calendar recurrence target is invalid.');
    }
    $repeatType = calendar_event_recurrence_validate_type($settings['repeat_type'] ?? null);
    $repeatUntil = $settings['repeat_until'] ?? null;
    if ($repeatType === null || ($repeatUntil !== null && calendar_validate_date($repeatUntil) === null)) {
        throw new InvalidArgumentException('Calendar recurrence settings are invalid.');
    }
    if ($repeatType !== 'none'
        && calendar_event_recurrence_active_count($pdo, $ownerId, $eventId) >= CALENDAR_RECURRENCE_MAX_ACTIVE_SERIES) {
        throw new LengthException('Calendar can contain up to 50 active recurring series.');
    }

    $stmt = $pdo->prepare(
        'UPDATE ' . db_table_identifier('calendar_event') . ' SET '
        . 'calendar_event_repeat_type = :repeat_type, calendar_event_repeat_until = :repeat_until '
        . 'WHERE calendar_event_id = :event_id AND calendar_event_owner = :owner AND calendar_event_flag = 0'
    );
    $stmt->execute([
        ':repeat_type' => $repeatType,
        ':repeat_until' => $repeatType === 'none' ? null : $repeatUntil,
        ':event_id' => $eventId,
        ':owner' => $ownerId,
    ]);
}

/**
 * Save color/time/URL/recurrence in one transaction while reusing the V1.25-B path.
 *
 * @param array{all_day:bool,start_time:?string,end_time:?string,url:?string} $timeSettings
 * @param array{repeat_type:string,repeat_until:?string} $repeatSettings
 */
function calendar_event_recurrence_time_color_create(
    int $ownerId,
    string $title,
    string $startDate,
    string $endDate,
    string $note,
    string $color,
    array $timeSettings,
    array $repeatSettings
): int {
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        $eventId = calendar_event_time_color_create(
            $ownerId,
            $title,
            $startDate,
            $endDate,
            $note,
            $color,
            $timeSettings
        );
        calendar_event_recurrence_apply($pdo, $ownerId, $eventId, $repeatSettings);
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

/**
 * @param array{all_day:bool,start_time:?string,end_time:?string,url:?string} $timeSettings
 * @param array{repeat_type:string,repeat_until:?string} $repeatSettings
 */
function calendar_event_recurrence_time_color_update(
    int $ownerId,
    int $eventId,
    string $title,
    string $startDate,
    string $endDate,
    string $note,
    string $color,
    array $timeSettings,
    array $repeatSettings
): bool {
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        if (!calendar_event_time_color_update(
            $ownerId,
            $eventId,
            $title,
            $startDate,
            $endDate,
            $note,
            $color,
            $timeSettings
        )) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        calendar_event_recurrence_apply($pdo, $ownerId, $eventId, $repeatSettings);
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

function calendar_recurrence_date(string $value): ?DateTimeImmutable
{
    if (preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', $value) !== 1) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date instanceof DateTimeImmutable
        || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
        || $date->format('Y-m-d') !== $value) {
        return null;
    }
    return $date;
}

function calendar_recurrence_build_date(int $year, int $month, int $day): ?DateTimeImmutable
{
    return calendar_recurrence_date(sprintf('%04d-%02d-%02d', $year, $month, $day));
}

function calendar_recurrence_month_candidate(DateTimeImmutable $anchor, int $offset): ?DateTimeImmutable
{
    $base = ((int) $anchor->format('Y')) * 12 + ((int) $anchor->format('n') - 1) + $offset;
    $year = intdiv($base, 12);
    $month = ($base % 12) + 1;
    return calendar_recurrence_build_date($year, $month, (int) $anchor->format('j'));
}

function calendar_recurrence_year_candidate(DateTimeImmutable $anchor, int $offset): ?DateTimeImmutable
{
    return calendar_recurrence_build_date(
        (int) $anchor->format('Y') + $offset,
        (int) $anchor->format('n'),
        (int) $anchor->format('j')
    );
}

/** @return list<string> */
function calendar_recurrence_occurrence_starts(
    string $repeatType,
    string $anchorDate,
    string $windowStartDate,
    string $windowEndDate,
    ?string $repeatUntil
): array {
    $repeatType = calendar_event_recurrence_validate_type($repeatType) ?? 'none';
    if ($repeatType === 'none') {
        return [];
    }
    $anchor = calendar_recurrence_date($anchorDate);
    $windowStart = calendar_recurrence_date($windowStartDate);
    $windowEnd = calendar_recurrence_date($windowEndDate);
    $until = $repeatUntil === null ? null : calendar_recurrence_date($repeatUntil);
    if (!$anchor || !$windowStart || !$windowEnd || $windowEnd < $windowStart) {
        return [];
    }
    if ($windowStart < $anchor) {
        $windowStart = $anchor;
    }
    if ($until !== null && $until < $windowStart) {
        return [];
    }

    $starts = [];
    $append = static function (DateTimeImmutable $candidate) use (&$starts, $windowStart, $windowEnd, $until): bool {
        if ($candidate > $windowEnd || ($until !== null && $candidate > $until)) {
            return false;
        }
        if ($candidate >= $windowStart) {
            $starts[] = $candidate->format('Y-m-d');
        }
        return true;
    };

    if ($repeatType === 'daily' || $repeatType === 'weekly') {
        $step = $repeatType === 'daily' ? 1 : 7;
        $daysFromAnchor = $anchor < $windowStart ? (int) $anchor->diff($windowStart)->days : 0;
        $offset = $step === 1 ? $daysFromAnchor : intdiv($daysFromAnchor + 6, 7) * 7;
        $candidate = $anchor->modify('+' . $offset . ' days');
        while ($candidate <= $windowEnd) {
            if (!$append($candidate)) {
                break;
            }
            $candidate = $candidate->modify('+' . $step . ' days');
        }
        return $starts;
    }

    if ($repeatType === 'monthly') {
        $monthDiff = (((int) $windowStart->format('Y')) - ((int) $anchor->format('Y'))) * 12
            + ((int) $windowStart->format('n') - (int) $anchor->format('n'));
        $offset = max(0, $monthDiff - 1);
        for ($guard = 0; $guard < 18; $guard++, $offset++) {
            $total = ((int) $anchor->format('Y')) * 12 + ((int) $anchor->format('n') - 1) + $offset;
            $firstOfMonth = calendar_recurrence_build_date(intdiv($total, 12), ($total % 12) + 1, 1);
            if ($firstOfMonth !== null && $firstOfMonth > $windowEnd) {
                break;
            }
            $candidate = calendar_recurrence_month_candidate($anchor, $offset);
            if ($candidate === null || $candidate < $windowStart) {
                continue;
            }
            if (!$append($candidate)) {
                break;
            }
        }
        return $starts;
    }

    $yearDiff = (int) $windowStart->format('Y') - (int) $anchor->format('Y');
    $offset = max(0, $yearDiff - 1);
    for ($guard = 0; $guard < 4; $guard++, $offset++) {
        $candidate = calendar_recurrence_year_candidate($anchor, $offset);
        if ($candidate === null || $candidate < $windowStart) {
            continue;
        }
        if (!$append($candidate)) {
            break;
        }
    }
    return $starts;
}

/** @return list<array<string,mixed>> */
function calendar_event_recurrence_expand_row(array $row, string $monthStart, string $monthEnd): array
{
    $event = calendar_normalize_event_row($row);
    if ($event === null) {
        return [];
    }
    $settings = calendar_event_recurrence_settings(
        $row['calendar_event_repeat_type'] ?? null,
        $row['calendar_event_repeat_until'] ?? null,
        $event['calendar_event_start_date'],
        $event['calendar_event_end_date']
    );
    if ($settings === null || $settings['repeat_type'] === 'none') {
        return [];
    }

    $start = new DateTimeImmutable($event['calendar_event_start_date']);
    $end = new DateTimeImmutable($event['calendar_event_end_date']);
    $spanDays = (int) $start->diff($end)->days;
    $monthStartDate = calendar_recurrence_date($monthStart);
    $monthEndDate = calendar_recurrence_date($monthEnd);
    if (!$monthStartDate || !$monthEndDate) {
        return [];
    }
    $windowStart = $monthStartDate->modify('-' . $spanDays . ' days')->format('Y-m-d');
    $starts = calendar_recurrence_occurrence_starts(
        $settings['repeat_type'],
        $event['calendar_event_start_date'],
        $windowStart,
        $monthEnd,
        $settings['repeat_until']
    );

    $color = calendar_event_color_validate($row['calendar_event_color'] ?? null) ?? 'blue';
    $allDay = calendar_event_time_validate_all_day($row['calendar_event_all_day'] ?? '1') ?? true;
    $startTime = calendar_event_time_public_clock($row['calendar_event_start_time'] ?? null);
    $endTime = calendar_event_time_public_clock($row['calendar_event_end_time'] ?? null);
    $urlValue = calendar_event_time_validate_url($row['calendar_event_url'] ?? '');
    $url = $urlValue === false || $urlValue === '' ? null : $urlValue;

    $occurrences = [];
    foreach ($starts as $occurrenceStart) {
        $occurrenceStartDate = calendar_recurrence_date($occurrenceStart);
        if (!$occurrenceStartDate) {
            continue;
        }
        $occurrenceEnd = $occurrenceStartDate->modify('+' . $spanDays . ' days')->format('Y-m-d');
        if ($occurrenceEnd < $monthStart || $occurrenceStart > $monthEnd) {
            continue;
        }
        $occurrences[] = [
            'event_id' => (int) $event['calendar_event_id'],
            'title' => $event['calendar_event_title'],
            'note' => $event['calendar_event_note'],
            'color' => $color,
            'occurrence_start_date' => $occurrenceStart,
            'occurrence_end_date' => $occurrenceEnd,
            'source_start_date' => $event['calendar_event_start_date'],
            'source_end_date' => $event['calendar_event_end_date'],
            'all_day' => $allDay,
            'start_time' => $allDay ? null : $startTime,
            'end_time' => $allDay ? null : $endTime,
            'url' => $url,
            'repeat_type' => $settings['repeat_type'],
            'repeat_until' => $settings['repeat_until'],
            'updated_at' => (string) ($event['calendar_event_updated_at'] ?? ''),
        ];
    }
    return $occurrences;
}

/** @return list<array<string,mixed>> */
function calendar_event_recurrence_month_list(int $ownerId, int $year, int $month): array
{
    if ($ownerId <= 0 || calendar_validate_year($year) === null || calendar_validate_month($month) === null) {
        throw new InvalidArgumentException('Calendar recurrence month is invalid.');
    }
    $range = calendar_month_range($year, $month);
    $stmt = conn_db()->prepare(
        'SELECT calendar_event_id, calendar_event_date, calendar_event_updated_at, calendar_event_flag, '
        . 'calendar_event_owner, calendar_event_title, calendar_event_start_date, calendar_event_end_date, '
        . 'calendar_event_note, calendar_event_color, calendar_event_all_day, calendar_event_start_time, '
        . 'calendar_event_end_time, calendar_event_url, calendar_event_repeat_type, calendar_event_repeat_until '
        . 'FROM ' . db_table_identifier('calendar_event') . ' '
        . 'WHERE calendar_event_owner = :owner AND calendar_event_flag = 0 '
        . "AND calendar_event_repeat_type <> 'none' AND calendar_event_start_date <= :month_end "
        . 'ORDER BY calendar_event_id ASC LIMIT ' . CALENDAR_RECURRENCE_MAX_ACTIVE_SERIES
    );
    $stmt->execute([':owner' => $ownerId, ':month_end' => $range['end']]);

    $events = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        foreach (calendar_event_recurrence_expand_row($row, $range['start'], $range['end']) as $occurrence) {
            $events[] = $occurrence;
            if (count($events) > CALENDAR_RECURRENCE_MAX_MONTH_OCCURRENCES) {
                throw new LengthException('Calendar recurrence expansion is too large for one month.');
            }
        }
    }

    usort($events, static function (array $left, array $right): int {
        return [$left['occurrence_start_date'], $left['occurrence_end_date'], $left['event_id']]
            <=> [$right['occurrence_start_date'], $right['occurrence_end_date'], $right['event_id']];
    });
    return $events;
}
