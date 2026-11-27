<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_ics_uid.php';
require_once __DIR__ . '/calendar_time.php';
require_once __DIR__ . '/calendar_exception.php';
require_once __DIR__ . '/calendar_reminder.php';
require_once __DIR__ . '/calendar_ics_zip.php';

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

/** Shared VEVENT serializer. D1 wrapper still rejects advanced events. */
function calendar_transfer_event_lines(array $row, bool $advanced = false, bool $localTime = false, bool $extra = false): array
{
    $normalized = calendar_normalize_event_row($row);
    $uid = $row['calendar_event_ics_uid'] ?? null;
    if ($normalized === null || !calendar_ics_uid_valid($uid)) {
        throw new UnexpectedValueException('Calendar event or UID is invalid.');
    }
    if (!$advanced && ($row['calendar_event_repeat_type'] ?? 'none') !== 'none') {
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
        $lines[] = calendar_transfer_clock_property('DTSTART', $start, $time['start_time'], $localTime);
        if ($time['end_time'] !== null) {
            // RFC 5545 DTEND must be strictly later; a zero-duration event omits DTEND.
            if (!$advanced || $start !== $end || $time['start_time'] !== $time['end_time']) {
                $lines[] = calendar_transfer_clock_property('DTEND', $end, $time['end_time'], $localTime);
            }
        } elseif ($start !== $end) {
            if (!$advanced) {
                throw new DomainException('終了時刻未設定の複数日予定はD1では出力できません。');
            }
            // Preserve the last occupied civil date without inventing a stored end clock.
            $lines[] = calendar_transfer_clock_property('DTEND', $end, '23:59:59', $localTime);
        }
        if ($advanced && $extra) {
            $lines[] = 'X-RSSREADER-END-TIME:' . ($time['end_time'] ?? 'NONE');
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
    if ($advanced) {
        $color = calendar_event_color_validate($row['calendar_event_color'] ?? 'blue');
        $reminder = calendar_event_reminder_validate($row['calendar_event_reminder'] ?? 'none');
        $highlight = calendar_event_time_validate_all_day($row['calendar_event_deadline_highlight'] ?? 0);
        if ($color === null || $reminder === null || $highlight === null) {
            throw new UnexpectedValueException('Calendar event metadata is invalid.');
        }
        $lines[] = 'COLOR:' . $color;
        if ($extra) {
            $lines[] = 'X-RSSREADER-COLOR:' . $color;
            $lines[] = 'X-RSSREADER-DEADLINE:' . ($highlight ? '1' : '0');
            $lines[] = 'X-RSSREADER-REMINDER:' . $reminder;
        }
        array_push($lines, ...calendar_transfer_alarm($reminder, $time['all_day'], $normalized['calendar_event_title']));
    }
    $lines[] = 'END:VEVENT';
    return $lines;
}

function calendar_transfer_basic_event(array $row): array
{
    return calendar_transfer_event_lines($row);
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

/** D2 transfer limits are independent of subscription display limits. */
const CALENDAR_TRANSFER_MAX_BYTES = 8388608;
const CALENDAR_TRANSFER_MAX_EVENTS = 2000;
const CALENDAR_TRANSFER_MAX_ZIP_BYTES = 16777216;

function calendar_transfer_clock_property(string $name, string $date, string $clock, bool $localTime): string
{
    return $localTime
        ? $name . ';TZID=Asia/Tokyo:' . str_replace('-', '', $date) . 'T' . str_replace(':', '', $clock)
        : $name . ':' . calendar_transfer_utc($date, $clock);
}

function calendar_transfer_alarm(string $reminder, bool $allDay, string $title): array
{
    if ($reminder === 'none') {
        return [];
    }
    // All-day reminders in RSS Reader are relative to 09:00 JST, not midnight.
    $trigger = $allDay
        ? match ($reminder) {
            'at_time' => 'PT9H', '10m' => 'PT8H50M', '30m' => 'PT8H30M', '1h' => 'PT8H', '1d' => '-PT15H',
        }
        : match ($reminder) {
            'at_time' => 'PT0S', '10m' => '-PT10M', '30m' => '-PT30M', '1h' => '-PT1H', '1d' => '-P1D',
        };
    return ['BEGIN:VALARM', 'ACTION:DISPLAY', 'TRIGGER:' . $trigger,
        'DESCRIPTION:' . calendar_transfer_text($title), 'END:VALARM'];
}

function calendar_transfer_recurrence_settings(array $row): array
{
    $settings = calendar_event_recurrence_settings($row['calendar_event_repeat_type'] ?? null,
        $row['calendar_event_repeat_until'] ?? null, $row['calendar_event_start_date'], $row['calendar_event_end_date']);
    if ($settings === null) {
        throw new UnexpectedValueException('Calendar recurrence is invalid.');
    }
    return $settings;
}

/** EXDATE and RECURRENCE-ID must match the original DTSTART, not the override. */
function calendar_transfer_original_property(string $name, array $parent, string $date): string
{
    $allDay = calendar_event_time_validate_all_day($parent['calendar_event_all_day'] ?? null);
    $clock = calendar_event_time_validate_clock($parent['calendar_event_start_time'] ?? null);
    if ($allDay === null || (!$allDay && ($clock === null || $clock === ''))) {
        throw new UnexpectedValueException('Calendar recurrence time is invalid.');
    }
    return $allDay ? $name . ';VALUE=DATE:' . str_replace('-', '', $date)
        : calendar_transfer_clock_property($name, $date, $clock, true);
}

function calendar_transfer_rule(array $row, array $settings): string
{
    $rule = 'RRULE:FREQ=' . strtoupper($settings['repeat_type']);
    if ($settings['repeat_until'] !== null) {
        $allDay = calendar_event_time_validate_all_day($row['calendar_event_all_day'] ?? null);
        $clock = calendar_event_time_validate_clock($row['calendar_event_start_time'] ?? null);
        if ($allDay === null || (!$allDay && ($clock === null || $clock === ''))) {
            throw new UnexpectedValueException('Calendar recurrence end is invalid.');
        }
        $rule .= ';UNTIL=' . ($allDay ? str_replace('-', '', $settings['repeat_until'])
            : calendar_transfer_utc($settings['repeat_until'], $clock));
    }
    return $rule;
}

/** Validate active overrides with the existing model, preserve raw seconds. */
function calendar_transfer_override_row(array $parent, array $exception, array $sourceOccurrence): array
{
    $item = calendar_event_exception_override_item($sourceOccurrence, $exception);
    $row = $parent;
    foreach (['title','note','color','all_day','start_time','end_time','url','location'] as $key) {
        $row['calendar_event_' . $key] = $exception['calendar_event_exception_' . $key] ?? null;
    }
    $row['calendar_event_start_date'] = $item['occurrence_start_date'];
    $row['calendar_event_end_date'] = $item['occurrence_end_date'];
    $row['calendar_event_deadline_highlight'] = $item['deadline_highlight'] ? 1 : 0;
    $row['calendar_event_updated_at'] = $exception['calendar_event_exception_updated_at'];
    $row['calendar_event_date'] = $exception['calendar_event_exception_created_at'];
    return $row;
}

/** Stable distinct occurrence UID, independent of selected export window or moved date. */
function calendar_transfer_occurrence_uid(string $uid, string $original): string
{
    return 'rr-occ-' . hash('sha256', $uid . "\0" . $original);
}

function calendar_transfer_overlap(array $row, array $period): bool
{
    return $row['calendar_event_start_date'] <= $period[1] && $row['calendar_event_end_date'] >= $period[0];
}

/** Insert event-level properties before nested VALARM, not inside it. */
function calendar_transfer_event_properties(array $lines, array $properties): array
{
    $at = array_search('BEGIN:VALARM', $lines, true);
    array_splice($lines, $at === false ? count($lines) - 1 : $at, 0, $properties);
    return $lines;
}

function calendar_transfer_period(mixed $start, mixed $end): ?array
{
    $range = calendar_validate_event_range($start, $end);
    if ($range === null) {
        throw new InvalidArgumentException('Calendar export period must be 1–366 days.');
    }
    return $range;
}

/** Build one calendar, with a hard bound on components and encoded bytes. */
function calendar_transfer_build(array $source, array $rows, array $exceptions, array $marks, ?array $period, bool $extra): string
{
    $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//RSS Reader Modernization//Calendar//EN', 'CALSCALE:GREGORIAN',
        'NAME:' . calendar_transfer_text($source['name']), 'COLOR:' . $source['color']];
    if ($extra) {
        $lines[] = 'X-WR-CALNAME:' . calendar_transfer_text($source['name']);
        $lines[] = 'X-RSSREADER-CALENDAR-NAME:' . calendar_transfer_text($source['name']);
        $lines[] = 'X-RSSREADER-CALENDAR-COLOR:' . $source['color'];
        $lines[] = 'X-RSSREADER-TRANSFER-VERSION:1';
        foreach ($marks as $date => $mark) {
            if (calendar_validate_date($date) === null || app_validate_text($mark['label'] ?? null, 12, false) === null
                || app_validate_text($mark['note'] ?? null, 120, true) === null) {
                throw new UnexpectedValueException('Calendar day mark is invalid.');
            }
            $lines[] = 'X-RSSREADER-DAY-MARK:' . calendar_transfer_text(json_encode(
                ['date' => $date, 'label' => $mark['label'], 'note' => $mark['note']],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        }
    }
    // Local DTSTART for timed series avoids UTC weekday/month-day changes.
    if ($period === null) {
        $hasTimedSeries = false;
        foreach ($rows as $row) {
            if (($row['calendar_event_repeat_type'] ?? 'none') !== 'none'
                && calendar_event_time_validate_all_day($row['calendar_event_all_day'] ?? null) === false) {
                $hasTimedSeries = true;
            }
        }
        // An all-day series may have a timed override: UTC is used for that override.
        if ($hasTimedSeries) {
            array_push($lines, 'BEGIN:VTIMEZONE', 'TZID:Asia/Tokyo', 'BEGIN:STANDARD',
                'DTSTART:19700101T000000', 'TZOFFSETFROM:+0900', 'TZOFFSETTO:+0900', 'TZNAME:JST',
                'END:STANDARD', 'END:VTIMEZONE');
        }
    }
    $ics = '';
    $append = static function (array $part) use (&$ics): void {
        foreach ($part as $line) {
            $ics .= calendar_transfer_fold($line);
            if (strlen($ics) > CALENDAR_TRANSFER_MAX_BYTES) {
                throw new LengthException('Calendar ICS exceeds 8 MiB.');
            }
        }
    };
    $append($lines);
    $count = 0;
    $seen = [];
    $emit = static function (array $event) use (&$count, $append): void {
        if (++$count > CALENDAR_TRANSFER_MAX_EVENTS) {
            throw new LengthException('Calendar export has more than 2000 events.');
        }
        $append($event);
    };
    foreach ($rows as $parent) {
        $normalized = calendar_normalize_event_row($parent);
        $uid = $parent['calendar_event_ics_uid'] ?? null;
        if ($normalized === null || !calendar_ics_uid_valid($uid)) {
            throw new UnexpectedValueException('Calendar event or UID is invalid.');
        }
        if (isset($seen[$uid])) {
            throw new DomainException('同じUIDの予定が複数存在します。');
        }
        $seen[$uid] = true;
        $settings = calendar_transfer_recurrence_settings($parent);
        $eventExceptions = $exceptions[(int) $parent['calendar_event_id']] ?? [];
        if ($settings['repeat_type'] === 'none') {
            if ($eventExceptions !== []) {
                throw new UnexpectedValueException('Non-recurring event has active exceptions.');
            }
            if ($period === null || calendar_transfer_overlap($parent, $period)) {
                $emit(calendar_transfer_event_lines($parent, true, false, $extra));
            }
            continue;
        }
        $overrides = [];
        $cancelled = [];
        foreach ($eventExceptions as $exception) {
            $exception = calendar_event_exception_metadata($exception);
            $original = $exception['calendar_event_exception_original_start_date'];
            $occurrence = calendar_event_exception_source_occurrence($parent, $original);
            if ($occurrence === null || isset($overrides[$original]) || isset($cancelled[$original])) {
                throw new UnexpectedValueException('Invalid or duplicate recurrence exception.');
            }
            if ($exception['calendar_event_exception_kind'] === 'cancelled') {
                $cancelled[$original] = true;
            } else {
                $overrides[$original] = calendar_transfer_override_row($parent, $exception, $occurrence);
            }
        }
        $parentTimed = calendar_event_time_validate_all_day($parent['calendar_event_all_day']) === false;
        if ($period === null) {
            $master = calendar_transfer_event_lines($parent, true, $parentTimed, $extra);
            $properties = [calendar_transfer_rule($parent, $settings)];
            foreach ($cancelled as $original => $_) {
                $properties[] = calendar_transfer_original_property('EXDATE', $parent, $original);
            }
            $emit(calendar_transfer_event_properties($master, $properties));
            foreach ($overrides as $original => $override) {
                $event = calendar_transfer_event_lines($override, true, $parentTimed, $extra);
                $emit(calendar_transfer_event_properties($event,
                    [calendar_transfer_original_property('RECURRENCE-ID', $parent, $original)]));
            }
            continue;
        }
        // Enumerate original dates with the existing recurrence engine, then use effective dates.
        // Moved-in overrides are handled separately, even if their original date is outside the period.
        $span = (int) (new DateTimeImmutable($parent['calendar_event_start_date']))
            ->diff(new DateTimeImmutable($parent['calendar_event_end_date']))->days;
        $floor = (new DateTimeImmutable($period[0]))->modify('-' . $span . ' days')->format('Y-m-d');
        $starts = calendar_recurrence_occurrence_starts($settings['repeat_type'],
            $parent['calendar_event_start_date'], $floor, $period[1], $settings['repeat_until']);
        $emitOccurrence = static function (array $row, string $original) use ($uid, $period, $extra, $emit, &$seen): void {
            if (!calendar_transfer_overlap($row, $period)) {
                return;
            }
            $occUid = calendar_transfer_occurrence_uid($uid, $original);
            if (isset($seen[$occUid])) {
                throw new DomainException('Duplicate export UID.');
            }
            $seen[$occUid] = true;
            $row['calendar_event_ics_uid'] = $occUid;
            $row['calendar_event_repeat_type'] = 'none';
            $row['calendar_event_repeat_until'] = null;
            $emit(calendar_transfer_event_lines($row, true, false, $extra));
        };
        foreach ($starts as $original) {
            if (isset($cancelled[$original]) || isset($overrides[$original])) {
                continue;
            }
            $row = $parent;
            $row['calendar_event_start_date'] = $original;
            $row['calendar_event_end_date'] = (new DateTimeImmutable($original))->modify('+' . $span . ' days')->format('Y-m-d');
            $emitOccurrence($row, $original);
        }
        foreach ($overrides as $original => $override) {
            $emitOccurrence($override, $original);
        }
    }
    $append(['END:VCALENDAR']);
    return $ics;
}

/** Read owner-scoped data without initializing defaults or mutating any rows. */
function calendar_transfer_export(PDO $pdo, int $ownerId, ?int $sourceId, ?array $period, bool $extra): array
{
    if ($ownerId <= 0 || ($sourceId !== null && $sourceId <= 0)) {
        throw new InvalidArgumentException('Invalid Calendar source.');
    }
    if (!calendar_ics_uid_schema_ready($pdo) || !calendar_source_schema_ready($pdo)) {
        throw new LogicException('Migration 037 is required.');
    }
    $sources = db_table_identifier('calendar_source');
    $events = db_table_identifier('calendar_event');
    $exceptions = db_table_identifier('calendar_event_exception');
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        $sql = "SELECT calendar_source_id,calendar_source_name,calendar_source_color,calendar_source_default FROM $sources"
            . ' WHERE calendar_source_owner=:owner AND calendar_source_flag=0';
        $params = [':owner' => $ownerId];
        if ($sourceId !== null) {
            $sql .= ' AND calendar_source_id=:source';
            $params[':source'] = $sourceId;
        }
        $stmt = $pdo->prepare($sql . ' ORDER BY calendar_source_default DESC, calendar_source_id LIMIT 21');
        $stmt->execute($params);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($list === []) {
            throw new OutOfBoundsException('Calendar source was not found.');
        }
        if (count($list) > CALENDAR_SOURCE_MAX_ACTIVE) {
            throw new LengthException('Too many Calendars.');
        }
        $stmt = $pdo->prepare("SELECT e.* FROM $events e WHERE e.calendar_event_owner=:owner AND e.calendar_event_flag=0 ORDER BY e.calendar_event_id LIMIT 501");
        $stmt->execute([':owner' => $ownerId]);
        $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($allRows) > 500) {
            throw new LengthException('Too many Calendar events.');
        }
        $stmt = $pdo->prepare("SELECT x.* FROM $exceptions x INNER JOIN $events e"
            . ' ON e.calendar_event_id=x.calendar_event_exception_event_id AND e.calendar_event_owner=x.calendar_event_exception_owner'
            . ' WHERE x.calendar_event_exception_owner=:owner AND x.calendar_event_exception_flag=0 AND e.calendar_event_flag=0'
            . ' ORDER BY x.calendar_event_exception_original_start_date,x.calendar_event_exception_id LIMIT 501');
        $stmt->execute([':owner' => $ownerId]);
        $exceptionRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($exceptionRows) > CALENDAR_EXCEPTION_MAX_ACTIVE) {
            throw new LengthException('Too many Calendar exceptions.');
        }
        $byEvent = [];
        foreach ($exceptionRows as $row) {
            $byEvent[(int) $row['calendar_event_exception_event_id']][] = $row;
        }
        $marks = [];
        if ($extra) {
            $table = db_table_identifier('calendar_day_mark');
            $stmt = $pdo->prepare("SELECT mark_date,mark_label,mark_note FROM $table WHERE mark_owner=:owner AND mark_date BETWEEN :start AND :end ORDER BY mark_date");
            $stmt->execute([':owner' => $ownerId, ':start' => $period[0] ?? '2000-01-01', ':end' => $period[1] ?? '2100-12-31']);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $mark) {
                $marks[$mark['mark_date']] = ['label' => $mark['mark_label'], 'note' => $mark['mark_note']];
            }
        }
        $files = [];
        $size = 0;
        $assigned = 0;
        foreach ($list as $sourceRow) {
            $source = calendar_source_normalize_row($sourceRow);
            if ($source === null) {
                throw new UnexpectedValueException('Invalid Calendar source.');
            }
            $rows = array_values(array_filter($allRows, static fn(array $row): bool =>
                (int) ($row['calendar_event_source_id'] ?? 0) === $source['source_id']
                || ($source['is_default'] && $row['calendar_event_source_id'] === null)));
            $assigned += count($rows);
            // Day marks belong to the owner, not a source; include once (default-first for ZIP).
            $ics = calendar_transfer_build($source, $rows, $byEvent, $marks, $period, $extra);
            $marks = [];
            $size += strlen($ics);
            if ($size > CALENDAR_TRANSFER_MAX_ZIP_BYTES) {
                throw new LengthException('Calendar export exceeds 16 MiB total.');
            }
            // Use IDs only for safe, unique filenames. Calendar names are in each ICS.
            $files['calendar-' . $source['source_id'] . '.ics'] = $ics;
        }
        if ($sourceId === null && $assigned !== count($allRows)) {
            throw new UnexpectedValueException('Some Calendar events have no active source.');
        }
        if ($started) {
            $pdo->commit();
        }
        return $files;
    } catch (Throwable $e) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** JSON transport, matching OPML; ZIP is base64 for the later download UI. */
function api_calendar_transfer_export(int $userId, array $input): array
{
    if (array_diff(array_keys($input), ['action','csrf_token','source_id','scope','range','start_date','end_date','include_extra']) !== []) {
        return api_error('validation_error', '出力条件を確認してください。', 422);
    }
    $scope = $input['scope'] ?? 'single';
    $range = $input['range'] ?? 'all';
    $extra = $input['include_extra'] ?? '1';
    $source = app_validate_positive_int($input['source_id'] ?? null);
    if (!in_array($scope, ['single','all'], true) || !in_array($range, ['all','period'], true)
        || !in_array($extra, ['0','1'], true) || ($scope === 'single' && $source === null)
        || ($scope === 'all' && isset($input['source_id']) && $input['source_id'] !== '')
        || ($range === 'all' && (isset($input['start_date']) || isset($input['end_date'])))) {
        return api_error('validation_error', 'Calendarと出力条件を確認してください。', 422);
    }
    try {
        $period = $range === 'period' ? calendar_transfer_period($input['start_date'] ?? null, $input['end_date'] ?? null) : null;
        $files = calendar_transfer_export(conn_db(), $userId, $scope === 'all' ? null : $source, $period, $extra === '1');
        $data = ['scope' => $scope, 'range' => $range,
            'warning' => $period === null ? '' : '期間指定では繰り返し予定を個別予定として出力します。'];
        if ($scope === 'all') {
            $zip = calendar_transfer_zip($files, CALENDAR_TRANSFER_MAX_ZIP_BYTES + 8192);
            $data += ['filename' => 'rss-reader-calendars.zip', 'zip_base64' => base64_encode($zip)];
        } else {
            $data += ['filename' => array_key_first($files), 'ics' => reset($files)];
        }
        return api_success($data);
    } catch (OutOfBoundsException $e) {
        return api_error('not_found', 'Calendarが見つかりません。', 404);
    } catch (InvalidArgumentException $e) {
        return api_error('validation_error', '出力期間または条件が不正です。', 422);
    } catch (DomainException | LengthException $e) {
        return api_error('calendar_transfer_unsupported', '出力上限を超えたか、UIDが重複しています。条件を確認してください。', 422);
    } catch (LogicException $e) {
        return api_error('calendar_transfer_unavailable', 'DB Migration 037が必要です。', 503);
    } catch (UnexpectedValueException $e) {
        return api_error('calendar_transfer_invalid_data', '出力できない予定データがあります。', 422);
    }
}
