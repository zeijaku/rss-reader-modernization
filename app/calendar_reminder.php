<?php

declare(strict_types=1);

const CALENDAR_EVENT_REMINDER_VALUES = ['none', 'at_time', '10m', '30m', '1h', '1d'];
const CALENDAR_EVENT_REMINDER_ALL_DAY_TIME = '09:00:00';
const CALENDAR_EVENT_REMINDER_SYNC_PAST_DAYS = 2;
const CALENDAR_EVENT_REMINDER_SYNC_FUTURE_DAYS = 62;
const CALENDAR_EVENT_REMINDER_SYNC_CHUNK_DAYS = 35;

function calendar_event_reminder_validate(mixed $value): ?string
{
    return is_string($value) && in_array($value, CALENDAR_EVENT_REMINDER_VALUES, true) ? $value : null;
}

function calendar_event_reminder_label(string $reminder): string
{
    return match ($reminder) {
        'at_time' => '予定時刻',
        '10m' => '10分前',
        '30m' => '30分前',
        '1h' => '1時間前',
        '1d' => '前日',
        default => 'なし',
    };
}

function calendar_event_reminder_source_key(int $eventId): string
{
    if ($eventId <= 0) {
        throw new InvalidArgumentException('Calendar reminder event id is invalid.');
    }
    return 'event:' . $eventId . ':reminder';
}

function calendar_event_reminder_occurrence_source_key(int $eventId, string $originalStart): string
{
    if ($eventId <= 0 || calendar_validate_date($originalStart) === null) {
        throw new InvalidArgumentException('Calendar occurrence reminder identity is invalid.');
    }
    return 'event:' . $eventId . ':occurrence:' . $originalStart . ':reminder';
}

/** @param array<string,mixed> $event */
function calendar_event_reminder_due_at(array $event): ?string
{
    $reminder = calendar_event_reminder_validate($event['calendar_event_reminder'] ?? null);
    $startDate = calendar_validate_date($event['calendar_event_start_date'] ?? null);
    $allDay = calendar_event_time_validate_all_day($event['calendar_event_all_day'] ?? '1');
    if ($reminder === null || $reminder === 'none' || $startDate === null || $allDay === null) {
        return null;
    }
    $clock = CALENDAR_EVENT_REMINDER_ALL_DAY_TIME;
    if (!$allDay) {
        $validated = calendar_event_time_validate_clock($event['calendar_event_start_time'] ?? null);
        if ($validated === null || $validated === '') {
            return null;
        }
        $clock = $validated;
    }
    return calendar_event_reminder_due_from_values($startDate, $clock, $reminder);
}

function calendar_event_reminder_due_from_values(string $startDate, string $clock, string $reminder): ?string
{
    if (calendar_validate_date($startDate) === null
        || calendar_event_time_validate_clock($clock) === null
        || calendar_event_reminder_validate($reminder) === null
        || $reminder === 'none') {
        return null;
    }
    $base = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i:s',
        $startDate . ' ' . calendar_event_time_validate_clock($clock),
        new DateTimeZone('Asia/Tokyo')
    );
    if (!$base instanceof DateTimeImmutable) {
        return null;
    }
    $due = match ($reminder) {
        '10m' => $base->modify('-10 minutes'),
        '30m' => $base->modify('-30 minutes'),
        '1h' => $base->modify('-1 hour'),
        '1d' => $base->modify('-1 day'),
        default => $base,
    };
    return $due->format('Y-m-d H:i:s');
}

