<?php

declare(strict_types=1);

final class V124eFakePDO extends PDO
{
    public string $lastSql = '';
    /** @var array<string,mixed> */
    public array $lastParams = [];

    public function __construct() {}

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->lastSql = $query;
        return new V124eFakeStatement($this);
    }
}

final class V124eFakeStatement extends PDOStatement
{
    public function __construct(private V124eFakePDO $pdo) {}

    public function execute(?array $params = null): bool
    {
        $this->pdo->lastParams = $params ?? [];
        return true;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return 0;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return [];
    }
}

function db_table_name(string $name): string
{
    return 'ig_' . $name;
}

function db_table_identifier(string $name): string
{
    return 'ig_' . $name;
}

require dirname(__DIR__) . '/app/common/common_db.php';

$pdo = new V124eFakePDO();
set_db_connection_for_testing($pdo);
$tests = 0;
$failures = [];

function v124e_check(bool $condition, string $message): void
{
    global $tests, $failures;
    $tests++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) {
        $failures[] = $message;
    }
}

$_GET = [];
$filters = stock_state_search_filters_from_request();
v124e_check($filters === ['processed' => 'all', 'important' => 'all', 'archive' => 'active'], 'default state filters are all/all/active');
v124e_check(stock_state_search_filter_sql($filters) === ' AND s.stock_archived = 0', 'default Stock list excludes archived rows');

$_GET = ['processed' => 'unprocessed', 'important' => 'normal', 'archive' => 'archived'];
$filters = stock_state_search_filters_from_request();
$sql = stock_state_search_filter_sql($filters);
v124e_check(str_contains($sql, 's.stock_processed = 0'), 'unprocessed filter maps to fixed zero predicate');
v124e_check(str_contains($sql, 's.stock_important = 0'), 'normal filter maps to fixed zero predicate');
v124e_check(str_contains($sql, 's.stock_archived = 1'), 'archive view maps to fixed archived predicate');

$_GET = ['processed' => 'processed', 'important' => 'important', 'archive' => 'all'];
$sql = stock_state_search_filter_sql(stock_state_search_filters_from_request());
v124e_check(str_contains($sql, 's.stock_processed = 1'), 'processed filter maps to fixed one predicate');
v124e_check(str_contains($sql, 's.stock_important = 1'), 'important filter maps to fixed one predicate');
v124e_check(!str_contains($sql, 'stock_archived'), 'archive=all adds no archive predicate');

$malicious = "all OR 1=1 --";
$_GET = ['processed' => $malicious, 'important' => $malicious, 'archive' => $malicious];
$filters = stock_state_search_filters_from_request();
$sql = stock_state_search_filter_sql($filters);
v124e_check($filters === ['processed' => 'all', 'important' => 'all', 'archive' => 'active'], 'invalid filter values fall back to safe fixed values');
v124e_check(!str_contains($sql, $malicious) && $sql === ' AND s.stock_archived = 0', 'request filter text is never interpolated into SQL');

$_GET = ['processed' => 'processed', 'important' => 'important', 'archive' => 'active'];
count_stock(10, 'needle', 7);
v124e_check(str_contains($pdo->lastSql, 'WHERE s.stock_flag = 0 AND s.stock_owner = :owner'), 'count query remains active Stock and owner scoped');
v124e_check(str_contains($pdo->lastSql, 's.stock_processed = 1') && str_contains($pdo->lastSql, 's.stock_important = 1') && str_contains($pdo->lastSql, 's.stock_archived = 0'), 'count query applies all state predicates');
v124e_check(str_contains($pdo->lastSql, 'stock_tag_map') && str_contains($pdo->lastSql, 'map_tag_id = :filter_tag_id'), 'count query keeps search/Tag filters alongside state filters');
v124e_check(($pdo->lastParams[':owner'] ?? null) === 10 && ($pdo->lastParams[':filter_tag_id'] ?? null) === 7, 'count query keeps owner and Tag parameters');

search_stock(10, 'needle', 'oldest', 20, 20, 7);
v124e_check(str_contains($pdo->lastSql, 's.stock_processed = 1') && str_contains($pdo->lastSql, 's.stock_archived = 0'), 'list query applies state predicates');
v124e_check(str_contains($pdo->lastSql, 'ORDER BY stock_id ASC LIMIT 20 OFFSET 20'), 'list query keeps allowlisted sort and pagination');

search_stock(10, '', 'stock_id DESC; DROP TABLE x', 20, 0, null);
v124e_check(str_contains($pdo->lastSql, 'ORDER BY stock_id DESC LIMIT 20 OFFSET 0'), 'invalid sort still falls back to fixed newest order');
v124e_check(!str_contains($pdo->lastSql, 'DROP TABLE'), 'sort request is never interpolated into SQL');

$_GET = ['archive' => 'archived'];
search_stock(10);
v124e_check(str_contains($pdo->lastSql, 's.stock_archived = 1'), 'Archive view is applied to list query');
$_GET = ['archive' => 'all'];
search_stock(10);
v124e_check(!str_contains($pdo->lastSql, 'stock_archived'), 'Archive all view can intentionally include both states');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . "/{$tests} V1.24-E filter checks failed.\n");
    exit(1);
}

echo "All {$tests} V1.24-E filter checks passed.\n";
