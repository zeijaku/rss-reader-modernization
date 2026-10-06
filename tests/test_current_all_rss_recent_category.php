<?php

declare(strict_types=1);

final class V145BStatement
{
    public array $params = [];
    public function __construct(public string $sql, private array $rows) {}
    public function execute(array $params = []): bool { $this->params = $params; return true; }
    public function fetchAll(): array { return $this->rows; }
    public function fetch(): mixed { return false; }
}

final class V145BPdo
{
    public array $statements = [];
    public function __construct(private array $rows) {}
    public function prepare(string $sql): V145BStatement
    {
        $stmt = new V145BStatement($sql, $this->rows);
        $this->statements[] = $stmt;
        return $stmt;
    }
}

$rows = [
    ['content_id'=>1,'feed_url'=>'https://example.test/a.xml','content_location'=>0,'content_style'=>'success','feed_title'=>'A','site_url'=>'','category_path'=>'技術'],
    ['content_id'=>2,'feed_url'=>'https://example.test/b.xml','content_location'=>0,'content_style'=>'success','feed_title'=>'B','site_url'=>'','category_path'=>''],
    ['content_id'=>3,'feed_url'=>'https://example.test/c.xml','content_location'=>0,'content_style'=>'success','feed_title'=>'C','site_url'=>'','category_path'=>'all'],
    ['content_id'=>4,'feed_url'=>'not-a-url','content_location'=>0,'content_style'=>'success','feed_title'=>'D','site_url'=>'','category_path'=>'技術'],
];
$pdo = new V145BPdo($rows);

function conn_db(): V145BPdo { global $pdo; return $pdo; }
function db_table_identifier(string $name): string { return 'rss_' . $name; }
function app_validate_positive_int(mixed $value): ?int
{
    if (is_int($value)) return $value > 0 ? $value : null;
    return is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1 ? (int) $value : null;
}
function app_validate_text(mixed $value, int $maxLength, bool $allowEmpty = true): ?string
{
    if (!is_string($value) || preg_match('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F]/', $value)) return null;
    $value = trim($value);
    if (!$allowEmpty && $value === '') return null;
    return strlen($value) <= $maxLength ? $value : null;
}
function app_validate_feed_url(mixed $value): ?string
{
    if (!is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) return null;
    return preg_match('/^https?:\\/\\//i', $value) === 1 ? $value : null;
}
function app_validate_external_link(mixed $value, int $maxLength = 2048): ?string
{
    return is_string($value) && strlen($value) <= $maxLength ? $value : null;
}
function app_now(): string { return '2026-10-06 13:15:00'; }
function dashboard_widget_decode_config(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || $value === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

require_once dirname(__DIR__) . '/app/all_rss_recent.php';

$pass = 0;
$fail = 0;
function v145b_check(bool $condition, string $message): void
{
    global $pass, $fail;
    if ($condition) { $pass++; echo "PASS: {$message}\n"; }
    else { $fail++; echo "FAIL: {$message}\n"; }
}

v145b_check(all_rss_recent_validate_category_filter('all') === 'all', 'All token is accepted');
v145b_check(all_rss_recent_validate_category_filter('uncategorized') === 'uncategorized', 'Uncategorized token is accepted');
v145b_check(all_rss_recent_validate_category_filter('category:技術') === 'category:技術', 'Named Category token is accepted');
v145b_check(all_rss_recent_validate_category_filter('category:all') === 'category:all', 'A real Category named all does not collide with All');
v145b_check(all_rss_recent_validate_category_filter('category:') === null, 'Blank named Category is rejected');
v145b_check(all_rss_recent_validate_category_filter('other:技術') === null, 'Unknown Category token is rejected');
v145b_check(all_rss_recent_validate_category_filter('category:' . str_repeat('x', 513)) === null, 'Overlong Category is rejected');

v145b_check(all_rss_recent_category_path_from_filter('all') === null, 'All maps to null path');
v145b_check(all_rss_recent_category_path_from_filter('uncategorized') === '', 'Uncategorized maps to empty path');
v145b_check(all_rss_recent_category_path_from_filter('category:技術') === '技術', 'Named filter maps to exact path');
v145b_check(all_rss_recent_category_filter_value(null) === 'all', 'Null path maps back to All');
v145b_check(all_rss_recent_category_filter_value('') === 'uncategorized', 'Empty path maps back to Uncategorized');
v145b_check(all_rss_recent_category_filter_value('all') === 'category:all', 'Named all path maps back without collision');

$legacyConfig = all_rss_recent_config(10);
v145b_check(!array_key_exists('feed_category_path', $legacyConfig), 'All filter keeps legacy config shape without a new key');
v145b_check(all_rss_recent_config_from_storage($legacyConfig) === $legacyConfig, 'Legacy All RSS Recent config remains readable');

$uncategorizedConfig = all_rss_recent_config(10, '');
v145b_check(array_key_exists('feed_category_path', $uncategorizedConfig) && $uncategorizedConfig['feed_category_path'] === '', 'Uncategorized is stored as an explicit empty Category path');
v145b_check(all_rss_recent_config_from_storage($uncategorizedConfig) === $uncategorizedConfig, 'Uncategorized config round-trips');

$namedConfig = all_rss_recent_config(20, '技術 / Cloud');
v145b_check(($namedConfig['feed_category_path'] ?? null) === '技術 / Cloud', 'Hierarchical Category path is preserved');
v145b_check(all_rss_recent_config_from_storage($namedConfig) === $namedConfig, 'Named Category config round-trips');

$invalidStored = $legacyConfig;
$invalidStored['feed_category_path'] = ['bad'];
v145b_check(all_rss_recent_config_from_storage($invalidStored) === null, 'Invalid stored Category type fails closed');

$allSources = all_rss_recent_owned_sources(7, null);
v145b_check(array_column($allSources, 'source_id') === [1, 2, 3], 'All selects every valid owned active Feed row');
$uncategorizedSources = all_rss_recent_owned_sources(7, '');
v145b_check(array_column($uncategorizedSources, 'source_id') === [2], 'Uncategorized selects only blank Category path');
$techSources = all_rss_recent_owned_sources(7, '技術');
v145b_check(array_column($techSources, 'source_id') === [1], 'Named Category selects only exact matching Feed');
$allNamedSources = all_rss_recent_owned_sources(7, 'all');
v145b_check(array_column($allNamedSources, 'source_id') === [3], 'Real Category named all is independently selectable');
v145b_check(all_rss_recent_owned_sources(7, '存在しない') === [], 'Missing Category yields zero source Feeds without fallback');

$lastStatement = $pdo->statements[count($pdo->statements) - 1];
v145b_check(str_contains($lastStatement->sql, 'c.content_owner = :owner') && str_contains($lastStatement->sql, 'c.content_flag = 0'), 'Feed metadata source query is owner-scoped and active-only');
v145b_check(($lastStatement->params[':owner'] ?? null) === 7, 'Feed source query binds authenticated owner id');

printf("RESULT: %s %d / FAIL %d / SKIP 0\n", $fail === 0 ? 'PASS' : 'FAIL', $pass, $fail);
exit($fail === 0 ? 0 : 1);
