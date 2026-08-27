<?php

declare(strict_types=1);

final class V124dFakePDO extends PDO
{
    /** @var array<int,array<string,int>> */
    public array $stocks = [];

    public function __construct() {}

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new V124dFakeStatement($this, $query);
    }

    public function inTransaction(): bool
    {
        return false;
    }
}

final class V124dFakeStatement extends PDOStatement
{
    /** @var list<array<string,int>> */
    private array $rows = [];

    public function __construct(private V124dFakePDO $pdo, private string $sql) {}

    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $this->rows = [];

        if (!str_starts_with($this->sql, 'SELECT stock_id, stock_processed, stock_important, stock_archived FROM ig_content_stock ')) {
            throw new RuntimeException('Unexpected SQL in V1.24-D fake: ' . $this->sql);
        }

        $owner = (int) ($params[':owner'] ?? 0);
        $ids = [];
        foreach ($params as $key => $value) {
            if (is_string($key) && str_starts_with($key, ':stock_id_')) {
                $ids[] = (int) $value;
            }
        }

        foreach ($ids as $id) {
            $row = $this->pdo->stocks[$id] ?? null;
            if (!is_array($row)
                || (int) $row['stock_owner'] !== $owner
                || (int) $row['stock_flag'] !== 0) {
                continue;
            }
            $this->rows[] = [
                'stock_id' => $id,
                'stock_processed' => (int) $row['stock_processed'],
                'stock_important' => (int) $row['stock_important'],
                'stock_archived' => (int) $row['stock_archived'],
            ];
        }
        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        if ($mode !== PDO::FETCH_ASSOC) {
            throw new RuntimeException('V1.24-D fake expects FETCH_ASSOC.');
        }
        return $this->rows;
    }
}

$GLOBALS['test_pdo'] = new V124dFakePDO();

function conn_db(): PDO
{
    return $GLOBALS['test_pdo'];
}

function db_table_name(string $name): string
{
    if ($name !== 'content_stock') {
        throw new InvalidArgumentException('Unexpected table.');
    }
    return 'ig_content_stock';
}

function app_validate_positive_int(mixed $value): ?int
{
    if (is_int($value)) {
        return $value > 0 ? $value : null;
    }
    if (!is_string($value) || preg_match('/^[1-9][0-9]*$/', $value) !== 1) {
        return null;
    }
    $number = (int) $value;
    return $number > 0 ? $number : null;
}

function api_success(array $data = [], int $status = 200): array
{
    return ['status' => $status, 'body' => ['ok' => true, 'data' => $data]];
}

function api_error(string $code, string $message, int $status): array
{
    return ['status' => $status, 'body' => ['ok' => false, 'error' => ['code' => $code, 'message' => $message]]];
}

function api_validation_error(string $message): array
{
    return api_error('validation_error', $message, 422);
}

function api_string(array $input, string $key, string $default = ''): string
{
    $value = $input[$key] ?? $default;
    return is_string($value) ? $value : $default;
}

function api_positive_int(array $input, string $key): ?int
{
    return app_validate_positive_int($input[$key] ?? null);
}

require dirname(__DIR__) . '/app/stock_state.php';

$pdo = $GLOBALS['test_pdo'];
$pdo->stocks = [
    1 => ['stock_owner' => 10, 'stock_flag' => 0, 'stock_processed' => 0, 'stock_important' => 0, 'stock_archived' => 0],
    2 => ['stock_owner' => 10, 'stock_flag' => 0, 'stock_processed' => 1, 'stock_important' => 0, 'stock_archived' => 0],
    3 => ['stock_owner' => 10, 'stock_flag' => 0, 'stock_processed' => 0, 'stock_important' => 1, 'stock_archived' => 1],
    4 => ['stock_owner' => 20, 'stock_flag' => 0, 'stock_processed' => 1, 'stock_important' => 1, 'stock_archived' => 0],
    5 => ['stock_owner' => 10, 'stock_flag' => 1, 'stock_processed' => 1, 'stock_important' => 1, 'stock_archived' => 1],
];

$tests = 0;
$failures = [];
function v124d_check(bool $condition, string $message): void
{
    global $tests, $failures;
    $tests++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) {
        $failures[] = $message;
    }
}

$r = stock_state_api_dispatch('stock.state.list', 10, ['stock_ids' => ['1', '2', '3']]);
$stocks = $r['body']['data']['stocks'] ?? [];
v124d_check($r['status'] === 200 && ($r['body']['ok'] ?? false) === true, 'Stock state list is dispatched');
v124d_check(count($stocks) === 3, 'list returns all requested owned active Stock rows');
v124d_check(array_column($stocks, 'stock_id') === [1, 2, 3], 'list preserves request order');
v124d_check(($stocks[0]['processed'] ?? -1) === 0 && ($stocks[0]['important'] ?? -1) === 0 && ($stocks[0]['archived'] ?? -1) === 0, 'default Stock states are returned as zero');
v124d_check(($stocks[1]['processed'] ?? 0) === 1, 'processed state is returned');
v124d_check(($stocks[2]['important'] ?? 0) === 1, 'important state is returned');
v124d_check(($stocks[2]['archived'] ?? 0) === 1, 'archived state is returned so D can restore it');

$r = api_stock_state_list(10, ['stock_ids' => ['1', '4']]);
v124d_check($r['status'] === 404 && ($r['body']['error']['code'] ?? '') === 'not_found', 'mixed-owner list request is rejected without identifying foreign Stock');
$r = api_stock_state_list(10, ['stock_ids' => ['1', '5']]);
v124d_check($r['status'] === 404, 'Stock解除 row is not readable through state API');
$r = api_stock_state_list(10, ['stock_ids' => ['1', '999']]);
v124d_check($r['status'] === 404, 'missing Stock ID is indistinguishable from other unavailable IDs');

$r = api_stock_state_list(10, ['stock_ids' => []]);
v124d_check($r['status'] === 422, 'empty state list is rejected');
$r = api_stock_state_list(10, ['stock_ids' => ['0']]);
v124d_check($r['status'] === 422, 'non-positive Stock ID is rejected');
$r = api_stock_state_list(10, ['stock_ids' => array_fill(0, 101, '1')]);
v124d_check($r['status'] === 422, 'state list keeps the raw 100-entry cap');

$r = api_stock_state_list(10, ['stock_ids' => ['2', '2', '3']]);
$stocks = $r['body']['data']['stocks'] ?? [];
v124d_check($r['status'] === 200 && array_column($stocks, 'stock_id') === [2, 3], 'duplicate visible IDs are deduplicated');

$r = stock_state_api_dispatch('stock.state.nope', 10, ['stock_ids' => ['1']]);
v124d_check($r['status'] === 400 && ($r['body']['error']['code'] ?? '') === 'unknown_action', 'unknown state action remains rejected');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . "/{$tests} V1.24-D Stock state UI checks failed.\n");
    exit(1);
}

echo "All {$tests} V1.24-D Stock state UI checks passed.\n";
