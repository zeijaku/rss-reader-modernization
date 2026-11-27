<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_ics_uid.php';
require_once __DIR__ . '/calendar_time.php';

/** RFC 5545 TEXT escaping, including line-break injection prevention. */
function calendar_transfer_text(string $value): string
{
    return str_replace(["\\", "\r\n", "\r", "\n", ";", ","],
        ["\\\\", "\\n", "\\n", "\\n", "\\;", "\\,"], $value);
}

/** Fold at 75 octets, never inside a UTF-8 codepoint. */
function calendar_transfer_fold(string $line): string
{
    if (preg_match('//u', $line) !== 1 || preg_match('/[\r\n\x00]/', $line)) {
        throw new InvalidArgumentException('Invalid ICS content line.');
    }
    $out = '';
    while (strlen($line) > 75) {
        $part = mb_strcut($line, 0, 75, 'UTF-8');
        $out .= $part . "\r\n";
        $line = ' ' . substr($line, strlen($part));
    }
    return $out . $line . "\r\n";
}

function calendar_transfer_utc(string $date, string $clock): string
{
    $dt = new DateTimeImmutable($date . ' ' . $clock, new DateTimeZone('Asia/Tokyo'));
    return $dt->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
}

/** D1 supports ordinary events only; D2 adds series, reminders and extensions. */
function calendar_transfer_basic_event(array $row): array
{
    $normalized = calendar_normalize_event_row($row);
    $uid = $row['calendar_event_ics_uid'] ?? null;
    if ($normalized === null || !calendar_ics_uid_valid($uid)) {
        throw new UnexpectedValueException('Calendar event or UID is invalid.');
    }
    if (($row['calendar_event_repeat_type'] ?? 'none') !== 'none') {
        throw new DomainException('繰り返し予定の出力はD2で対応します。');
    }
    $start = $normalized['calendar_event_start_date'];
    $end = $normalized['calendar_event_end_date'];
    $time = calendar_event_time_settings($row['calendar_event_all_day'] ?? null,
        $row['calendar_event_start_time'] ?? null, $row['calendar_event_end_time'] ?? null,
        $row['calendar_event_url'] ?? null, $start, $end, null, $row['calendar_event_location'] ?? '');
    if ($time === null) {
        throw new UnexpectedValueException('Calendar event time is invalid.');
    }
    $updated = (string) ($row['calendar_event_updated_at'] ?? '');
    $created = (string) ($row['calendar_event_date'] ?? '');
    foreach ([$updated, $created] as $stamp) {
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $stamp) !== 1) {
            throw new UnexpectedValueException('Calendar event timestamp is invalid.');
        }
    }
    $lines = ['BEGIN:VEVENT', 'UID:' . calendar_transfer_text($uid),
        'DTSTAMP:' . calendar_transfer_utc(substr($updated, 0, 10), substr($updated, 11)),
        'CREATED:' . calendar_transfer_utc(substr($created, 0, 10), substr($created, 11)),
        'LAST-MODIFIED:' . calendar_transfer_utc(substr($updated, 0, 10), substr($updated, 11))];
    if ($time['all_day']) {
        $exclusive = (new DateTimeImmutable($end, new DateTimeZone('Asia/Tokyo')))->modify('+1 day')->format('Ymd');
        $lines[] = 'DTSTART;VALUE=DATE:' . str_replace('-', '', $start);
        $lines[] = 'DTEND;VALUE=DATE:' . $exclusive;
    } else {
        $lines[] = 'DTSTART:' . calendar_transfer_utc($start, $time['start_time']);
        if ($time['end_time'] !== null) {
            $lines[] = 'DTEND:' . calendar_transfer_utc($end, $time['end_time']);
        } elseif ($start !== $end) {
            throw new DomainException('終了時刻未設定の複数日予定はD1では出力できません。');
        }
    }
    $lines[] = 'SUMMARY:' . calendar_transfer_text($normalized['calendar_event_title']);
    $lines[] = 'DESCRIPTION:' . calendar_transfer_text($normalized['calendar_event_note']);
    if ($time['location'] !== null) {
        $lines[] = 'LOCATION:' . calendar_transfer_text($time['location']);
    }
    if ($time['url'] !== null) {
        $lines[] = 'URL:' . $time['url'];
    }
    $lines[] = 'END:VEVENT';
    return $lines;
}

