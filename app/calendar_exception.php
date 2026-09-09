<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_color.php';
require_once __DIR__ . '/calendar_time.php';
require_once __DIR__ . '/calendar_recurrence.php';

final class CalendarOccurrenceConflictException extends RuntimeException
{
}

const CALENDAR_EXCEPTION_MAX_ACTIVE = 500;
const CALENDAR_EXCEPTION_MAX_RANGE_ROWS = 2000;

function calendar_event_exception_kind_validate(mixed $value): ?string
{
    return is_string($value) && in_array($value, ['override', 'cancelled'], true) ? $value : null;
}

function calendar_event_occurrence_revision_validate(mixed $value): ?string
{
    return is_string($value) && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1 ? $value : null;
}

/** @return array<string,mixed> */
function calendar_event_exception_metadata(array $row): array
{
    $exceptionId = app_validate_positive_int($row['calendar_event_exception_id'] ?? null);
    $ownerId = app_validate_positive_int($row['calendar_event_exception_owner'] ?? null);
    $eventId = app_validate_positive_int($row['calendar_event_exception_event_id'] ?? null);
    $originalStart = calendar_validate_date($row['calendar_event_exception_original_start_date'] ?? null);
    $kind = calendar_event_exception_kind_validate($row['calendar_event_exception_kind'] ?? null);
    $revision = app_validate_positive_int($row['calendar_event_exception_revision'] ?? null);
    $flagValue = $row['calendar_event_exception_flag'] ?? null;
    $flag = in_array($flagValue, [0, '0'], true) ? 0 : (in_array($flagValue, [1, '1'], true) ? 1 : null);
    if ($exceptionId === null || $ownerId === null || $eventId === null || $originalStart === null
        || $kind === null || $revision === null || $flag === null) {
        throw new UnexpectedValueException('Calendar occurrence exception data is invalid.');
    }
    $row['calendar_event_exception_id'] = $exceptionId;
    $row['calendar_event_exception_owner'] = $ownerId;
    $row['calendar_event_exception_event_id'] = $eventId;
    $row['calendar_event_exception_original_start_date'] = $originalStart;
    $row['calendar_event_exception_kind'] = $kind;
    $row['calendar_event_exception_revision'] = $revision;
    $row['calendar_event_exception_flag'] = $flag;
    return $row;
}

/** @return array<string,mixed>|null */
function calendar_event_exception_source_occurrence(array $parentRow, string $originalStart): ?array
{
    $parent = calendar_normalize_event_row($parentRow);
    $originalStart = calendar_validate_date($originalStart) ?? '';
    if ($parent === null || $originalStart === '') {
        return null;
    }
    $repeatType = calendar_event_recurrence_validate_type($parent['calendar_event_repeat_type'] ?? null);
    if ($repeatType === null || $repeatType === 'none') {
        return null;
    }
    foreach (calendar_event_recurrence_expand_row($parent, $originalStart, $originalStart) as $occurrence) {
        if (($occurrence['original_occurrence_start_date'] ?? null) === $originalStart) {
            return $occurrence;
        }
    }
    return null;
}

