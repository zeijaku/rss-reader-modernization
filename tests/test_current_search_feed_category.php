<?php

declare(strict_types=1);

final class V145CStatement
{
    public array $params = [];
    public function __construct(public string $sql, private array $rows) {}
    public function execute(array $params = []): bool { $this->params = $params; return true; }
    public function fetchAll(): array { return $this->rows; }
    public function fetch(): mixed { return false; }
}

final class V145CPdo
{
    public array $statements = [];
    public function __construct(private array $rows) {}
    public function prepare(string $sql): V145CStatement
    {
        $stmt = new V145CStatement($sql, $this->rows);
        $this->statements[] = $stmt;
        return $stmt;
    }
}

$ownedRows = [
    ['content_id'=>1,'feed_url'=>'https://owned.test/tech.xml','content_location'=>0,'content_style'=>'success','feed_title'=>'Tech','site_url'=>'','category_path'=>'技術'],
    ['content_id'=>2,'feed_url'=>'https://owned.test/uncat.xml','content_location'=>0,'content_style'=>'success','feed_title'=>'Uncat','site_url'=>'','category_path'=>''],
    ['content_id'=>3,'feed_url'=>'https://owned.test/all.xml','content_location'=>0,'content_style'=>'success','feed_title'=>'All named','site_url'=>'','category_path'=>'all'],
    ['content_id'=>4,'feed_url'=>'not-a-url','content_location'=>0,'content_style'=>'success','feed_title'=>'Bad','site_url'=>'','category_path'=>'技術'],
];
$pdo = new V145CPdo($ownedRows);

