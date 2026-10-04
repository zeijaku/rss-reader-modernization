<?php

declare(strict_types=1);

const CALENDAR_SOURCE_MAX_ACTIVE = 20;
const CALENDAR_SOURCE_NAME_MAX_LENGTH = 40;
const CALENDAR_SOURCE_DEFAULT_NAME = '既定Calendar';
const CALENDAR_SOURCE_COLOR_VALUES = ['blue', 'green', 'purple', 'yellow', 'red'];

function calendar_source_validate_name(mixed $value): ?string
{
    return app_validate_text($value, CALENDAR_SOURCE_NAME_MAX_LENGTH, false);
}

function calendar_source_validate_color(mixed $value): ?string
{
    return is_string($value) && in_array($value, CALENDAR_SOURCE_COLOR_VALUES, true) ? $value : null;
}

function calendar_source_schema_ready(PDO $pdo): bool
{
    static $cache = [];
    $key = spl_object_id($pdo);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $pdo->query('SELECT calendar_source_id FROM ' . db_table_identifier('calendar_source') . ' WHERE 1 = 0');
        $pdo->query('SELECT calendar_event_source_id FROM ' . db_table_identifier('calendar_event') . ' WHERE 1 = 0');
        $cache[$key] = true;
    } catch (PDOException) {
        $cache[$key] = false;
    }
    return $cache[$key];
}

/** @return array{source_id:int,name:string,color:string,is_default:bool,source_type:string}|null */
function calendar_source_normalize_row(array $row): ?array
{
    $id = app_validate_positive_int($row['calendar_source_id'] ?? null);
    $name = calendar_source_validate_name($row['calendar_source_name'] ?? null);
    $color = calendar_source_validate_color($row['calendar_source_color'] ?? null);
    if ($id === null || $name === null || $color === null) {
        return null;
    }
    return [
        'source_id' => $id,
        'name' => $name,
        'color' => $color,
        'is_default' => (int) ($row['calendar_source_default'] ?? 0) === 1,
        'source_type' => 'local',
    ];
}

function calendar_source_active_count(PDO $pdo, int $ownerId): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM ' . db_table_identifier('calendar_source') . ' '
        . 'WHERE calendar_source_owner = :owner AND calendar_source_flag = 0'
    );
    $stmt->execute([':owner' => $ownerId]);
    return (int) $stmt->fetchColumn();
}

/** @return array{source_id:int,name:string,color:string,is_default:bool,source_type:string} */
function calendar_source_ensure_default(PDO $pdo, int $ownerId): array
{
    if ($ownerId <= 0 || !calendar_source_schema_ready($pdo)) {
        throw new InvalidArgumentException('Calendar source is unavailable.');
    }

    $stmt = $pdo->prepare(
        'SELECT calendar_source_id, calendar_source_name, calendar_source_color, calendar_source_default '
        . 'FROM ' . db_table_identifier('calendar_source') . ' '
        . 'WHERE calendar_source_owner = :owner AND calendar_source_flag = 0 AND calendar_source_default = 1 '
        . 'ORDER BY calendar_source_id ASC LIMIT 1'
    );
    $stmt->execute([':owner' => $ownerId]);
    $row = $stmt->fetch();
    if (is_array($row)) {
        $source = calendar_source_normalize_row($row);
        if ($source !== null) {
            return $source;
        }
    }

    $now = app_now();
    $insert = $pdo->prepare(
        'INSERT INTO ' . db_table_identifier('calendar_source') . ' '
        . '(calendar_source_date, calendar_source_updated_at, calendar_source_flag, calendar_source_owner, '
        . 'calendar_source_name, calendar_source_color, calendar_source_default, calendar_source_sort_order) '
        . 'VALUES (:created_at, :updated_at, 0, :owner, :name, :color, 1, 0)'
    );
    $insert->execute([
        ':created_at' => $now,
        ':updated_at' => $now,
        ':owner' => $ownerId,
        ':name' => CALENDAR_SOURCE_DEFAULT_NAME,
        ':color' => 'blue',
    ]);

    return [
        'source_id' => (int) $pdo->lastInsertId(),
        'name' => CALENDAR_SOURCE_DEFAULT_NAME,
        'color' => 'blue',
        'is_default' => true,
        'source_type' => 'local',
    ];
}