/** @param array<string,mixed>|null $exceptionRow */
function calendar_event_occurrence_revision(array $sourceOccurrence, ?array $exceptionRow): string
{
    $exception = null;
    if ($exceptionRow !== null) {
        $exceptionRow = calendar_event_exception_metadata($exceptionRow);
        $exception = [
            'id' => $exceptionRow['calendar_event_exception_id'],
            'revision' => $exceptionRow['calendar_event_exception_revision'],
            'flag' => $exceptionRow['calendar_event_exception_flag'],
            'kind' => $exceptionRow['calendar_event_exception_kind'],
        ];
    }
    $state = [
        'event_id' => (int) ($sourceOccurrence['event_id'] ?? 0),
        'original_start' => (string) ($sourceOccurrence['original_occurrence_start_date'] ?? ''),
        'source_start' => (string) ($sourceOccurrence['source_start_date'] ?? ''),
        'source_end' => (string) ($sourceOccurrence['source_end_date'] ?? ''),
        'title' => (string) ($sourceOccurrence['title'] ?? ''),
        'note' => (string) ($sourceOccurrence['note'] ?? ''),
        'color' => (string) ($sourceOccurrence['color'] ?? ''),
        'all_day' => ($sourceOccurrence['all_day'] ?? true) === true,
        'start_time' => $sourceOccurrence['start_time'] ?? null,
        'end_time' => $sourceOccurrence['end_time'] ?? null,
        'url' => $sourceOccurrence['url'] ?? null,
        'repeat_type' => (string) ($sourceOccurrence['repeat_type'] ?? ''),
        'repeat_until' => $sourceOccurrence['repeat_until'] ?? null,
        'updated_at' => (string) ($sourceOccurrence['updated_at'] ?? ''),
        'exception' => $exception,
    ];
    return hash('sha256', json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

/** @param array<string,mixed>|null $exceptionRow @return array<string,mixed> */
function calendar_event_exception_base_item(array $sourceOccurrence, ?array $exceptionRow): array
{
    $sourceOccurrence['exception_id'] = null;
    $sourceOccurrence['exception_kind'] = null;
    $sourceOccurrence['is_exception'] = false;
    $sourceOccurrence['is_cancelled'] = false;
    $sourceOccurrence['occurrence_revision'] = calendar_event_occurrence_revision($sourceOccurrence, $exceptionRow);
    return $sourceOccurrence;
}

/** @return array<string,mixed> */
function calendar_event_exception_cancelled_item(array $sourceOccurrence, array $exceptionRow): array
{
    $exceptionRow = calendar_event_exception_metadata($exceptionRow);
    $item = calendar_event_exception_base_item($sourceOccurrence, $exceptionRow);
    $item['exception_id'] = $exceptionRow['calendar_event_exception_id'];
    $item['exception_kind'] = 'cancelled';
    $item['is_exception'] = true;
    $item['is_cancelled'] = true;
    $item['exception_updated_at'] = (string) ($exceptionRow['calendar_event_exception_updated_at'] ?? '');
    return $item;
}

/** @return array<string,mixed> */
function calendar_event_exception_override_item(array $sourceOccurrence, array $exceptionRow): array
{
    $exceptionRow = calendar_event_exception_metadata($exceptionRow);
    $title = calendar_validate_event_title($exceptionRow['calendar_event_exception_title'] ?? null);
    $note = calendar_validate_event_note($exceptionRow['calendar_event_exception_note'] ?? null);
    $range = calendar_validate_event_range(
        $exceptionRow['calendar_event_exception_start_date'] ?? null,
        $exceptionRow['calendar_event_exception_end_date'] ?? null
    );
    $color = calendar_event_color_validate($exceptionRow['calendar_event_exception_color'] ?? null);
    if ($title === null || $note === null || $range === null || $color === null) {
        throw new UnexpectedValueException('Calendar occurrence override data is invalid.');
    }
    $time = calendar_event_time_settings(
        $exceptionRow['calendar_event_exception_all_day'] ?? null,
        $exceptionRow['calendar_event_exception_start_time'] ?? null,
        $exceptionRow['calendar_event_exception_end_time'] ?? null,
        $exceptionRow['calendar_event_exception_url'] ?? null,
        $range[0],
        $range[1]
    );
    if ($time === null) {
        throw new UnexpectedValueException('Calendar occurrence override time data is invalid.');
    }
    return [
        'kind' => 'event',
        'event_id' => (int) $sourceOccurrence['event_id'],
        'occurrence_key' => (string) $sourceOccurrence['occurrence_key'],
        'original_occurrence_start_date' => (string) $sourceOccurrence['original_occurrence_start_date'],
        'title' => $title,
        'note' => $note,
        'color' => $color,
        'occurrence_start_date' => $range[0],
        'occurrence_end_date' => $range[1],
        'source_start_date' => (string) $sourceOccurrence['source_start_date'],
        'source_end_date' => (string) $sourceOccurrence['source_end_date'],
        'source_title' => (string) ($sourceOccurrence['source_title'] ?? $sourceOccurrence['title']),
        'source_note' => (string) ($sourceOccurrence['source_note'] ?? $sourceOccurrence['note']),
        'source_color' => (string) ($sourceOccurrence['source_color'] ?? $sourceOccurrence['color']),
        'source_all_day' => ($sourceOccurrence['source_all_day'] ?? $sourceOccurrence['all_day']) === true,
        'source_start_time' => $sourceOccurrence['source_start_time'] ?? $sourceOccurrence['start_time'],
        'source_end_time' => $sourceOccurrence['source_end_time'] ?? $sourceOccurrence['end_time'],
        'source_url' => $sourceOccurrence['source_url'] ?? $sourceOccurrence['url'],
        'all_day' => $time['all_day'],
        'start_time' => $time['start_time'] === null ? null : substr($time['start_time'], 0, 5),
        'end_time' => $time['end_time'] === null ? null : substr($time['end_time'], 0, 5),
        'url' => $time['url'],
        'repeat_type' => (string) $sourceOccurrence['repeat_type'],
        'repeat_until' => $sourceOccurrence['repeat_until'] ?? null,
        'updated_at' => (string) ($exceptionRow['calendar_event_exception_updated_at'] ?? ''),
        'exception_id' => $exceptionRow['calendar_event_exception_id'],
        'exception_kind' => 'override',
        'is_exception' => true,
        'is_cancelled' => false,
        'exception_updated_at' => (string) ($exceptionRow['calendar_event_exception_updated_at'] ?? ''),
        'occurrence_revision' => calendar_event_occurrence_revision($sourceOccurrence, $exceptionRow),
    ];
}

function calendar_event_exception_overlaps(array $item, string $rangeStart, string $rangeEnd): bool
{
    $range = calendar_validate_event_range(
        $item['occurrence_start_date'] ?? null,
        $item['occurrence_end_date'] ?? null
    );
    return $range !== null && $range[1] >= $rangeStart && $range[0] <= $rangeEnd;
}

/** @return list<array<string,mixed>> */
function calendar_event_exception_range_rows(int $ownerId, string $rangeStart, string $rangeEnd): array
{
    $start = calendar_validate_date($rangeStart);
    $end = calendar_validate_date($rangeEnd);
    if ($ownerId <= 0 || $start === null || $end === null || $end < $start
        || (int) (new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days > 41) {
        throw new InvalidArgumentException('Calendar occurrence exception range is invalid.');
    }
    $originalFloor = (new DateTimeImmutable($start))->modify('-365 days')->format('Y-m-d');
    $stmt = conn_db()->prepare(
        'SELECT e.*, p.* FROM ' . db_table_identifier('calendar_event_exception') . ' e '
        . 'INNER JOIN ' . db_table_identifier('calendar_event') . ' p '
        . 'ON p.calendar_event_id = e.calendar_event_exception_event_id '
        . 'AND p.calendar_event_owner = e.calendar_event_exception_owner '
        . 'AND p.calendar_event_flag = 0 '
        . 'WHERE e.calendar_event_exception_owner = :owner AND ('
        . 'e.calendar_event_exception_original_start_date BETWEEN :original_floor AND :original_end '
        . "OR (e.calendar_event_exception_flag = 0 AND e.calendar_event_exception_kind = 'override' "
        . 'AND e.calendar_event_exception_start_date <= :effective_end '
        . 'AND e.calendar_event_exception_end_date >= :effective_start)) '
        . 'ORDER BY e.calendar_event_exception_id ASC LIMIT ' . (CALENDAR_EXCEPTION_MAX_RANGE_ROWS + 1)
    );
    $stmt->execute([
        ':owner' => $ownerId,
        ':original_floor' => $originalFloor,
        ':original_end' => $end,
        ':effective_start' => $start,
        ':effective_end' => $end,
    ]);
    $rows = $stmt->fetchAll();
    if (count($rows) > CALENDAR_EXCEPTION_MAX_RANGE_ROWS) {
        throw new LengthException('Calendar occurrence exception range is too large.');
    }
    return array_values(array_filter($rows, 'is_array'));
}

/**
 * @param list<array<string,mixed>> $baseEvents
 * @return array{events:list<array<string,mixed>>,cancelled_occurrences:list<array<string,mixed>>}
 */
function calendar_event_exception_apply_range(
    int $ownerId,
    string $rangeStart,
    string $rangeEnd,
    array $baseEvents
): array {
    $rowsByKey = [];
    $parentByKey = [];
    foreach (calendar_event_exception_range_rows($ownerId, $rangeStart, $rangeEnd) as $row) {
        $exception = calendar_event_exception_metadata($row);
        if ((int) $exception['calendar_event_exception_owner'] !== $ownerId) {
            continue;
        }
        $key = calendar_event_occurrence_key(
            (int) $exception['calendar_event_exception_event_id'],
            (string) $exception['calendar_event_exception_original_start_date']
        );
        if (isset($rowsByKey[$key])) {
            throw new UnexpectedValueException('Calendar occurrence exception identity is duplicated.');
        }
        $parent = calendar_normalize_event_row($row);
        if ($parent === null || (int) $parent['calendar_event_owner'] !== $ownerId) {
            throw new UnexpectedValueException('Calendar occurrence exception parent is invalid.');
        }
        $rowsByKey[$key] = $exception;
        $parentByKey[$key] = $parent;
    }

    $events = [];
    $cancelled = [];
    $processed = [];
    foreach ($baseEvents as $baseEvent) {
        $key = (string) ($baseEvent['occurrence_key'] ?? '');
        $exception = $rowsByKey[$key] ?? null;
        $processed[$key] = true;
        if ($exception === null || (int) $exception['calendar_event_exception_flag'] !== 0) {
            $events[] = calendar_event_exception_base_item($baseEvent, $exception);
            continue;
        }
        $parent = $parentByKey[$key] ?? [];
        $source = calendar_event_exception_source_occurrence(
            $parent,
            (string) $exception['calendar_event_exception_original_start_date']
        );
        if ($source === null) {
            throw new UnexpectedValueException('Calendar occurrence exception no longer matches its series.');
        }
        if ($exception['calendar_event_exception_kind'] === 'cancelled') {
            $cancelled[] = calendar_event_exception_cancelled_item($source, $exception);
            continue;
        }
        $override = calendar_event_exception_override_item($source, $exception);
        if (calendar_event_exception_overlaps($override, $rangeStart, $rangeEnd)) {
            $events[] = $override;
        }
    }

    foreach ($rowsByKey as $key => $exception) {
        if (isset($processed[$key]) || (int) $exception['calendar_event_exception_flag'] !== 0
            || $exception['calendar_event_exception_kind'] !== 'override') {
            continue;
        }
        $source = calendar_event_exception_source_occurrence(
            $parentByKey[$key] ?? [],
            (string) $exception['calendar_event_exception_original_start_date']
        );
        if ($source === null) {
            throw new UnexpectedValueException('Calendar occurrence exception no longer matches its series.');
        }
        $override = calendar_event_exception_override_item($source, $exception);
        if (calendar_event_exception_overlaps($override, $rangeStart, $rangeEnd)) {
            $events[] = $override;
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
    usort($cancelled, static fn(array $left, array $right): int => [
        (string) ($left['original_occurrence_start_date'] ?? ''),
        (int) ($left['event_id'] ?? 0),
    ] <=> [
        (string) ($right['original_occurrence_start_date'] ?? ''),
        (int) ($right['event_id'] ?? 0),
    ]);
    return ['events' => $events, 'cancelled_occurrences' => $cancelled];
}

/** @return array<string,mixed>|null */
function calendar_event_exception_lock_row(PDO $pdo, int $ownerId, int $eventId, string $originalStart): ?array
{
    $sql = 'SELECT * FROM ' . db_table_identifier('calendar_event_exception') . ' '
        . 'WHERE calendar_event_exception_owner = :owner '
        . 'AND calendar_event_exception_event_id = :event_id '
        . 'AND calendar_event_exception_original_start_date = :original_start';
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':owner' => $ownerId, ':event_id' => $eventId, ':original_start' => $originalStart]);
    $row = $stmt->fetch();
    return is_array($row) ? calendar_event_exception_metadata($row) : null;
}

function calendar_event_exception_active_count(PDO $pdo, int $ownerId): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM ' . db_table_identifier('calendar_event_exception') . ' '
        . 'WHERE calendar_event_exception_owner = :owner AND calendar_event_exception_flag = 0'
    );
    $stmt->execute([':owner' => $ownerId]);
    return (int) $stmt->fetchColumn();
}

/** @return array{parent:array<string,mixed>,source:array<string,mixed>,exception:?array<string,mixed>} */
function calendar_event_exception_mutation_state(
    PDO $pdo,
    int $ownerId,
    int $eventId,
    string $originalStart,
    string $expectedRevision
): array {
    if ($ownerId <= 0 || $eventId <= 0 || calendar_validate_date($originalStart) === null
        || calendar_event_occurrence_revision_validate($expectedRevision) === null) {
        throw new InvalidArgumentException('Calendar occurrence target is invalid.');
    }
    $parent = calendar_lock_owned_event($pdo, $ownerId, $eventId);
    if ($parent === null) {
        throw new OutOfBoundsException('Calendar occurrence was not found.');
    }
    $source = calendar_event_exception_source_occurrence($parent, $originalStart);
    if ($source === null) {
        throw new OutOfBoundsException('Calendar occurrence was not found.');
    }
    $exception = calendar_event_exception_lock_row($pdo, $ownerId, $eventId, $originalStart);
    $currentRevision = calendar_event_occurrence_revision($source, $exception);
    if (!hash_equals($currentRevision, $expectedRevision)) {
        throw new CalendarOccurrenceConflictException('Calendar occurrence changed. Reload and try again.');
    }
    return ['parent' => $parent, 'source' => $source, 'exception' => $exception];
}

/**
 * @param array{all_day:bool,start_time:?string,end_time:?string,url:?string}|null $settings
 * @return array<string,mixed>
 */
function calendar_event_exception_save(
    PDO $pdo,
    int $ownerId,
    int $eventId,
    string $originalStart,
    string $kind,
    ?string $title,
    ?string $startDate,
    ?string $endDate,
    ?string $note,
    ?string $color,
    ?array $settings,
    ?array $existing
): array {
    if (($existing === null || (int) $existing['calendar_event_exception_flag'] !== 0)
        && calendar_event_exception_active_count($pdo, $ownerId) >= CALENDAR_EXCEPTION_MAX_ACTIVE) {
        throw new LengthException('Calendar can contain up to 500 active occurrence exceptions.');
    }
    $now = app_now();
    $params = [
        ':kind' => $kind,
        ':title' => $title,
        ':start_date' => $startDate,
        ':end_date' => $endDate,
        ':note' => $note,
        ':color' => $color,
        ':all_day' => $settings === null ? null : ($settings['all_day'] ? 1 : 0),
        ':start_time' => $settings['start_time'] ?? null,
        ':end_time' => $settings['end_time'] ?? null,
        ':url' => $settings['url'] ?? null,
        ':updated_at' => $now,
        ':owner' => $ownerId,
        ':event_id' => $eventId,
        ':original_start' => $originalStart,
    ];
    if ($existing === null) {
        $stmt = $pdo->prepare(
            'INSERT INTO ' . db_table_identifier('calendar_event_exception') . ' ('
            . 'calendar_event_exception_owner, calendar_event_exception_event_id, '
            . 'calendar_event_exception_original_start_date, calendar_event_exception_kind, '
            . 'calendar_event_exception_revision, calendar_event_exception_flag, '
            . 'calendar_event_exception_start_date, calendar_event_exception_end_date, '
            . 'calendar_event_exception_title, calendar_event_exception_note, calendar_event_exception_color, '
            . 'calendar_event_exception_all_day, calendar_event_exception_start_time, '
            . 'calendar_event_exception_end_time, calendar_event_exception_url, '
            . 'calendar_event_exception_created_at, calendar_event_exception_updated_at) VALUES ('
            . ':owner, :event_id, :original_start, :kind, 1, 0, :start_date, :end_date, '
            . ':title, :note, :color, :all_day, :start_time, :end_time, :url, :created_at, :updated_at)'
        );
        $params[':created_at'] = $now;
        $stmt->execute($params);
    } else {
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('calendar_event_exception') . ' SET '
            . 'calendar_event_exception_kind = :kind, '
            . 'calendar_event_exception_revision = calendar_event_exception_revision + 1, '
            . 'calendar_event_exception_flag = 0, '
            . 'calendar_event_exception_start_date = :start_date, '
            . 'calendar_event_exception_end_date = :end_date, '
            . 'calendar_event_exception_title = :title, '
            . 'calendar_event_exception_note = :note, '
            . 'calendar_event_exception_color = :color, '
            . 'calendar_event_exception_all_day = :all_day, '
            . 'calendar_event_exception_start_time = :start_time, '
            . 'calendar_event_exception_end_time = :end_time, '
            . 'calendar_event_exception_url = :url, '
            . 'calendar_event_exception_updated_at = :updated_at '
            . 'WHERE calendar_event_exception_owner = :owner '
            . 'AND calendar_event_exception_event_id = :event_id '
            . 'AND calendar_event_exception_original_start_date = :original_start'
        );
        $stmt->execute($params);
    }
    $saved = calendar_event_exception_lock_row($pdo, $ownerId, $eventId, $originalStart);
    if ($saved === null) {
        throw new RuntimeException('Calendar occurrence exception could not be saved.');
    }
    return $saved;
}

