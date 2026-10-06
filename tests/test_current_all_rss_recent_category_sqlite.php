<?php

declare(strict_types=1);

if (!extension_loaded('pdo_sqlite')) {
    echo "RESULT: PASS 0 / FAIL 0 / SKIP 1 (pdo_sqlite unavailable)\n";
    exit(0);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function conn_db(): PDO { global $pdo; return $pdo; }
function db_table_identifier(string $name): string { return 'rss_' . $name; }
function app_validate_positive_int(mixed $value): ?int
{
    if (is_int($value)) return $value > 0 ? $value : null;
    return is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1 ? (int) $value : null;
}
function app_validate_text(mixed $value, int $maxLength, bool $allowEmpty = true): ?string
{
    if (!is_string($value)) return null;
    $value = trim($value);
    if (!$allowEmpty && $value === '') return null;
    return strlen($value) <= $maxLength ? $value : null;
}
function app_validate_feed_url(mixed $value): ?string
{
    return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false ? $value : null;
}
function app_validate_external_link(mixed $value, int $maxLength = 2048): ?string { return is_string($value) ? $value : null; }
function app_now(): string { return '2026-10-06 13:45:00'; }
function dashboard_widget_decode_config(mixed $value): array
{
    if (is_array($value)) return $value;
    $decoded = is_string($value) ? json_decode($value, true) : null;
    return is_array($decoded) ? $decoded : [];
}

require_once dirname(__DIR__) . '/app/all_rss_recent.php';

$pdo->exec(
    'CREATE TABLE rss_content ('
    . 'content_id INTEGER PRIMARY KEY, '
    . 'content_owner INTEGER NOT NULL, '
    . 'content_flag INTEGER NOT NULL DEFAULT 0, '
    . 'content_value TEXT NOT NULL, '
    . 'content_location INTEGER NOT NULL DEFAULT 0, '
    . 'content_style TEXT NOT NULL DEFAULT "success"'
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
    . "(1, 7, 0, 'https://example.test/tech.xml'), "
    . "(2, 7, 0, 'https://example.test/uncat.xml'), "
    . "(3, 7, 0, 'https://example.test/all.xml'), "
    . "(4, 8, 0, 'https://example.test/other.xml'), "
    . "(5, 7, 1, 'https://example.test/inactive.xml'), "
    . "(6, 7, 0, 'https://example.test/no-metadata.xml')"
);
$pdo->exec(
    "INSERT INTO rss_feed_metadata "
    . "(metadata_content_id, feed_title, site_url, category_path, created_at, updated_at) VALUES "
    . "(1, 'Tech', '', '技術', '2026-10-01', '2026-10-01'), "
    . "(2, 'Uncat', '', '', '2026-10-01', '2026-10-01'), "
    . "(3, 'All named', '', 'all', '2026-10-01', '2026-10-01'), "
    . "(4, 'Other owner', '', '技術', '2026-10-01', '2026-10-01'), "
    . "(5, 'Inactive', '', '技術', '2026-10-01', '2026-10-01')"
);

$pass = 0;
$fail = 0;
function v145b_sqlite_check(bool $condition, string $message): void
{
    global $pass, $fail;
    if ($condition) { $pass++; echo "PASS: {$message}\n"; }
    else { $fail++; echo "FAIL: {$message}\n"; }
}

v145b_sqlite_check(array_column(all_rss_recent_owned_sources(7, null), 'source_id') === [1,2,3,6], 'All includes every active Feed owned by the user');
v145b_sqlite_check(array_column(all_rss_recent_owned_sources(7, '技術'), 'source_id') === [1], 'Named Category includes only exact active owned match');
v145b_sqlite_check(array_column(all_rss_recent_owned_sources(7, 'all'), 'source_id') === [3], 'Real Category named all remains independently selectable');
v145b_sqlite_check(array_column(all_rss_recent_owned_sources(7, ''), 'source_id') === [2,6], 'Uncategorized includes blank metadata and Feed rows with no metadata');
v145b_sqlite_check(all_rss_recent_owned_sources(7, '存在しない') === [], 'Missing Category returns no sources rather than falling back to All');
v145b_sqlite_check(array_column(all_rss_recent_owned_sources(8, '技術'), 'source_id') === [4], 'Another owner receives only their own matching Feed');
v145b_sqlite_check(!in_array(5, array_column(all_rss_recent_owned_sources(7, null), 'source_id'), true), 'Inactive Feed is excluded from All');
v145b_sqlite_check(!in_array(4, array_column(all_rss_recent_owned_sources(7, null), 'source_id'), true), 'Other owner Feed is excluded from All');

printf("RESULT: %s %d / FAIL %d / SKIP 0\n", $fail === 0 ? 'PASS' : 'FAIL', $pass, $fail);
exit($fail === 0 ? 0 : 1);