function calendar_transfer_build_basic(array $rows): string
{
    $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//RSS Reader Modernization//Calendar//EN', 'CALSCALE:GREGORIAN'];
    $seen = [];
    foreach ($rows as $row) {
        $event = calendar_transfer_basic_event($row);
        $uid = $row['calendar_event_ics_uid'];
        if (isset($seen[$uid])) {
            throw new DomainException('同じUIDの予定が複数存在します。');
        }
        $seen[$uid] = true;
        array_push($lines, ...$event);
    }
    $lines[] = 'END:VCALENDAR';
    return implode('', array_map('calendar_transfer_fold', $lines));
}

/** Owner and source are checked in the same read query; never read subscriptions. */
function calendar_transfer_export_basic(PDO $pdo, int $ownerId, int $sourceId): string
{
    if ($ownerId <= 0 || $sourceId <= 0) {
        throw new InvalidArgumentException('Calendar source is invalid.');
    }
    if (!calendar_ics_uid_schema_ready($pdo)) {
        throw new LogicException('Migration 037 is required.');
    }
    $sources = db_table_identifier('calendar_source');
    $events = db_table_identifier('calendar_event');
    $stmt = $pdo->prepare("SELECT e.* FROM $sources s LEFT JOIN $events e"
        . ' ON e.calendar_event_owner=s.calendar_source_owner AND e.calendar_event_flag=0'
        . ' AND (e.calendar_event_source_id=s.calendar_source_id OR (s.calendar_source_default=1 AND e.calendar_event_source_id IS NULL))'
        . ' WHERE s.calendar_source_owner=:owner AND s.calendar_source_id=:source AND s.calendar_source_flag=0'
        . ' ORDER BY e.calendar_event_id LIMIT 501');
    $stmt->execute([':owner' => $ownerId, ':source' => $sourceId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows === []) {
        throw new OutOfBoundsException('Calendar source was not found.');
    }
    if (count($rows) > 500) {
        throw new LengthException('Calendar export event limit exceeded.');
    }
    if ($rows[0]['calendar_event_id'] === null) {
        $rows = [];
    }
    return calendar_transfer_build_basic($rows);
}

/** JSON transport, matching the existing OPML convention; UI is added later. */
function api_calendar_transfer_export(int $userId, array $input): array
{
    $source = app_validate_positive_int($input['source_id'] ?? null);
    if ($source === null) {
        return api_error('validation_error', 'Calendarを指定してください。', 422);
    }
    // D1 must not silently accept advanced export options.
    if (array_diff(array_keys($input), ['action', 'csrf_token', 'source_id']) !== []) {
        return api_error('validation_error', 'D1では指定Calendarの基本出力のみ対応します。', 422);
    }
    try {
        $pdo = conn_db();
        if (!calendar_ics_uid_schema_ready($pdo) || !calendar_source_schema_ready($pdo)) {
            return api_error('calendar_transfer_unavailable', 'DB Migration 037が必要です。', 503);
        }
        $ics = calendar_transfer_export_basic($pdo, $userId, $source);
        return api_success(['filename' => 'calendar-' . $source . '.ics', 'ics' => $ics,
            'scope' => 'basic', 'warning' => 'D1: リマインダー・色・独自情報は未出力です。完全な移行にはD2以降を利用してください。']);
    } catch (OutOfBoundsException $e) {
        return api_error('not_found', 'Calendarが見つかりません。', 404);
    } catch (DomainException | LengthException $e) {
        return api_error('calendar_transfer_unsupported', '現在の出力条件では対応できない予定があります。', 422);
    }
}
