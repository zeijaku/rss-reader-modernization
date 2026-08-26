<?php

declare(strict_types=1);

$GLOBALS['test_rules'] = [];
$GLOBALS['test_pdo'] = new PDO('sqlite::memory:');
$GLOBALS['test_pdo']->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$GLOBALS['test_pdo']->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$GLOBALS['test_pdo']->exec('CREATE TABLE user_info (user_id INTEGER PRIMARY KEY, user_flag INTEGER NOT NULL DEFAULT 0)');
$GLOBALS['test_pdo']->exec('CREATE TABLE content_stock (stock_id INTEGER PRIMARY KEY AUTOINCREMENT, stock_date TEXT, stock_owner INTEGER, stock_data TEXT, stock_title TEXT, stock_flag INTEGER NOT NULL DEFAULT 0)');
$GLOBALS['test_pdo']->exec('INSERT INTO user_info (user_id,user_flag) VALUES (7,0),(8,0)');

function conn_db(): PDO { return $GLOBALS['test_pdo']; }
function db_table_identifier(string $name): string { return '`' . $name . '`'; }
function app_now(): string { return '2026-08-26 20:00:00'; }
function app_validate_text(mixed $value, int $max, bool $allowEmpty = false): ?string {
    if (!is_string($value)) return null;
    $value = trim($value);
    if (!$allowEmpty && $value === '') return null;
    return strlen($value) <= $max ? $value : null;
}
function app_validate_stock_url(mixed $value): ?string {
    if (!is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) return null;
    return preg_match('/^https?:\/\//i', $value) ? $value : null;
}
function app_remove_tracking_parameters(string $url): string {
    $parts = parse_url($url);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) return $url;
    $query = [];
    if (isset($parts['query'])) parse_str($parts['query'], $query);
    foreach (array_keys($query) as $key) {
        if (str_starts_with(strtolower((string) $key), 'utm_')) unset($query[$key]);
    }
    $result = $parts['scheme'] . '://' . $parts['host'] . ($parts['path'] ?? '');
    if ($query !== []) $result .= '?' . http_build_query($query);
    return $result;
}
function feed_keyword_compare_key(string $value): string { return strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? $value)); }
function rss_rule_validate_field(mixed $value): ?string { return in_array($value, ['title','content','url','feed','category'], true) ? $value : null; }
function rss_rule_validate_operator(mixed $value): ?string { return in_array($value, ['contains','not_contains','equals','prefix'], true) ? $value : null; }
function rss_rule_validate_condition_value(mixed $value): ?string { return app_validate_text($value, 255, false); }
function rss_rule_list_owned(int $ownerId): array { return $ownerId === 7 ? $GLOBALS['test_rules'] : []; }
function feed_metadata_list_owned(int $ownerId): array {
    return $ownerId === 7 ? [['content_id'=>11,'feed_title'=>'Example Feed','category_path'=>'Technology / Security']] : [];
}
function info_dbsave(int|string|null $owner, ?string $url, ?string $title): int {
    $stmt = conn_db()->prepare('INSERT INTO content_stock (stock_date,stock_owner,stock_data,stock_title,stock_flag) VALUES (:date,:owner,:url,:title,0)');
    $stmt->execute([':date'=>app_now(),':owner'=>$owner,':url'=>$url,':title'=>$title]);
    return (int) conn_db()->lastInsertId();
}

require_once dirname(__DIR__) . '/app/rss_rule_engine.php';

$passed = 0;
function ok(bool $condition, string $message): void {
    global $passed;
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
    $passed++;
    echo "PASS: {$message}\n";
}

ok(rss_rule_match_text('PHP Security News', 'php', 'contains'), 'contains is case-insensitive literal matching');
ok(rss_rule_match_text('PHP Security News', 'Go', 'not_contains'), 'not_contains works');
ok(rss_rule_match_text('PHP', 'php', 'equals'), 'equals works');
ok(rss_rule_match_text('PHP Security', 'php', 'prefix'), 'prefix works');
ok(!rss_rule_match_text('PHP', 'php', 'regex'), 'unknown/Regex operator never matches');