/** @return list<array{source_id:int,name:string,color:string,is_default:bool,source_type:string}> */
function calendar_source_list(int $ownerId): array
{
    if ($ownerId <= 0) {
        throw new InvalidArgumentException('Calendar source owner is invalid.');
    }
    $pdo = conn_db();
    if (!calendar_source_schema_ready($pdo)) {
        return [[
            'source_id' => 0,
            'name' => CALENDAR_SOURCE_DEFAULT_NAME,
            'color' => 'blue',
            'is_default' => true,
            'source_type' => 'local',
        ]];
    }

    calendar_source_ensure_default($pdo, $ownerId);
    $stmt = $pdo->prepare(
        'SELECT calendar_source_id, calendar_source_name, calendar_source_color, calendar_source_default '
        . 'FROM ' . db_table_identifier('calendar_source') . ' '
        . 'WHERE calendar_source_owner = :owner AND calendar_source_flag = 0 '
        . 'ORDER BY calendar_source_default DESC, calendar_source_sort_order ASC, calendar_source_id ASC '
        . 'LIMIT ' . CALENDAR_SOURCE_MAX_ACTIVE
    );
    $stmt->execute([':owner' => $ownerId]);
    $sources = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $source = calendar_source_normalize_row($row);
        if ($source !== null) {
            $sources[] = $source;
        }
    }
    return $sources;
}

/** @return array{source_id:int,name:string,color:string,is_default:bool,source_type:string}|null */
function calendar_source_owned(PDO $pdo, int $ownerId, int $sourceId): ?array
{
    if ($ownerId <= 0 || $sourceId <= 0 || !calendar_source_schema_ready($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT calendar_source_id, calendar_source_name, calendar_source_color, calendar_source_default '
        . 'FROM ' . db_table_identifier('calendar_source') . ' '
        . 'WHERE calendar_source_id = :source_id AND calendar_source_owner = :owner AND calendar_source_flag = 0'
    );
    $stmt->execute([':source_id' => $sourceId, ':owner' => $ownerId]);
    $row = $stmt->fetch();
    return is_array($row) ? calendar_source_normalize_row($row) : null;
}

function calendar_source_resolve_input(PDO $pdo, int $ownerId, mixed $value): int
{
    if (!calendar_source_schema_ready($pdo)) {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return 0;
        }
        throw new RuntimeException('Calendar source migration is required.');
    }

    if ($value === null || $value === '' || $value === 0 || $value === '0') {
        return calendar_source_ensure_default($pdo, $ownerId)['source_id'];
    }
    $sourceId = app_validate_positive_int($value);
    if ($sourceId === null || calendar_source_owned($pdo, $ownerId, $sourceId) === null) {
        throw new InvalidArgumentException('Calendar source is invalid.');
    }
    return $sourceId;
}

function calendar_source_assign_event(PDO $pdo, int $ownerId, int $eventId, mixed $sourceValue): int
{
    if ($ownerId <= 0 || $eventId <= 0) {
        throw new InvalidArgumentException('Calendar source target is invalid.');
    }
    $sourceId = calendar_source_resolve_input($pdo, $ownerId, $sourceValue);
    if ($sourceId === 0) {
        return 0;
    }
    $stmt = $pdo->prepare(
        'UPDATE ' . db_table_identifier('calendar_event') . ' '
        . 'SET calendar_event_source_id = :source_id '
        . 'WHERE calendar_event_id = :event_id AND calendar_event_owner = :owner AND calendar_event_flag = 0'
    );
    $stmt->execute([':source_id' => $sourceId, ':event_id' => $eventId, ':owner' => $ownerId]);
    if ($stmt->rowCount() < 1) {
        $check = calendar_lock_owned_event($pdo, $ownerId, $eventId);
        if ($check === null) {
            throw new OutOfBoundsException('Calendar event was not found.');
        }
    }
    return $sourceId;
}