/** @param array<string,mixed> $occurrence */
function calendar_event_reminder_occurrence_due_at(array $occurrence, string $reminder): ?string
{
    $startDate = calendar_validate_date($occurrence['occurrence_start_date'] ?? null);
    $allDay = ($occurrence['all_day'] ?? true) === true;
    if ($startDate === null || calendar_event_reminder_validate($reminder) === null || $reminder === 'none') {
        return null;
    }
    $clock = CALENDAR_EVENT_REMINDER_ALL_DAY_TIME;
    if (!$allDay) {
        $validated = calendar_event_time_validate_clock($occurrence['start_time'] ?? null);
        if ($validated === null || $validated === '') {
            return null;
        }
        $clock = $validated;
    }
    return calendar_event_reminder_due_from_values($startDate, $clock, $reminder);
}

function calendar_event_reminder_target_url(PDO $pdo, int $ownerId, int $eventId, string $startDate): string
{
    $stmt = $pdo->prepare(
        'SELECT widget_location FROM ' . db_table_identifier('dashboard_widget') . ' '
        . "WHERE widget_owner = :owner AND widget_type = 'calendar' AND widget_flag = 0 "
        . 'ORDER BY widget_location ASC, widget_sort_order ASC, widget_id ASC LIMIT 1'
    );
    $stmt->execute([':owner' => $ownerId]);
    $location = $stmt->fetchColumn();
    $tab = is_numeric($location) ? max(0, min(3, (int) $location)) : 0;
    return './?tab=' . $tab
        . '&calendar_date=' . rawurlencode($startDate)
        . '&calendar_event_id=' . $eventId;
}

/** @param array<string,mixed> $event */
function calendar_event_reminder_body(array $event): string
{
    $allDay = calendar_event_time_validate_all_day($event['calendar_event_all_day'] ?? '1') ?? true;
    $startDate = (string) ($event['calendar_event_start_date'] ?? '');
    $time = $allDay ? '終日（通知基準 09:00）' : substr((string) ($event['calendar_event_start_time'] ?? ''), 0, 5);
    $label = calendar_event_reminder_label((string) ($event['calendar_event_reminder'] ?? 'none'));
    return $startDate . ' ' . $time . ' / ' . $label;
}

/** @param array<string,mixed> $occurrence */
function calendar_event_reminder_occurrence_body(array $occurrence, string $reminder): string
{
    $allDay = ($occurrence['all_day'] ?? true) === true;
    $startDate = (string) ($occurrence['occurrence_start_date'] ?? '');
    $time = $allDay ? '終日（通知基準 09:00）' : substr((string) ($occurrence['start_time'] ?? ''), 0, 5);
    return $startDate . ' ' . $time . ' / ' . calendar_event_reminder_label($reminder);
}

