<?php

declare(strict_types=1);

function app_validate_text(mixed $value, int $maxLength, bool $allowEmpty): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $value = trim($value);
    if (!$allowEmpty && $value === '') {
        return null;
    }
    return mb_strlen($value) <= $maxLength ? $value : null;
}

function app_validate_positive_int(mixed $value): ?int
{
    if (is_int($value)) {
        return $value > 0 ? $value : null;
    }
    if (!is_string($value) || preg_match('/^[1-9][0-9]*$/', $value) !== 1) {
        return null;
    }
    return (int) $value;
}

function db_table_identifier(string $name): string
{
    return $name;
}

function app_now(): string
{
    return '2026-10-04 12:00:00';
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function conn_db(): PDO
{
    global $pdo;
    return $pdo;
}

function calendar_lock_owned_event(PDO $pdo, int $ownerId, int $eventId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM calendar_event WHERE calendar_event_id = :id AND calendar_event_owner = :owner AND calendar_event_flag = 0');
    $stmt->execute([':id' => $eventId, ':owner' => $ownerId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

require_once dirname(__DIR__) . '/app/calendar_source.php';

$pdo->exec(
    'CREATE TABLE calendar_source ('
    . 'calendar_source_id INTEGER PRIMARY KEY AUTOINCREMENT,'
    . 'calendar_source_date TEXT NOT NULL,'
    . 'calendar_source_updated_at TEXT NOT NULL,'
    . 'calendar_source_flag INTEGER NOT NULL DEFAULT 0,'
    . 'calendar_source_owner INTEGER NOT NULL,'
    . 'calendar_source_name TEXT NOT NULL,'
    . 'calendar_source_color TEXT NOT NULL DEFAULT "blue",'
    . 'calendar_source_default INTEGER NOT NULL DEFAULT 0,'
    . 'calendar_source_sort_order INTEGER NOT NULL DEFAULT 0)'
);
$pdo->exec(
    'CREATE TABLE calendar_event ('
    . 'calendar_event_id INTEGER PRIMARY KEY AUTOINCREMENT,'
    . 'calendar_event_owner INTEGER NOT NULL,'
    . 'calendar_event_source_id INTEGER NULL,'
    . 'calendar_event_flag INTEGER NOT NULL DEFAULT 0)'
);
$pdo->exec('INSERT INTO calendar_event (calendar_event_owner, calendar_event_source_id, calendar_event_flag) VALUES (1, NULL, 0)');
$pdo->exec('INSERT INTO calendar_event (calendar_event_owner, calendar_event_source_id, calendar_event_flag) VALUES (2, NULL, 0)');

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function assert_true(bool $value, string $message): void
{
    if (!$value) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$sources = calendar_source_list(1);
assert_same(1, count($sources), 'Owner 1 should receive one lazy default Calendar.');
assert_true($sources[0]['is_default'], 'The first Calendar must be default.');
assert_same('既定Calendar', $sources[0]['name'], 'Default Calendar name mismatch.');
$defaultId = $sources[0]['source_id'];

$workId = calendar_source_create(1, '仕事', 'green');
assert_true($workId > $defaultId, 'Created Calendar should have its own id.');
$sources = calendar_source_list(1);
assert_same(2, count($sources), 'Created Calendar should be listed.');
assert_same('仕事', $sources[1]['name'], 'Created Calendar name mismatch.');
assert_same('green', $sources[1]['color'], 'Created Calendar color mismatch.');

$assigned = calendar_source_assign_event($pdo, 1, 1, $workId);
assert_same($workId, $assigned, 'Event assignment should return assigned source id.');
assert_same($workId, (int) $pdo->query('SELECT calendar_event_source_id FROM calendar_event WHERE calendar_event_id = 1')->fetchColumn(), 'Event source was not persisted.');

$crossOwnerRejected = false;
try {
    calendar_source_assign_event($pdo, 2, 2, $workId);
} catch (InvalidArgumentException) {
    $crossOwnerRejected = true;
}
assert_true($crossOwnerRejected, 'A Calendar owned by another user must be rejected.');

assert_true(calendar_source_update(1, $workId, '業務', 'purple'), 'Calendar update should succeed.');
$updated = calendar_source_owned($pdo, 1, $workId);
assert_same('業務', $updated['name'] ?? null, 'Calendar rename failed.');
assert_same('purple', $updated['color'] ?? null, 'Calendar color update failed.');

$event = [['event_id' => 1, 'title' => '会議']];
$attached = calendar_source_attach_to_events(1, $event);
assert_same($workId, $attached['events'][0]['calendar_source_id'], 'Range metadata should carry source id.');
assert_same('業務', $attached['events'][0]['calendar_source_name'], 'Range metadata should carry source name.');
assert_same('purple', $attached['events'][0]['calendar_source_color'], 'Range metadata should carry source color.');

assert_true(calendar_source_delete(1, $workId), 'Calendar delete should succeed.');
assert_same($defaultId, (int) $pdo->query('SELECT calendar_event_source_id FROM calendar_event WHERE calendar_event_id = 1')->fetchColumn(), 'Deleting a Calendar must move active events to the default Calendar.');
assert_same(1, count(calendar_source_list(1)), 'Deleted Calendar should not remain active.');

$defaultDeleteRejected = false;
try {
    calendar_source_delete(1, $defaultId);
} catch (InvalidArgumentException) {
    $defaultDeleteRejected = true;
}
assert_true($defaultDeleteRejected, 'Default Calendar deletion must be rejected.');

$owner2 = calendar_source_list(2);
assert_same(1, count($owner2), 'Owner 2 should have an independent default Calendar.');
assert_true($owner2[0]['source_id'] !== $defaultId, 'Default Calendars must be owner-scoped.');

echo "V1.44-A Calendar source backend: OK\n";