/** @param array{all_day:bool,start_time:?string,end_time:?string,url:?string} $timeSettings @return array<string,mixed> */
function calendar_event_occurrence_update(
    int $ownerId,
    int $eventId,
    string $originalStart,
    string $title,
    string $startDate,
    string $endDate,
    string $note,
    string $color,
    array $timeSettings,
    string $expectedRevision
): array {
    $title = calendar_validate_event_title($title);
    $note = calendar_validate_event_note($note);
    $range = calendar_validate_event_range($startDate, $endDate);
    $color = calendar_event_color_validate($color);
    $time = $range === null ? null : calendar_event_time_settings(
        $timeSettings['all_day'] ?? null,
        $timeSettings['start_time'] ?? null,
        $timeSettings['end_time'] ?? null,
        $timeSettings['url'] ?? null,
        $range[0],
        $range[1]
    );
    if ($title === null || $note === null || $range === null || $color === null || $time === null) {
        throw new InvalidArgumentException('Calendar occurrence settings are invalid.');
    }
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        $state = calendar_event_exception_mutation_state(
            $pdo,
            $ownerId,
            $eventId,
            $originalStart,
            $expectedRevision
        );
        $saved = calendar_event_exception_save(
            $pdo,
            $ownerId,
            $eventId,
            $originalStart,
            'override',
            $title,
            $range[0],
            $range[1],
            $note,
            $color,
            $time,
            $state['exception']
        );
        $result = calendar_event_exception_override_item($state['source'], $saved);
        if ($started) {
            $pdo->commit();
        }
        return $result;
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/** @return array<string,mixed> */
function calendar_event_occurrence_cancel(
    int $ownerId,
    int $eventId,
    string $originalStart,
    string $expectedRevision
): array {
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        $state = calendar_event_exception_mutation_state(
            $pdo,
            $ownerId,
            $eventId,
            $originalStart,
            $expectedRevision
        );
        if ($state['exception'] !== null
            && (int) $state['exception']['calendar_event_exception_flag'] === 0
            && $state['exception']['calendar_event_exception_kind'] === 'cancelled') {
            $result = calendar_event_exception_cancelled_item($state['source'], $state['exception']);
        } else {
            $saved = calendar_event_exception_save(
                $pdo,
                $ownerId,
                $eventId,
                $originalStart,
                'cancelled',
                null,
                null,
                null,
                null,
                null,
                null,
                $state['exception']
            );
            $result = calendar_event_exception_cancelled_item($state['source'], $saved);
        }
        if ($started) {
            $pdo->commit();
        }
        return $result;
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/** @return array<string,mixed> */
function calendar_event_occurrence_restore(
    int $ownerId,
    int $eventId,
    string $originalStart,
    string $expectedRevision
): array {
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        $state = calendar_event_exception_mutation_state(
            $pdo,
            $ownerId,
            $eventId,
            $originalStart,
            $expectedRevision
        );
        if ($state['exception'] === null || (int) $state['exception']['calendar_event_exception_flag'] !== 0) {
            throw new OutOfBoundsException('Calendar occurrence exception was not found.');
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('calendar_event_exception') . ' SET '
            . 'calendar_event_exception_flag = 1, '
            . 'calendar_event_exception_revision = calendar_event_exception_revision + 1, '
            . 'calendar_event_exception_updated_at = :updated_at '
            . 'WHERE calendar_event_exception_owner = :owner '
            . 'AND calendar_event_exception_event_id = :event_id '
            . 'AND calendar_event_exception_original_start_date = :original_start '
            . 'AND calendar_event_exception_flag = 0'
        );
        $stmt->execute([
            ':updated_at' => app_now(),
            ':owner' => $ownerId,
            ':event_id' => $eventId,
            ':original_start' => $originalStart,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new CalendarOccurrenceConflictException('Calendar occurrence changed. Reload and try again.');
        }
        $restored = calendar_event_exception_lock_row($pdo, $ownerId, $eventId, $originalStart);
        if ($restored === null) {
            throw new RuntimeException('Calendar occurrence exception could not be restored.');
        }
        $result = calendar_event_exception_base_item($state['source'], $restored);
        if ($started) {
            $pdo->commit();
        }
        return $result;
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/**
 * Refuse a series change when an active exception would no longer refer to a
 * real occurrence. Callers already hold the parent row lock and transaction.
 *
 * @param array<string,mixed> $parentRow
 * @param array{repeat_type:string,repeat_until:?string}|null $repeatSettings
 */
function calendar_event_exception_assert_series_change_allowed(
    PDO $pdo,
    int $ownerId,
    array $parentRow,
    string $startDate,
    string $endDate,
    ?array $repeatSettings = null
): void {
    $eventId = app_validate_positive_int($parentRow['calendar_event_id'] ?? null);
    $range = calendar_validate_event_range($startDate, $endDate);
    if ($ownerId <= 0 || $eventId === null || $range === null
        || (int) ($parentRow['calendar_event_owner'] ?? 0) !== $ownerId) {
        throw new InvalidArgumentException('Calendar series exception check is invalid.');
    }
    $sql = 'SELECT * FROM ' . db_table_identifier('calendar_event_exception') . ' '
        . 'WHERE calendar_event_exception_owner = :owner '
        . 'AND calendar_event_exception_event_id = :event_id '
        . 'AND calendar_event_exception_flag = 0 '
        . 'ORDER BY calendar_event_exception_id ASC LIMIT ' . (CALENDAR_EXCEPTION_MAX_ACTIVE + 1);
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':owner' => $ownerId, ':event_id' => $eventId]);
    $exceptions = $stmt->fetchAll();
    if (count($exceptions) > CALENDAR_EXCEPTION_MAX_ACTIVE) {
        throw new LengthException('Calendar series contains too many occurrence exceptions.');
    }
    if ($exceptions === []) {
        return;
    }
    $proposed = $parentRow;
    $proposed['calendar_event_start_date'] = $range[0];
    $proposed['calendar_event_end_date'] = $range[1];
    if ($repeatSettings !== null) {
        $proposed['calendar_event_repeat_type'] = $repeatSettings['repeat_type'];
        $proposed['calendar_event_repeat_until'] = $repeatSettings['repeat_until'];
    }
    foreach ($exceptions as $exceptionRow) {
        if (!is_array($exceptionRow)) {
            throw new UnexpectedValueException('Calendar occurrence exception data is invalid.');
        }
        $exceptionRow = calendar_event_exception_metadata($exceptionRow);
        if (calendar_event_exception_source_occurrence(
            $proposed,
            (string) $exceptionRow['calendar_event_exception_original_start_date']
        ) === null) {
            throw new CalendarOccurrenceConflictException(
                'Series change would invalidate occurrence exceptions. Restore those exceptions first.'
            );
        }
    }
}
