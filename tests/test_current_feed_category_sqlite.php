<?php

declare(strict_types=1);

if (!extension_loaded('pdo_sqlite')) {
    echo "RESULT: PASS 0 / FAIL 0 / SKIP 1 (pdo_sqlite unavailable)\n";
    exit(0);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function conn_db(): PDO
{
    global $pdo;
    return $pdo;
}

function db_table_identifier(string $name): string
{
    return 'rss_' . $name;
}

function app_now(): string
{
    return '2026-10-06 11:30:00';
}

function app_validate_text(mixed $value, int $maxLength, bool $allowEmpty = true): ?string
{
    if (!is_string($value) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
        return null;
    }
    $value = trim($value);
    if (!$allowEmpty && $value === '') {
        return null;
    }
    return strlen($value) <= $maxLength ? $value : null;
}

function app_validate_external_link(mixed $value, int $maxLength = 2048): ?string
{
    return is_string($value) ? $value : null;
}

function app_validate_feed_url(mixed $value): ?string
{
    return is_string($value) ? $value : null;
}

require_once dirname(__DIR__) . '/app/feed_metadata.php';

$pdo->exec(
    'CREATE TABLE rss_content ('
    . 'content_id INTEGER PRIMARY KEY, '
    . 'content_owner INTEGER NOT NULL, '
    . 'content_flag INTEGER NOT NULL DEFAULT 0, '
    . 'content_value TEXT NOT NULL DEFAULT "", '
    . 'content_location INTEGER NOT NULL DEFAULT 0, '
    . 'content_style TEXT NOT NULL DEFAULT ""'
    . ')'
);
$pdo->exec(
    'CREATE TABLE rss_feed_metadata ('
    . 'metadata_content_id INTEGER PRIMARY KEY, '
    . 'feed_title TEXT NOT NULL DEFAULT "", '
    . 'site_url TEXT NOT NULL DEFAULT "", '
    . 'category_path TEXT NOT NULL DEFAULT "", '
    . 'created_at TEXT NOT NULL, '
    . 'updated_at TEXT NOT NULL'
    . ')'
);

$pdo->exec(
    "INSERT INTO rss_content (content_id, content_owner, content_flag, content_value) VALUES "
    . "(1, 7, 0, 'https://example.test/a.xml'), "
    . "(2, 7, 0, 'https://example.test/b.xml'), "
    . "(3, 8, 0, 'https://example.test/c.xml'), "
    . "(4, 7, 1, 'https://example.test/d.xml'), "
    . "(5, 7, 0, 'https://example.test/e.xml')"
);
$pdo->exec(
    "INSERT INTO rss_feed_metadata "
    . "(metadata_content_id, feed_title, site_url, category_path, created_at, updated_at) VALUES "
    . "(1, 'Owner A', 'https://example.test/a', 'Cloud', '2026-10-01', '2026-10-01'), "
    . "(2, 'Owner B', 'https://example.test/b', 'News', '2026-10-01', '2026-10-01'), "
    . "(3, 'Other', 'https://example.test/c', 'Cloud', '2026-10-01', '2026-10-01'), "
    . "(4, 'Inactive', 'https://example.test/d', 'Cloud', '2026-10-01', '2026-10-01')"
);

$pass = 0;
$fail = 0;
function v145a_sqlite_check(bool $condition, string $message): void
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "PASS: {$message}\n";
    } else {
        $fail++;
        echo "FAIL: {$message}\n";
    }
}

v145a_sqlite_check(feed_metadata_set_category_owned(7, 2, 'Cloud'), 'SQLite updates an owned existing Feed Category');
$row = $pdo->query('SELECT feed_title, site_url, category_path FROM rss_feed_metadata WHERE metadata_content_id = 2')->fetch();
v145a_sqlite_check(
    $row['feed_title'] === 'Owner B' && $row['site_url'] === 'https://example.test/b' && $row['category_path'] === 'Cloud',
    'SQLite Category update preserves existing Feed title and Site URL'
);

v145a_sqlite_check(feed_metadata_set_category_owned(7, 5, 'New') === true, 'SQLite inserts metadata for an owned Feed without metadata');
$row = $pdo->query('SELECT category_path FROM rss_feed_metadata WHERE metadata_content_id = 5')->fetch();
v145a_sqlite_check(is_array($row) && $row['category_path'] === 'New', 'Inserted metadata stores the requested Category');

v145a_sqlite_check(feed_metadata_set_category_owned(7, 3, 'Forbidden') === false, 'SQLite rejects another owner Feed');
$row = $pdo->query('SELECT category_path FROM rss_feed_metadata WHERE metadata_content_id = 3')->fetch();
v145a_sqlite_check($row['category_path'] === 'Cloud', 'Another owner Category remains unchanged');

$changed = feed_metadata_rename_category_owned(7, 'Cloud', 'Platform');
v145a_sqlite_check($changed === 2, 'SQLite rename changes only two active owned Feeds');
v145a_sqlite_check(
    (string) $pdo->query('SELECT category_path FROM rss_feed_metadata WHERE metadata_content_id = 3')->fetchColumn() === 'Cloud',
    'SQLite rename does not cross owner boundary'
);
v145a_sqlite_check(
    (string) $pdo->query('SELECT category_path FROM rss_feed_metadata WHERE metadata_content_id = 4')->fetchColumn() === 'Cloud',
    'SQLite rename ignores inactive Feed'
);

$categories = feed_metadata_category_paths_owned(7);
v145a_sqlite_check($categories === ['New', 'Platform'], 'SQLite owned Category list contains active assigned Categories only');

$deleted = feed_metadata_delete_category_owned(7, 'Platform');
v145a_sqlite_check($deleted === 2, 'SQLite Category delete reassigns active owned Feeds');
v145a_sqlite_check(
    (int) $pdo->query("SELECT COUNT(*) FROM rss_content WHERE content_id IN (1,2) AND content_owner = 7 AND content_flag = 0")->fetchColumn() === 2,
    'SQLite Category delete keeps Feed rows'
);
v145a_sqlite_check(
    (int) $pdo->query("SELECT COUNT(*) FROM rss_feed_metadata WHERE metadata_content_id IN (1,2) AND category_path = ''")->fetchColumn() === 2,
    'SQLite Category delete maps matching Feeds to uncategorized'
);

printf("RESULT: %s %d / FAIL %d / SKIP 0\n", $fail === 0 ? 'PASS' : 'FAIL', $pass, $fail);
exit($fail === 0 ? 0 : 1);
