<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/calendar_day_mark.php';

function check_day_mark(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
set_db_connection_for_testing($pdo);
check_day_mark(calendar_day_mark_list(7, '2026-12-01', '2026-12-31') === null, 'Migration is optional for range reads.');

$table = db_table_identifier('calendar_day_mark');
$pdo->exec("CREATE TABLE $table (mark_owner INTEGER, mark_date TEXT, mark_label TEXT, mark_note TEXT, PRIMARY KEY (mark_owner, mark_date))");

calendar_day_mark_save(7, '2026-11-30', '病院', '午前');
calendar_day_mark_save(7, '2027-01-02', 'ドライブ', '');
calendar_day_mark_save(7, '2027-01-03', '休み', '');
calendar_day_mark_save(8, '2026-11-30', '副業', '');
check_day_mark(calendar_day_mark_list(7, '2026-11-29', '2027-01-02') === [
    '2026-11-30' => ['label' => '病院', 'note' => '午前'],
    '2027-01-02' => ['label' => 'ドライブ', 'note' => ''],
], 'Owner, range, and adjacent month filtering.');

calendar_day_mark_save(7, '2026-11-30', '制作', '午後');
check_day_mark(calendar_day_mark_list(7, '2026-11-30', '2026-11-30')['2026-11-30'] === ['label' => '制作', 'note' => '午後'], 'Upsert updates a single mark.');
foreach ([
    ['2026-02-30', '病院', ''],
    ['2026-11-30', str_repeat('仮', 13), ''],
    ['2026-11-30', ' ', ''],
    ['2026-11-30', '病院', str_repeat('予', 121)],
] as [$date, $label, $note]) {
    try {
        calendar_day_mark_save(7, $date, $label, $note);
        throw new RuntimeException('Invalid mark was accepted.');
    } catch (InvalidArgumentException) {
        // Expected validation error.
    }
}

calendar_day_mark_save(7, '2026-11-30', '', '');
calendar_day_mark_save(7, '2026-11-30', '', '');
check_day_mark(calendar_day_mark_list(7, '2026-11-30', '2026-11-30') === [], 'Deleting twice is safe.');
check_day_mark(calendar_day_mark_list(8, '2026-11-30', '2026-11-30')['2026-11-30']['label'] === '副業', 'Deleting leaves other owners intact.');

echo "PASS: day mark persistence, range, validation, owner isolation, and idempotent delete\n";
