<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('DB_DRIVER=sqlite');
putenv('DB_SQLITE_PATH=:memory:');
putenv('APP_LOG_ENABLED=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
require $root . '/app/common/common_conf.php';
require $root . '/app/common/common_db.php';

$tests = 0;
$failures = [];
function v18b_check(bool $condition, string $message): void
{
    global $tests, $failures;
    $tests++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) {
        $failures[] = $message;
    }
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "SKIP: PDO SQLite is unavailable.\n";
    exit(0);
}

$pdo = conn_db('sqlite');
$pdo->exec(<<<'SQL'
CREATE TABLE ig_content_stock (
 stock_id INTEGER PRIMARY KEY AUTOINCREMENT,
 stock_date TEXT NOT NULL,
 stock_flag INTEGER NOT NULL DEFAULT 0,
 stock_owner INTEGER NOT NULL,
 stock_data TEXT NOT NULL,
 stock_title TEXT NOT NULL,
 stock_processed INTEGER NOT NULL DEFAULT 0,
 stock_important INTEGER NOT NULL DEFAULT 0,
 stock_archived INTEGER NOT NULL DEFAULT 0
);
SQL);

$ownerStock = info_dbsave(10, 'https://example.test/owned', 'Owned Stock');
$otherStock = info_dbsave(20, 'https://example.test/other', 'Other Stock');

v18b_check(find_owned_active_stock(10, $ownerStock) !== null, 'owner can resolve own active Stock');
v18b_check(find_owned_active_stock(20, $ownerStock) === null, 'other user cannot resolve owner Stock');
v18b_check(delete_stock_owned(20, $ownerStock) === 0, 'other user cannot logically delete owner Stock');
v18b_check((int) $pdo->query('SELECT stock_flag FROM ig_content_stock WHERE stock_id=' . $ownerStock)->fetchColumn() === 0, 'failed foreign delete leaves Stock active');
v18b_check(delete_stock_owned(10, $ownerStock) === 1, 'owner can logically delete own Stock');
v18b_check((int) $pdo->query('SELECT stock_flag FROM ig_content_stock WHERE stock_id=' . $ownerStock)->fetchColumn() === 1, 'Stock delete changes only stock_flag');
v18b_check(find_owned_active_stock(10, $ownerStock) === null, 'inactive Stock is no longer returned by owned active lookup');
v18b_check(delete_stock_owned(10, $ownerStock) === 0, 'already inactive Stock cannot be deleted twice');
$visibleOwnerStocks = search_stock(10);
v18b_check($visibleOwnerStocks === [], 'logically deleted Stock disappears from Stock list');
$visibleOtherStocks = search_stock(20);
v18b_check(count($visibleOtherStocks) === 1 && (int) $visibleOtherStocks[0]['stock_id'] === $otherStock, 'another user Stock remains unchanged and visible only to that owner');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . "/{$tests} V1.8-B Stock DB checks failed.\n");
    exit(1);
}

echo "All {$tests} V1.8-B Stock DB checks passed.\n";