function calendar_source_create(int $ownerId, string $name, string $color): int
{
    $name = calendar_source_validate_name($name);
    $color = calendar_source_validate_color($color);
    if ($ownerId <= 0 || $name === null || $color === null) {
        throw new InvalidArgumentException('Calendar source settings are invalid.');
    }
    $pdo = conn_db();
    if (!calendar_source_schema_ready($pdo)) {
        throw new RuntimeException('Calendar source migration is required.');
    }
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        calendar_source_ensure_default($pdo, $ownerId);
        if (calendar_source_active_count($pdo, $ownerId) >= CALENDAR_SOURCE_MAX_ACTIVE) {
            throw new LengthException('Calendar can contain up to 20 active calendars.');
        }
        $stmt = $pdo->prepare(
            'INSERT INTO ' . db_table_identifier('calendar_source') . ' '
            . '(calendar_source_date, calendar_source_updated_at, calendar_source_flag, calendar_source_owner, '
            . 'calendar_source_name, calendar_source_color, calendar_source_default, calendar_source_sort_order) '
            . 'VALUES (:created_at, :updated_at, 0, :owner, :name, :color, 0, :sort_order)'
        );
        $now = app_now();
        $stmt->execute([
            ':created_at' => $now,
            ':updated_at' => $now,
            ':owner' => $ownerId,
            ':name' => $name,
            ':color' => $color,
            ':sort_order' => calendar_source_active_count($pdo, $ownerId),
        ]);
        $id = (int) $pdo->lastInsertId();
        if ($started) {
            $pdo->commit();
        }
        return $id;
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function calendar_source_update(int $ownerId, int $sourceId, string $name, string $color): bool
{
    $name = calendar_source_validate_name($name);
    $color = calendar_source_validate_color($color);
    if ($ownerId <= 0 || $sourceId <= 0 || $name === null || $color === null) {
        throw new InvalidArgumentException('Calendar source settings are invalid.');
    }
    $pdo = conn_db();
    if (calendar_source_owned($pdo, $ownerId, $sourceId) === null) {
        return false;
    }
    $stmt = $pdo->prepare(
        'UPDATE ' . db_table_identifier('calendar_source') . ' '
        . 'SET calendar_source_name = :name, calendar_source_color = :color, calendar_source_updated_at = :updated_at '
        . 'WHERE calendar_source_id = :source_id AND calendar_source_owner = :owner AND calendar_source_flag = 0'
    );
    $stmt->execute([
        ':name' => $name,
        ':color' => $color,
        ':updated_at' => app_now(),
        ':source_id' => $sourceId,
        ':owner' => $ownerId,
    ]);
    return true;
}

function calendar_source_delete(int $ownerId, int $sourceId): bool
{
    if ($ownerId <= 0 || $sourceId <= 0) {
        return false;
    }
    $pdo = conn_db();
    if (!calendar_source_schema_ready($pdo)) {
        throw new RuntimeException('Calendar source migration is required.');
    }
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        $source = calendar_source_owned($pdo, $ownerId, $sourceId);
        if ($source === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        if ($source['is_default']) {
            throw new InvalidArgumentException('既定Calendarは削除出来ません。');
        }
        $defaultId = calendar_source_ensure_default($pdo, $ownerId)['source_id'];
        $move = $pdo->prepare(
            'UPDATE ' . db_table_identifier('calendar_event') . ' '
            . 'SET calendar_event_source_id = :default_id '
            . 'WHERE calendar_event_owner = :owner AND calendar_event_flag = 0 AND calendar_event_source_id = :source_id'
        );
        $move->execute([':default_id' => $defaultId, ':owner' => $ownerId, ':source_id' => $sourceId]);

        $delete = $pdo->prepare(
            'UPDATE ' . db_table_identifier('calendar_source') . ' '
            . 'SET calendar_source_flag = 1, calendar_source_updated_at = :updated_at '
            . 'WHERE calendar_source_id = :source_id AND calendar_source_owner = :owner AND calendar_source_flag = 0'
        );
        $delete->execute([':updated_at' => app_now(), ':source_id' => $sourceId, ':owner' => $ownerId]);
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

/**
 * Attach owner-scoped Calendar source metadata to already normalized events.
 *
 * @param list<array<string,mixed>> $events
 * @return array{events:list<array<string,mixed>>,sources:list<array{source_id:int,name:string,color:string,is_default:bool,source_type:string}>}
 */
function calendar_source_attach_to_events(int $ownerId, array $events): array
{
    $sources = calendar_source_list($ownerId);
    $pdo = conn_db();
    if (!calendar_source_schema_ready($pdo)) {
        foreach ($events as &$event) {
            $event['calendar_source_id'] = 0;
            $event['calendar_source_name'] = CALENDAR_SOURCE_DEFAULT_NAME;
            $event['calendar_source_color'] = 'blue';
        }
        unset($event);
        return ['events' => $events, 'sources' => $sources];
    }

    $sourceMap = [];
    foreach ($sources as $source) {
        $sourceMap[$source['source_id']] = $source;
    }
    $default = null;
    foreach ($sources as $source) {
        if ($source['is_default']) {
            $default = $source;
            break;
        }
    }
    $default ??= calendar_source_ensure_default($pdo, $ownerId);

    $eventIds = [];
    foreach ($events as $event) {
        $id = app_validate_positive_int($event['event_id'] ?? null);
        if ($id !== null) {
            $eventIds[$id] = true;
        }
    }
    $eventSourceMap = [];
    if ($eventIds !== []) {
        $ids = array_keys($eventIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'SELECT calendar_event_id, calendar_event_source_id FROM ' . db_table_identifier('calendar_event')
            . ' WHERE calendar_event_owner = ? AND calendar_event_flag = 0 AND calendar_event_id IN (' . $placeholders . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$ownerId], $ids));
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $eventId = app_validate_positive_int($row['calendar_event_id'] ?? null);
            $sourceId = app_validate_positive_int($row['calendar_event_source_id'] ?? null);
            if ($eventId !== null) {
                $eventSourceMap[$eventId] = $sourceId ?? $default['source_id'];
            }
        }
    }

    foreach ($events as &$event) {
        $eventId = app_validate_positive_int($event['event_id'] ?? null);
        $sourceId = $eventId === null ? $default['source_id'] : ($eventSourceMap[$eventId] ?? $default['source_id']);
        $source = $sourceMap[$sourceId] ?? $default;
        $event['calendar_source_id'] = $source['source_id'];
        $event['calendar_source_name'] = $source['name'];
        $event['calendar_source_color'] = $source['color'];
    }
    unset($event);

    return ['events' => $events, 'sources' => $sources];
}
