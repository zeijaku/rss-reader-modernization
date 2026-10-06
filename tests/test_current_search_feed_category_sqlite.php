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
function app_now(): string { return '2026-10-06 14:15:00'; }
function app_common_feed_list(): array
{
    return [
        ['name'=>'Common Tech','category'=>'技術','url'=>'https://common.test/tech.xml'],
        ['name'=>'Common News','category'=>'ニュース','url'=>'https://common.test/news.xml'],
    ];
}
function dashboard_widget_decode_config(mixed $value): array
{
    if (is_array($value)) return $value;
    $decoded = is_string($value) ? json_decode($value, true) : null;
    return is_array($decoded) ? $decoded : [];
}

require_once dirname(__DIR__) . '/app/validation.php';
require_once dirname(__DIR__) . '/app/search_feed.php';

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
    . "(1, 7, 0, 'https://owned.test/tech.xml'), "
    . "(2, 7, 0, 'https://owned.test/uncat.xml'), "
    . "(3, 7, 0, 'https://owned.test/all.xml'), "
    . "(4, 8, 0, 'https://owned.test/other.xml'), "
    . "(5, 7, 1, 'https://owned.test/inactive.xml'), "
    . "(6, 7, 0, 'https://owned.test/no-metadata.xml')"
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
function v145c_sqlite_check(bool $condition, string $message): void
{
    global $pass, $fail;
    if ($condition) { $pass++; echo "PASS: {$message}\n"; }
    else { $fail++; echo "FAIL: {$message}\n"; }
}

v145c_sqlite_check(array_column(search_feed_owned_sources(7, null), 'source_id') === [1,2,3,6], 'All includes active Feeds owned by the user');
v145c_sqlite_check(array_column(search_feed_owned_sources(7, '技術'), 'source_id') === [1], 'Named Category selects exact active owned Feed');
v145c_sqlite_check(array_column(search_feed_owned_sources(7, ''), 'source_id') === [2,6], 'Uncategorized includes blank metadata and missing metadata');
v145c_sqlite_check(array_column(search_feed_owned_sources(7, 'all'), 'source_id') === [3], 'Real Category named all is independently selectable');
v145c_sqlite_check(search_feed_owned_sources(7, '存在しない') === [], 'Missing Category returns no owned source rather than All');
v145c_sqlite_check(array_column(search_feed_owned_sources(8, '技術'), 'source_id') === [4], 'Other owner receives only their matching Feed');
v145c_sqlite_check(!in_array(5, array_column(search_feed_owned_sources(7, null), 'source_id'), true), 'Inactive Feed is excluded');
v145c_sqlite_check(!in_array(4, array_column(search_feed_owned_sources(7, null), 'source_id'), true), 'Other owner Feed is excluded');

$config = [
    'schema'=>1,
    'query'=>'PHP',
    'scope'=>'both',
    'condition'=>'or',
    'limit'=>10,
    'category'=>'ニュース',
    'owned_category_path'=>'技術',
];
$sources = search_feed_sources_for_config(7, $config);
v145c_sqlite_check(
    array_column($sources, 'url') === ['https://owned.test/tech.xml', 'https://common.test/news.xml'],
    'Both scope applies owned Category and common Category independently'
);

$config['scope'] = 'common';
$sources = search_feed_sources_for_config(7, $config);
v145c_sqlite_check(
    array_column($sources, 'url') === ['https://common.test/news.xml'],
    'Common-only scope never leaks owned Feed despite stored owned Category'
);

printf("RESULT: %s %d / FAIL %d / SKIP 0\n", $fail === 0 ? 'PASS' : 'FAIL', $pass, $fail);
exit($fail === 0 ? 0 : 1);