function conn_db(): V145CPdo { global $pdo; return $pdo; }
function db_table_identifier(string $name): string { return 'rss_' . $name; }
function app_now(): string { return '2026-10-06 14:00:00'; }
function app_common_feed_list(): array
{
    return [
        ['name'=>'Common Tech','category'=>'技術','url'=>'https://common.test/tech.xml'],
        ['name'=>'Common News','category'=>'ニュース','url'=>'https://common.test/news.xml'],
        ['name'=>'Discovery','category'=>'技術','url'=>'https://common.test/discovery.xml','discovery'=>true],
    ];
}
function dashboard_widget_decode_config(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || $value === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

require_once dirname(__DIR__) . '/app/validation.php';
require_once dirname(__DIR__) . '/app/search_feed.php';

$pass = 0;
$fail = 0;
function v145c_check(bool $condition, string $message): void
{
    global $pass, $fail;
    if ($condition) { $pass++; echo "PASS: {$message}\n"; }
    else { $fail++; echo "FAIL: {$message}\n"; }
}

v145c_check(search_feed_validate_owned_category_filter('all') === 'all', 'Owned All token is accepted');
v145c_check(search_feed_validate_owned_category_filter('uncategorized') === 'uncategorized', 'Owned Uncategorized token is accepted');
v145c_check(search_feed_validate_owned_category_filter('category:技術') === 'category:技術', 'Owned named Category token is accepted');
v145c_check(search_feed_validate_owned_category_filter('category:all') === 'category:all', 'Real owned Category named all does not collide');
v145c_check(search_feed_validate_owned_category_filter('category:') === null, 'Blank owned named Category is rejected');
v145c_check(search_feed_validate_owned_category_filter('common:技術') === null, 'Unknown owned Category token is rejected');
v145c_check(search_feed_owned_category_path_from_filter('all') === null, 'Owned All maps to null path');
v145c_check(search_feed_owned_category_path_from_filter('uncategorized') === '', 'Owned Uncategorized maps to empty path');
v145c_check(search_feed_owned_category_path_from_filter('category:技術 / Cloud') === '技術 / Cloud', 'Owned hierarchical Category maps exactly');
v145c_check(search_feed_owned_category_filter_value('all') === 'category:all', 'Stored real all Category maps without collision');

$legacyInput = [
    'search_query'=>'PHP',
    'search_scope'=>'owned',
    'search_condition'=>'or',
    'search_limit'=>'10',
    'search_category'=>'all',
];
$legacyConfig = search_feed_config_from_input($legacyInput);
v145c_check(is_array($legacyConfig) && !array_key_exists('owned_category_path', $legacyConfig), 'Legacy Search Feed input defaults owned Category to All without a new key');

$uncatConfig = search_feed_config_from_input($legacyInput + ['search_owned_category_filter'=>'uncategorized']);
v145c_check(is_array($uncatConfig) && array_key_exists('owned_category_path', $uncatConfig) && $uncatConfig['owned_category_path'] === '', 'Uncategorized is stored as explicit empty owned Category path');

$namedConfig = search_feed_config_from_input($legacyInput + ['search_owned_category_filter'=>'category:技術']);
v145c_check(is_array($namedConfig) && ($namedConfig['owned_category_path'] ?? null) === '技術', 'Named owned Category is stored exactly');

$realAllConfig = search_feed_config_from_input($legacyInput + ['search_owned_category_filter'=>'category:all']);
v145c_check(is_array($realAllConfig) && ($realAllConfig['owned_category_path'] ?? null) === 'all', 'Real owned Category named all is stored exactly');

v145c_check(search_feed_config_from_input($legacyInput + ['search_owned_category_filter'=>'bad']) === null, 'Invalid owned Category token rejects settings');

$legacyStored = json_encode($legacyConfig, JSON_UNESCAPED_UNICODE);
v145c_check(search_feed_config_from_storage($legacyStored) === $legacyConfig, 'Legacy stored Search Feed config remains unchanged');
$namedStored = search_feed_config_from_storage(json_encode($namedConfig, JSON_UNESCAPED_UNICODE));
v145c_check(($namedStored['owned_category_path'] ?? null) === '技術', 'Stored owned Category round-trips');

$badStored = $legacyConfig;
$badStored['owned_category_path'] = ['bad'];
$normalizedBadStored = search_feed_config_from_storage(json_encode($badStored));
v145c_check(!array_key_exists('owned_category_path', $normalizedBadStored), 'Malformed stored owned Category falls back to All using existing tolerant config behavior');

v145c_check(array_column(search_feed_owned_sources(7, null), 'source_id') === [1,2,3], 'Owned All selects every valid Feed row');
v145c_check(array_column(search_feed_owned_sources(7, ''), 'source_id') === [2], 'Owned Uncategorized selects only blank Category');
v145c_check(array_column(search_feed_owned_sources(7, '技術'), 'source_id') === [1], 'Owned named Category selects exact matching Feed');
v145c_check(array_column(search_feed_owned_sources(7, 'all'), 'source_id') === [3], 'Owned real all Category remains independently selectable');
v145c_check(search_feed_owned_sources(7, '存在しない') === [], 'Missing owned Category returns zero sources without fallback');

$lastStatement = $pdo->statements[count($pdo->statements) - 1];
v145c_check(str_contains($lastStatement->sql, 'c.content_owner = :owner') && str_contains($lastStatement->sql, 'c.content_flag = 0'), 'Owned Feed source query remains owner-scoped and active-only');
v145c_check(($lastStatement->params[':owner'] ?? null) === 7, 'Owned Feed source query binds authenticated owner');

$ownedOnly = $namedConfig;
$ownedOnly['scope'] = 'owned';
$ownedOnly['category'] = 'ニュース';
$ownedOnlySources = search_feed_sources_for_config(7, $ownedOnly);
v145c_check(array_column($ownedOnlySources, 'source_id') === [1], 'Owned scope applies only owned Feed Category and ignores common Category');

$commonOnly = $namedConfig;
$commonOnly['scope'] = 'common';
$commonOnly['category'] = 'ニュース';
$commonOnlySources = search_feed_sources_for_config(7, $commonOnly);
v145c_check(count($commonOnlySources) === 1 && $commonOnlySources[0]['url'] === 'https://common.test/news.xml', 'Common scope ignores owned Category and keeps existing common Category behavior');

$both = $namedConfig;
$both['scope'] = 'both';
$both['category'] = 'ニュース';
$bothSources = search_feed_sources_for_config(7, $both);
v145c_check(
    array_column($bothSources, 'url') === ['https://owned.test/tech.xml', 'https://common.test/news.xml'],
    'Both scope combines independently filtered owned and common sources'
);

printf("RESULT: %s %d / FAIL %d / SKIP 0\n", $fail === 0 ? 'PASS' : 'FAIL', $pass, $fail);
exit($fail === 0 ? 0 : 1);