function calendar_event_reminder_existing_by_key(PDO $pdo, int $ownerId, string $sourceKey): ?array
{
    $stmt = $pdo->prepare(
        'SELECT notification_id, notification_due_at, notification_read_at, notification_hidden_at '
        . 'FROM ' . db_table_identifier('notification') . ' '
        . "WHERE notification_owner = :owner AND notification_source_type = 'calendar' "
        . "AND notification_source_key = :source_key AND notification_type = 'reminder' LIMIT 1"
    );
    $stmt->execute([':owner' => $ownerId, ':source_key' => $sourceKey]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function calendar_event_reminder_existing(PDO $pdo, int $ownerId, int $eventId): ?array
{
    return calendar_event_reminder_existing_by_key($pdo, $ownerId, calendar_event_reminder_source_key($eventId));
}

function calendar_event_reminder_cancel_pending(PDO $pdo, int $ownerId, int $eventId): void
{
    if ($ownerId <= 0 || $eventId <= 0) {
        return;
    }
    $stmt = $pdo->prepare(
        'DELETE FROM ' . db_table_identifier('notification') . ' '
        . "WHERE notification_owner = :owner AND notification_source_type = 'calendar' "
        . "AND notification_source_id = :source_id AND notification_type = 'reminder' "
        . 'AND notification_due_at > :now'
    );
    $stmt->execute([':owner' => $ownerId, ':source_id' => (string) $eventId, ':now' => app_now()]);
}

function calendar_event_reminder_apply(PDO $pdo, int $ownerId, int $eventId, string $reminder): void
{
    $reminder = calendar_event_reminder_validate($reminder);
    if ($ownerId <= 0 || $eventId <= 0 || $reminder === null) {
        throw new InvalidArgumentException('Calendar reminder settings are invalid.');
    }
    $stmt = $pdo->prepare(
        'UPDATE ' . db_table_identifier('calendar_event') . ' SET calendar_event_reminder = :reminder '
        . 'WHERE calendar_event_id = :event_id AND calendar_event_owner = :owner AND calendar_event_flag = 0'
    );
    $stmt->execute([':reminder' => $reminder, ':event_id' => $eventId, ':owner' => $ownerId]);
    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare(
            'SELECT calendar_event_id FROM ' . db_table_identifier('calendar_event') . ' '
            . 'WHERE calendar_event_id = :event_id AND calendar_event_owner = :owner AND calendar_event_flag = 0'
        );
        $check->execute([':event_id' => $eventId, ':owner' => $ownerId]);
        if ($check->fetchColumn() === false) {
            throw new OutOfBoundsException('Calendar event was not found.');
        }
    }
}

/** @param array<string,mixed> $occurrence */
function calendar_event_reminder_upsert_occurrence(
    PDO $pdo,
    int $ownerId,
    int $eventId,
    array $occurrence,
    string $reminder
): ?string {
    $originalStart = calendar_validate_date($occurrence['original_occurrence_start_date'] ?? null);
    $effectiveStart = calendar_validate_date($occurrence['occurrence_start_date'] ?? null);
    $reminder = calendar_event_reminder_validate($reminder);
    if ($originalStart === null || $effectiveStart === null || $reminder === null || $reminder === 'none') {
        return null;
    }
    $sourceKey = calendar_event_reminder_occurrence_source_key($eventId, $originalStart);
    $dueAt = calendar_event_reminder_occurrence_due_at($occurrence, $reminder);
    if ($dueAt === null) {
        return null;
    }
    $existing = calendar_event_reminder_existing_by_key($pdo, $ownerId, $sourceKey);
    if ($existing !== null && (string) ($existing['notification_due_at'] ?? '') <= app_now()) {
        return $sourceKey;
    }
    notification_upsert(
        $ownerId,
        'reminder',
        'calendar',
        (string) $eventId,
        $sourceKey,
        '予定: ' . (string) ($occurrence['title'] ?? ''),
        calendar_event_reminder_occurrence_body($occurrence, $reminder),
        $dueAt,
        calendar_event_reminder_target_url($pdo, $ownerId, $eventId, $effectiveStart)
    );
    return $sourceKey;
}

/** @return list<array<string,mixed>> */
function calendar_event_reminder_series_rows(PDO $pdo, int $ownerId, ?int $eventId = null): array
{
    $sql = 'SELECT calendar_event_id, calendar_event_date, calendar_event_updated_at, calendar_event_flag, '
        . 'calendar_event_owner, calendar_event_title, calendar_event_start_date, calendar_event_end_date, '
        . 'calendar_event_note, calendar_event_color, calendar_event_all_day, calendar_event_start_time, '
        . 'calendar_event_end_time, calendar_event_url, calendar_event_repeat_type, calendar_event_repeat_until, '
        . 'calendar_event_reminder FROM ' . db_table_identifier('calendar_event') . ' '
        . 'WHERE calendar_event_owner = :owner AND calendar_event_flag = 0 '
        . "AND calendar_event_repeat_type <> 'none' AND calendar_event_reminder <> 'none'";
    $params = [':owner' => $ownerId];
    if ($eventId !== null) {
        $sql .= ' AND calendar_event_id = :event_id';
        $params[':event_id'] = $eventId;
    }
    $sql .= ' ORDER BY calendar_event_id ASC LIMIT 500';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_values(array_filter($stmt->fetchAll(), 'is_array'));
}

/** @return array{start:string,end:string} */
function calendar_event_reminder_sync_window(): array
{
    $today = DateTimeImmutable::createFromFormat('!Y-m-d', substr(app_now(), 0, 10), new DateTimeZone('Asia/Tokyo'));
    if (!$today instanceof DateTimeImmutable) {
        throw new RuntimeException('Calendar reminder sync date is invalid.');
    }
    return [
        'start' => $today->modify('-' . CALENDAR_EVENT_REMINDER_SYNC_PAST_DAYS . ' days')->format('Y-m-d'),
        'end' => $today->modify('+' . CALENDAR_EVENT_REMINDER_SYNC_FUTURE_DAYS . ' days')->format('Y-m-d'),
    ];
}

/** @return list<array{start:string,end:string}> */
function calendar_event_reminder_sync_chunks(string $start, string $end): array
{
    $startDate = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
    $endDate = DateTimeImmutable::createFromFormat('!Y-m-d', $end);
    if (!$startDate instanceof DateTimeImmutable || !$endDate instanceof DateTimeImmutable || $endDate < $startDate) {
        throw new InvalidArgumentException('Calendar reminder sync range is invalid.');
    }
    $chunks = [];
    $cursor = $startDate;
    while ($cursor <= $endDate) {
        $chunkEnd = $cursor->modify('+' . (CALENDAR_EVENT_REMINDER_SYNC_CHUNK_DAYS - 1) . ' days');
        if ($chunkEnd > $endDate) {
            $chunkEnd = $endDate;
        }
        $chunks[] = ['start' => $cursor->format('Y-m-d'), 'end' => $chunkEnd->format('Y-m-d')];
        $cursor = $chunkEnd->modify('+1 day');
    }
    return $chunks;
}

/**
 * Synchronize a bounded rolling window of recurring occurrences.
 * Existing due/history rows are never rewritten or deleted.
 */
function calendar_event_reminder_sync_owner(int $ownerId, ?int $eventId = null): int
{
    if ($ownerId <= 0 || ($eventId !== null && $eventId <= 0)) {
        throw new InvalidArgumentException('Calendar reminder sync target is invalid.');
    }
    if (!function_exists('calendar_event_recurrence_expand_row')
        || !function_exists('calendar_event_exception_apply_range')) {
        return 0;
    }
    $pdo = conn_db();
    $rows = calendar_event_reminder_series_rows($pdo, $ownerId, $eventId);
    if ($rows === []) {
        if ($eventId !== null) {
            calendar_event_reminder_cancel_pending($pdo, $ownerId, $eventId);
        }
        return 0;
    }

    $window = calendar_event_reminder_sync_window();
    $expected = [];
    $synced = 0;
    foreach (calendar_event_reminder_sync_chunks($window['start'], $window['end']) as $chunk) {
        $base = [];
        foreach ($rows as $row) {
            foreach (calendar_event_recurrence_expand_row($row, $chunk['start'], $chunk['end']) as $occurrence) {
                $base[] = $occurrence;
            }
        }
        $state = calendar_event_exception_apply_range($ownerId, $chunk['start'], $chunk['end'], $base);
        foreach ($state['events'] as $occurrence) {
            $id = (int) ($occurrence['event_id'] ?? 0);
            if ($id <= 0 || ($eventId !== null && $id !== $eventId)) {
                continue;
            }
            $reminder = calendar_event_reminder_validate(
                $occurrence['source_reminder'] ?? $occurrence['reminder'] ?? 'none'
            ) ?? 'none';
            $sourceKey = calendar_event_reminder_upsert_occurrence($pdo, $ownerId, $id, $occurrence, $reminder);
            if ($sourceKey !== null) {
                $expected[$sourceKey] = true;
                $synced++;
            }
        }
    }

    $params = [
        ':owner' => $ownerId,
        ':now' => app_now(),
        ':window_end' => $window['end'] . ' 23:59:59',
    ];
    $sql = 'SELECT notification_id, notification_source_key FROM ' . db_table_identifier('notification') . ' '
        . "WHERE notification_owner = :owner AND notification_source_type = 'calendar' "
        . "AND notification_type = 'reminder' AND notification_source_key LIKE 'event:%:occurrence:%:reminder' "
        . 'AND notification_due_at > :now AND notification_due_at <= :window_end';
    if ($eventId !== null) {
        $sql .= ' AND notification_source_id = :source_id';
        $params[':source_id'] = (string) $eventId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $staleIds = [];
    foreach ($stmt->fetchAll() as $notificationRow) {
        if (!is_array($notificationRow)) {
            continue;
        }
        $key = (string) ($notificationRow['notification_source_key'] ?? '');
        if (!isset($expected[$key])) {
            $staleIds[] = (int) ($notificationRow['notification_id'] ?? 0);
        }
    }
    if ($staleIds !== []) {
        $delete = $pdo->prepare(
            'DELETE FROM ' . db_table_identifier('notification') . ' '
            . 'WHERE notification_id = :id AND notification_owner = :owner AND notification_due_at > :now'
        );
        foreach ($staleIds as $id) {
            if ($id > 0) {
                $delete->execute([':id' => $id, ':owner' => $ownerId, ':now' => app_now()]);
            }
        }
    }
    return $synced;
}

function calendar_event_reminder_reconcile(PDO $pdo, int $ownerId, int $eventId): void
{
    if ($ownerId <= 0 || $eventId <= 0) {
        throw new InvalidArgumentException('Calendar reminder target is invalid.');
    }
    $stmt = $pdo->prepare(
        'SELECT calendar_event_id, calendar_event_owner, calendar_event_flag, calendar_event_title, '
        . 'calendar_event_start_date, calendar_event_all_day, calendar_event_start_time, '
        . 'calendar_event_repeat_type, calendar_event_reminder '
        . 'FROM ' . db_table_identifier('calendar_event') . ' '
        . 'WHERE calendar_event_id = :event_id AND calendar_event_owner = :owner LIMIT 1'
    );
    $stmt->execute([':event_id' => $eventId, ':owner' => $ownerId]);
    $event = $stmt->fetch();
    if (!is_array($event)) {
        calendar_event_reminder_cancel_pending($pdo, $ownerId, $eventId);
        return;
    }

    $reminder = calendar_event_reminder_validate($event['calendar_event_reminder'] ?? null) ?? 'none';
    $repeatType = calendar_event_recurrence_validate_type($event['calendar_event_repeat_type'] ?? null) ?? 'none';
    if ((int) ($event['calendar_event_flag'] ?? 1) !== 0 || $reminder === 'none') {
        calendar_event_reminder_cancel_pending($pdo, $ownerId, $eventId);
        return;
    }
    if ($repeatType !== 'none') {
        calendar_event_reminder_cancel_pending($pdo, $ownerId, $eventId);
        calendar_event_reminder_sync_owner($ownerId, $eventId);
        return;
    }

    $dueAt = calendar_event_reminder_due_at($event);
    if ($dueAt === null) {
        calendar_event_reminder_cancel_pending($pdo, $ownerId, $eventId);
        return;
    }
    $existing = calendar_event_reminder_existing($pdo, $ownerId, $eventId);
    if ($existing !== null && (string) ($existing['notification_due_at'] ?? '') <= app_now()) {
        return;
    }
    notification_upsert(
        $ownerId,
        'reminder',
        'calendar',
        (string) $eventId,
        calendar_event_reminder_source_key($eventId),
        '予定: ' . (string) $event['calendar_event_title'],
        calendar_event_reminder_body($event),
        $dueAt,
        calendar_event_reminder_target_url($pdo, $ownerId, $eventId, (string) $event['calendar_event_start_date'])
    );
}
