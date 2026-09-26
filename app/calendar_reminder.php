<?php

declare(strict_types=1);

const CALENDAR_EVENT_REMINDER_VALUES = ['none', 'at_time', '10m', '30m', '1h', '1d'];
const CALENDAR_EVENT_REMINDER_ALL_DAY_TIME = '09:00:00';
const CALENDAR_EVENT_REMINDER_SYNC_PAST_DAYS = 1;
const CALENDAR_EVENT_REMINDER_SYNC_FUTURE_DAYS = 11;

function calendar_event_reminder_validate(mixed $value): ?string
{
    return is_string($value) && in_array($value, CALENDAR_EVENT_REMINDER_VALUES, true)
        ? $value
        : null;
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

function calendar_event_reminder_source_key(int $eventId, ?string $originalStart = null): string
{
    if ($eventId <= 0) {
        throw new InvalidArgumentException('Calendar reminder event id is invalid.');
    }
    if ($originalStart === null) {
        return 'event:' . $eventId . ':reminder';
    }
    $originalStart = calendar_validate_date($originalStart);
    if ($originalStart === null) {
        throw new InvalidArgumentException('Calendar reminder occurrence date is invalid.');
    }
    return 'event:' . $eventId . ':occurrence:' . $originalStart . ':reminder';
}

/** @return array{reminder:string,start_date:string,all_day:bool,start_time:?string}|null */
function calendar_event_reminder_timing(array $event): ?array
{
    $reminder = calendar_event_reminder_validate(
        $event['calendar_event_reminder'] ?? $event['reminder'] ?? $event['source_reminder'] ?? null
    );
    $startDate = calendar_validate_date(
        $event['calendar_event_start_date'] ?? $event['occurrence_start_date'] ?? null
    );
    $allDay = calendar_event_time_validate_all_day(
        $event['calendar_event_all_day'] ?? $event['all_day'] ?? '1'
    );
    if ($reminder === null || $reminder === 'none' || $startDate === null || $allDay === null) {
        return null;
    }

    $startTime = null;
    if (!$allDay) {
        $validated = calendar_event_time_validate_clock(
            $event['calendar_event_start_time'] ?? $event['start_time'] ?? null
        );
        if ($validated === null || $validated === '') {
            return null;
        }
        $startTime = $validated;
    }

    return [
        'reminder' => $reminder,
        'start_date' => $startDate,
        'all_day' => $allDay,
        'start_time' => $startTime,
    ];
}

function calendar_event_reminder_due_at(array $event): ?string
{
    $timing = calendar_event_reminder_timing($event);
    if ($timing === null) {
        return null;
    }

    $clock = $timing['all_day'] ? CALENDAR_EVENT_REMINDER_ALL_DAY_TIME : (string) $timing['start_time'];
    $base = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i:s',
        $timing['start_date'] . ' ' . $clock,
        new DateTimeZone('Asia/Tokyo')
    );
    if (!$base instanceof DateTimeImmutable) {
        return null;
    }

    $due = match ($timing['reminder']) {
        '10m' => $base->modify('-10 minutes'),
        '30m' => $base->modify('-30 minutes'),
        '1h' => $base->modify('-1 hour'),
        '1d' => $base->modify('-1 day'),
        default => $base,
    };
    return $due->format('Y-m-d H:i:s');
}

function calendar_event_reminder_target_url(
    PDO $pdo,
    int $ownerId,
    int $eventId,
    string $startDate,
    ?string $originalStart = null
): string {
    static $tabCache = [];
    if (!array_key_exists($ownerId, $tabCache)) {
        $stmt = $pdo->prepare(
            'SELECT widget_location FROM ' . db_table_identifier('dashboard_widget') . ' '
            . "WHERE widget_owner = :owner AND widget_type = 'calendar' AND widget_flag = 0 "
            . 'ORDER BY widget_location ASC, widget_sort_order ASC, widget_id ASC LIMIT 1'
        );
        $stmt->execute([':owner' => $ownerId]);
        $location = $stmt->fetchColumn();
        $tabCache[$ownerId] = is_numeric($location) ? max(0, min(3, (int) $location)) : 0;
    }
    $tab = $tabCache[$ownerId];

    $url = './?tab=' . $tab
        . '&calendar_date=' . rawurlencode($startDate)
        . '&calendar_event_id=' . $eventId;
    if ($originalStart !== null) {
        $originalStart = calendar_validate_date($originalStart);
        if ($originalStart === null) {
            throw new InvalidArgumentException('Calendar reminder occurrence target is invalid.');
        }
        $url .= '&calendar_occurrence_start=' . rawurlencode($originalStart);
    }
    return $url;
}