$GLOBALS['test_rules'] = [
    ['rule_id'=>1,'enabled'=>true,'scope_content_id'=>null,'match_mode'=>'all','action'=>'highlight','conditions'=>[
        ['field'=>'title','operator'=>'contains','value'=>'php'],
        ['field'=>'category','operator'=>'contains','value'=>'security'],
    ]],
    ['rule_id'=>2,'enabled'=>true,'scope_content_id'=>11,'match_mode'=>'any','action'=>'hide','conditions'=>[
        ['field'=>'title','operator'=>'contains','value'=>'advertisement'],
        ['field'=>'url','operator'=>'contains','value'=>'/ads/'],
    ]],
    ['rule_id'=>3,'enabled'=>true,'scope_content_id'=>11,'match_mode'=>'all','action'=>'auto_stock','conditions'=>[
        ['field'=>'content','operator'=>'contains','value'=>'security'],
    ]],
    ['rule_id'=>4,'enabled'=>false,'scope_content_id'=>11,'match_mode'=>'all','action'=>'hide','conditions'=>[
        ['field'=>'title','operator'=>'contains','value'=>'normal'],
    ]],
    ['rule_id'=>5,'enabled'=>true,'scope_content_id'=>99,'match_mode'=>'all','action'=>'hide','conditions'=>[
        ['field'=>'title','operator'=>'contains','value'=>'normal'],
    ]],
];

$feed = [
    'channel'=>['title'=>'Example Feed','link'=>'https://feed.example.test/'],
    'item'=>[
        ['title'=>'PHP Security News','link'=>'https://example.test/article?utm_source=rss&id=1','description'=>'security update','content'=>'','is_new'=>true],
        ['title'=>'Advertisement','link'=>'https://example.test/ads/2','description'=>'ad','content'=>'','is_new'=>true],
        ['title'=>'Normal Article','link'=>'https://example.test/normal/3','description'=>'misc','content'=>'','is_new'=>false],
    ],
    'new_count'=>2,
];

$first = rss_rule_apply_to_feed(7, 11, $feed, 'https://feed.example.test/rss.xml');
ok(count($first['feed']['item']) === 2, 'Hide removes matching item from returned Feed only');
ok(($first['feed']['item'][0]['rule_highlight'] ?? false) === true, 'Highlight marks matching visible item');
ok($first['hidden'] === 1 && $first['highlighted'] === 1, 'Rule summary counts Hide and Highlight');
ok($first['auto_stocked'] === 1, 'Auto Stock saves first matching article');
ok($first['feed']['new_count'] === 1, 'Visible new_count excludes hidden articles');

$stmt = conn_db()->query('SELECT stock_owner,stock_data,stock_title FROM content_stock');
$stocks = $stmt->fetchAll();
ok(count($stocks) === 1, 'One Stock row created');
ok($stocks[0]['stock_owner'] === 7 || (int) $stocks[0]['stock_owner'] === 7, 'Auto Stock uses current owner');
ok($stocks[0]['stock_data'] === 'https://example.test/article?id=1', 'Tracking parameter removed before Auto Stock');

$second = rss_rule_apply_to_feed(7, 11, $feed, 'https://feed.example.test/rss.xml');
ok($second['auto_stocked'] === 0, 'Second fetch does not duplicate Auto Stock');
ok((int) conn_db()->query('SELECT COUNT(*) FROM content_stock')->fetchColumn() === 1, 'Stock remains deduplicated after repeated fetch');
ok(count(rss_rule_enabled_for_content(7, 11)) === 3, 'Disabled and differently-scoped Rules are excluded');
ok(rss_rule_enabled_for_content(8, 11) === [], 'Another user cannot receive owner 7 Rules');

echo "V1.22-D RSS Rule runtime: PASS ({$passed})\n";
