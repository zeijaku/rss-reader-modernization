<?php

declare(strict_types=1);

const CALENDAR_EVENT_REMINDER_VALUES = ['none', 'at_time', '10m', '30m', '1h', '1d'];
const CALENDAR_EVENT_REMINDER_ALL_DAY_TIME = '09:00:00';

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

function calendar_event_reminder_source_key(int $eventId): string
{
    if ($eventId <= 0) {
        throw new InvalidArgumentException('Calendar reminder event id is invalid.');
    }
    return 'event:' . $eventId . ':reminder';
}

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

    $base = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i:s',
        $startDate . ' ' . $clock,
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

function calendar_event_reminder_body(array $event): string
{
    $allDay = calendar_event_time_validate_all_day($event['calendar_event_all_day'] ?? '1') ?? true;
    $startDate = (string) ($event['calendar_event_start_date'] ?? '');
    $time = $allDay
        ? '終日（通知基準 09:00）'
        : substr((string) ($event['calendar_event_start_time'] ?? ''), 0, 5);
    $label = calendar_event_reminder_label((string) ($event['calendar_event_reminder'] ?? 'none'));

    return $startDate . ' ' . $time . ' / ' . $label;
}

function calendar_event_reminder_existing(PDO $pdo, int $ownerId, int $eventId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT notification_id, notification_due_at, notification_read_at, notification_hidden_at '
        . 'FROM ' . db_table_identifier('notification') . ' '
        . "WHERE notification_owner = :owner AND notification_source_type = 'calendar' "
        . "AND notification_source_key = :source_key AND notification_type = 'reminder' LIMIT 1"
    );
    $stmt->execute([
        ':owner' => $ownerId,
        ':source_key' => calendar_event_reminder_source_key($eventId),
    ]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function calendar_event_reminder_cancel_pending(PDO $pdo, int $ownerId, int $eventId): void
{
    if ($ownerId <= 0 || $eventId <= 0) {
        return;
    }
    $now = app_now();
    $stmt = $pdo->prepare(
        'DELETE FROM ' . db_table_identifier('notification') . ' '
        . "WHERE notification_owner = :owner AND notification_source_type = 'calendar' "
        . "AND notification_source_key = :source_key AND notification_type = 'reminder' "
        . 'AND notification_due_at > :now'
    );
    $stmt->execute([
        ':owner' => $ownerId,
        ':source_key' => calendar_event_reminder_source_key($eventId),
        ':now' => $now,
    ]);
}

function calendar_event_reminder_apply(PDO $pdo, int $ownerId, int $eventId, string $reminder): void
{
    $reminder = calendar_event_reminder_validate($reminder);
    if ($ownerId <= 0 || $eventId <= 0 || $reminder === null) {
        throw new InvalidArgumentException('Calendar reminder settings are invalid.');
    }
    $stmt = $pdo->prepare(
        'UPDATE ' . db_table_identifier('calendar_event') . ' '
        . 'SET calendar_event_reminder = :reminder '
        . 'WHERE calendar_event_id = :event_id AND calendar_event_owner = :owner AND calendar_event_flag = 0'
    );
    $stmt->execute([
        ':reminder' => $reminder,
        ':event_id' => $eventId,
        ':owner' => $ownerId,
    ]);
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
    if ((int) ($event['calendar_event_flag'] ?? 1) !== 0 || $reminder === 'none' || $repeatType !== 'none') {
        calendar_event_reminder_cancel_pending($pdo, $ownerId, $eventId);
        return;
    }

    $dueAt = calendar_event_reminder_due_at($event);
    if ($dueAt === null) {
        calendar_event_reminder_cancel_pending($pdo, $ownerId, $eventId);
        return;
    }

    $existing = calendar_event_reminder_existing($pdo, $ownerId, $eventId);
    $now = app_now();
    if ($existing !== null && (string) ($existing['notification_due_at'] ?? '') <= $now) {
        // Once a reminder has become due, keep it as history. Event edits only
        // reschedule reminders that have not reached their due time yet.
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
        calendar_event_reminder_target_url(
            $pdo,
            $ownerId,
            $eventId,
            (string) $event['calendar_event_start_date']
        )
    );
}

function calendar_event_reminder_time_color_create(
    int $ownerId,
    string $title,
    string $startDate,
    string $endDate,
    string $note,
    string $color,
    array $timeSettings,
    string $reminder
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
        calendar_event_reminder_apply($pdo, $ownerId, $eventId, $reminder);
        calendar_event_reminder_reconcile($pdo, $ownerId, $eventId);
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

function calendar_event_reminder_time_color_update(
    int $ownerId,
    int $eventId,
    string $title,
    string $startDate,
    string $endDate,
    string $note,
    string $color,
    array $timeSettings,
    string $reminder
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
        calendar_event_reminder_apply($pdo, $ownerId, $eventId, $reminder);
        calendar_event_reminder_reconcile($pdo, $ownerId, $eventId);
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