function calendar_event_reminder_body(array $event): string
{
    $timing = calendar_event_reminder_timing($event);
    if ($timing === null) {
        return '';
    }
    $time = $timing['all_day']
        ? '終日（通知基準 09:00）'
        : substr((string) $timing['start_time'], 0, 5);
    return $timing['start_date'] . ' ' . $time . ' / ' . calendar_event_reminder_label($timing['reminder']);
}

function calendar_event_reminder_existing(PDO $pdo, int $ownerId, string $sourceKey): ?array
{
    $stmt = $pdo->prepare(
        'SELECT notification_id, notification_source_id, notification_title, notification_body, '
        . 'notification_target_url, notification_due_at, notification_read_at, notification_hidden_at '
        . 'FROM ' . db_table_identifier('notification') . ' '
        . "WHERE notification_owner = :owner AND notification_source_type = 'calendar' "
        . "AND notification_source_key = :source_key AND notification_type = 'reminder' LIMIT 1"
    );
    $stmt->execute([':owner' => $ownerId, ':source_key' => $sourceKey]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function calendar_event_reminder_matches_existing(
    ?array $existing,
    string $sourceId,
    string $title,
    string $body,
    string $dueAt,
    string $targetUrl
): bool {
    return $existing !== null
        && (string) ($existing['notification_source_id'] ?? '') === $sourceId
        && (string) ($existing['notification_title'] ?? '') === $title
        && (string) ($existing['notification_body'] ?? '') === $body
        && (string) ($existing['notification_due_at'] ?? '') === $dueAt
        && (string) ($existing['notification_target_url'] ?? '') === $targetUrl;
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
    $stmt->execute([
        ':owner' => $ownerId,
        ':source_id' => (string) $eventId,
        ':now' => app_now(),
    ]);
}

function calendar_event_reminder_cancel_pending_occurrences(PDO $pdo, int $ownerId, int $eventId): void
{
    if ($ownerId <= 0 || $eventId <= 0) {
        return;
    }
    $stmt = $pdo->prepare(
        'DELETE FROM ' . db_table_identifier('notification') . ' '
        . "WHERE notification_owner = :owner AND notification_source_type = 'calendar' "
        . "AND notification_source_id = :source_id AND notification_type = 'reminder' "
        . "AND notification_source_key LIKE :source_pattern AND notification_due_at > :now"
    );
    $stmt->execute([
        ':owner' => $ownerId,
        ':source_id' => (string) $eventId,
        ':source_pattern' => 'event:' . $eventId . ':occurrence:%:reminder',
        ':now' => app_now(),
    ]);
}

function calendar_event_reminder_cancel_pending_occurrence(
    PDO $pdo,
    int $ownerId,
    int $eventId,
    string $originalStart
): void {
    $sourceKey = calendar_event_reminder_source_key($eventId, $originalStart);
    $stmt = $pdo->prepare(
        'DELETE FROM ' . db_table_identifier('notification') . ' '
        . "WHERE notification_owner = :owner AND notification_source_type = 'calendar' "
        . "AND notification_source_key = :source_key AND notification_type = 'reminder' "
        . 'AND notification_due_at > :now'
    );
    $stmt->execute([':owner' => $ownerId, ':source_key' => $sourceKey, ':now' => app_now()]);
}

function calendar_event_reminder_apply(PDO $pdo, int $ownerId, int $eventId, string $reminder): void
{
    $reminder = calendar_event_reminder_validate($reminder);
    if ($ownerId <= 0 || $eventId <= 0 || $reminder === null) {
        throw new InvalidArgumentException('Calendar reminder settings are invalid.');
    }
    $check = $pdo->prepare(
        'SELECT calendar_event_id FROM ' . db_table_identifier('calendar_event') . ' '
        . 'WHERE calendar_event_id = :event_id AND calendar_event_owner = :owner AND calendar_event_flag = 0'
    );
    $check->execute([':event_id' => $eventId, ':owner' => $ownerId]);
    if ($check->fetchColumn() === false) {
        throw new OutOfBoundsException('Calendar event was not found.');
    }

    $stmt = $pdo->prepare(
        'UPDATE ' . db_table_identifier('calendar_event') . ' '
        . 'SET calendar_event_reminder = :reminder '
        . 'WHERE calendar_event_id = :event_id AND calendar_event_owner = :owner AND calendar_event_flag = 0'
    );
    $stmt->execute([':reminder' => $reminder, ':event_id' => $eventId, ':owner' => $ownerId]);
}

/** @param array<string,mixed> $occurrence */
function calendar_event_reminder_reconcile_occurrence(
    PDO $pdo,
    int $ownerId,
    array $occurrence
): void {
    $eventId = app_validate_positive_int($occurrence['event_id'] ?? null);
    $originalStart = calendar_validate_date($occurrence['original_occurrence_start_date'] ?? null);
    if ($ownerId <= 0 || $eventId === null || $originalStart === null) {
        throw new InvalidArgumentException('Calendar occurrence reminder target is invalid.');
    }

    $sourceKey = calendar_event_reminder_source_key($eventId, $originalStart);
    $reminder = calendar_event_reminder_validate(
        $occurrence['reminder'] ?? $occurrence['source_reminder'] ?? null
    ) ?? 'none';
    if (($occurrence['is_cancelled'] ?? false) === true || $reminder === 'none') {
        calendar_event_reminder_cancel_pending_occurrence($pdo, $ownerId, $eventId, $originalStart);
        return;
    }

    $dueAt = calendar_event_reminder_due_at($occurrence);
    if ($dueAt === null) {
        calendar_event_reminder_cancel_pending_occurrence($pdo, $ownerId, $eventId, $originalStart);
        return;
    }

    $existing = calendar_event_reminder_existing($pdo, $ownerId, $sourceKey);
    if ($existing !== null && (string) ($existing['notification_due_at'] ?? '') <= app_now()) {
        return;
    }

    $effectiveStart = calendar_validate_date($occurrence['occurrence_start_date'] ?? null);
    if ($effectiveStart === null) {
        throw new UnexpectedValueException('Calendar occurrence reminder date is invalid.');
    }
    $sourceId = (string) $eventId;
    $title = '予定: ' . (string) ($occurrence['title'] ?? '');
    $body = calendar_event_reminder_body($occurrence);
    $targetUrl = calendar_event_reminder_target_url($pdo, $ownerId, $eventId, $effectiveStart, $originalStart);
    if (calendar_event_reminder_matches_existing($existing, $sourceId, $title, $body, $dueAt, $targetUrl)) {
        return;
    }
    notification_upsert(
        $ownerId,
        'reminder',
        'calendar',
        $sourceId,
        $sourceKey,
        $title,
        $body,
        $dueAt,
        $targetUrl
    );
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
    $repeatType = is_string($event['calendar_event_repeat_type'] ?? null)
        ? (string) $event['calendar_event_repeat_type']
        : 'none';
    if ((int) ($event['calendar_event_flag'] ?? 1) !== 0 || $reminder === 'none') {
        calendar_event_reminder_cancel_pending($pdo, $ownerId, $eventId);
        return;
    }
    if ($repeatType !== 'none') {
        calendar_event_reminder_cancel_pending($pdo, $ownerId, $eventId);
        return;
    }

    calendar_event_reminder_cancel_pending_occurrences($pdo, $ownerId, $eventId);
    $dueAt = calendar_event_reminder_due_at($event);
    if ($dueAt === null) {
        calendar_event_reminder_cancel_pending($pdo, $ownerId, $eventId);
        return;
    }

    $sourceKey = calendar_event_reminder_source_key($eventId);
    $existing = calendar_event_reminder_existing($pdo, $ownerId, $sourceKey);
    if ($existing !== null && (string) ($existing['notification_due_at'] ?? '') <= app_now()) {
        return;
    }

    $sourceId = (string) $eventId;
    $title = '予定: ' . (string) $event['calendar_event_title'];
    $body = calendar_event_reminder_body($event);
    $targetUrl = calendar_event_reminder_target_url(
        $pdo,
        $ownerId,
        $eventId,
        (string) $event['calendar_event_start_date']
    );
    if (calendar_event_reminder_matches_existing($existing, $sourceId, $title, $body, $dueAt, $targetUrl)) {
        return;
    }
    notification_upsert(
        $ownerId,
        'reminder',
        'calendar',
        $sourceId,
        $sourceKey,
        $title,
        $body,
        $dueAt,
        $targetUrl
    );
}

/** @param array<string,bool> $desiredKeys */
function calendar_event_reminder_prune_occurrence_window(
    PDO $pdo,
    int $ownerId,
    string $rangeStart,
    string $rangeEnd,
    array $desiredKeys
): void {
    $stmt = $pdo->prepare(
        'SELECT notification_id, notification_source_key FROM ' . db_table_identifier('notification') . ' '
        . "WHERE notification_owner = :owner AND notification_source_type = 'calendar' "
        . "AND notification_type = 'reminder' AND notification_due_at > :now "
        . "AND notification_source_key LIKE 'event:%:occurrence:%:reminder'"
    );
    $stmt->execute([':owner' => $ownerId, ':now' => app_now()]);
    $delete = $pdo->prepare(
        'DELETE FROM ' . db_table_identifier('notification') . ' '
        . 'WHERE notification_id = :id AND notification_owner = :owner AND notification_due_at > :now'
    );
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $key = (string) ($row['notification_source_key'] ?? '');
        if (preg_match('/\\Aevent:[1-9][0-9]*:occurrence:([0-9]{4}-[0-9]{2}-[0-9]{2}):reminder\\z/D', $key, $match) !== 1) {
            continue;
        }
        $originalStart = calendar_validate_date($match[1]);
        if ($originalStart === null || $originalStart < $rangeStart || $originalStart > $rangeEnd || isset($desiredKeys[$key])) {
            continue;
        }
        $delete->execute([':id' => (int) $row['notification_id'], ':owner' => $ownerId, ':now' => app_now()]);
    }
}

/**
 * Materialize the rolling recurring-occurrence reminder window used by the
 * Dashboard Notification Center. The maximum range is exactly 42 days so it
 * stays inside the existing Calendar range contract.
 */
function calendar_event_reminder_sync_owner(int $ownerId): void
{
    if ($ownerId <= 0) {
        throw new InvalidArgumentException('Calendar reminder owner is invalid.');
    }
    if (!function_exists('calendar_range_event_state')) {
        throw new LogicException('Calendar range support is not loaded.');
    }

    $todayValue = calendar_validate_date(substr(app_now(), 0, 10));
    if ($todayValue === null) {
        throw new UnexpectedValueException('Calendar reminder current date is invalid.');
    }
    $today = new DateTimeImmutable($todayValue, new DateTimeZone('Asia/Tokyo'));
    $rangeStart = $today->modify('-' . CALENDAR_EVENT_REMINDER_SYNC_PAST_DAYS . ' days')->format('Y-m-d');
    $rangeEnd = $today->modify('+' . CALENDAR_EVENT_REMINDER_SYNC_FUTURE_DAYS . ' days')->format('Y-m-d');
    $state = calendar_range_event_state($ownerId, $rangeStart, $rangeEnd);
    $pdo = conn_db();
    $desired = [];

    foreach ($state['events'] as $occurrence) {
        if (!is_array($occurrence) || ($occurrence['repeat_type'] ?? 'none') === 'none') {
            continue;
        }
        $eventId = app_validate_positive_int($occurrence['event_id'] ?? null);
        $originalStart = calendar_validate_date($occurrence['original_occurrence_start_date'] ?? null);
        if ($eventId === null || $originalStart === null) {
            continue;
        }
        $key = calendar_event_reminder_source_key($eventId, $originalStart);
        $reminder = calendar_event_reminder_validate(
            $occurrence['reminder'] ?? $occurrence['source_reminder'] ?? null
        ) ?? 'none';
        if ($reminder !== 'none') {
            $desired[$key] = true;
        }
        calendar_event_reminder_reconcile_occurrence($pdo, $ownerId, $occurrence);
    }

    foreach ($state['cancelled_occurrences'] as $occurrence) {
        if (!is_array($occurrence)) {
            continue;
        }
        $eventId = app_validate_positive_int($occurrence['event_id'] ?? null);
        $originalStart = calendar_validate_date($occurrence['original_occurrence_start_date'] ?? null);
        if ($eventId !== null && $originalStart !== null) {
            calendar_event_reminder_cancel_pending_occurrence($pdo, $ownerId, $eventId, $originalStart);
        }
    }

    calendar_event_reminder_prune_occurrence_window($pdo, $ownerId, $rangeStart, $rangeEnd, $desired);
}
