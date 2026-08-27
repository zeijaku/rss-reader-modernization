<?php

declare(strict_types=1);

final class V124cFakePDO extends PDO
{
    /** @var array<int,array<string,int|string>> */
    public array $stocks = [];
    private bool $transaction = false;
    private array $snapshot = [];

    public function __construct() {}

    public function getAttribute(int $attribute): mixed
    {
        if ($attribute === PDO::ATTR_DRIVER_NAME) {
            return 'sqlite';
        }
        return null;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new V124cFakeStatement($this, $query);
    }

    public function beginTransaction(): bool
    {
        if ($this->transaction) {
            return false;
        }
        $this->snapshot = $this->stocks;
        $this->transaction = true;
        return true;
    }

    public function commit(): bool
    {
        if (!$this->transaction) {
            return false;
        }
        $this->snapshot = [];
        $this->transaction = false;
        return true;
    }

    public function rollBack(): bool
    {
        if (!$this->transaction) {
            return false;
        }
        $this->stocks = $this->snapshot;
        $this->snapshot = [];
        $this->transaction = false;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }
}

final class V124cFakeStatement extends PDOStatement
{
    /** @var list<int> */
    private array $columnRows = [];
    private int $affected = 0;

    public function __construct(private V124cFakePDO $pdo, private string $sql) {}

    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $this->columnRows = [];
        $this->affected = 0;

        $owner = (int) ($params[':owner'] ?? 0);
        $ids = [];
        foreach ($params as $key => $value) {
            if (is_string($key) && str_starts_with($key, ':stock_id_')) {
                $ids[] = (int) $value;
            }
        }

        if (str_starts_with($this->sql, 'SELECT stock_id FROM ig_content_stock ')) {
            foreach ($ids as $id) {
                $row = $this->pdo->stocks[$id] ?? null;
                if (is_array($row) && (int) $row['stock_owner'] === $owner && (int) $row['stock_flag'] === 0) {
                    $this->columnRows[] = $id;
                }
            }
            return true;
        }

        if (preg_match('/^UPDATE ig_content_stock SET (stock_processed|stock_important|stock_archived) = :state_value /', $this->sql, $matches) === 1) {
            $column = $matches[1];
            $value = (int) ($params[':state_value'] ?? -1);
            foreach ($ids as $id) {
                $row = $this->pdo->stocks[$id] ?? null;
                if (!is_array($row) || (int) $row['stock_owner'] !== $owner || (int) $row['stock_flag'] !== 0) {
                    continue;
                }
                if ((int) $row[$column] !== $value) {
                    $this->affected++;
                }
                $this->pdo->stocks[$id][$column] = $value;
            }
            return true;
        }

        throw new RuntimeException('Unexpected SQL in V1.24-C fake: ' . $this->sql);
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        if ($mode === PDO::FETCH_COLUMN) {
            return $this->columnRows;
        }
        return array_map(static fn(int $id): array => ['stock_id' => $id], $this->columnRows);
    }

    public function rowCount(): int
    {
        return $this->affected;
    }
}

$GLOBALS['test_pdo'] = new V124cFakePDO();

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
$seed = static function (int $id, int $owner, int $flag, string $title) use ($pdo): int {
    $pdo->stocks[$id] = [
        'stock_id' => $id,
        'stock_flag' => $flag,
        'stock_owner' => $owner,
        'stock_title' => $title,
        'stock_processed' => 0,
        'stock_important' => 0,
        'stock_archived' => 0,
    ];
    return $id;
};

$a = $seed(1, 10, 0, 'A');
$b = $seed(2, 10, 0, 'B');
$c = $seed(3, 10, 0, 'C');
$other = $seed(4, 20, 0, 'Other');
$removed = $seed(5, 10, 1, 'Removed');

$tests = 0;
$failures = [];
function v124c_check(bool $condition, string $message): void
{
    global $tests, $failures;
    $tests++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) {
        $failures[] = $message;
    }
}

function v124c_state(V124cFakePDO $pdo, int $stockId, string $column): int
{
    return (int) $pdo->stocks[$stockId][$column];
}

v124c_check(stock_state_column('processed') === 'stock_processed', 'processed uses fixed column mapping');
v124c_check(stock_state_column('important') === 'stock_important', 'important uses fixed column mapping');
v124c_check(stock_state_column('archived') === 'stock_archived', 'archived uses fixed column mapping');
v124c_check(stock_state_column('stock_flag') === null, 'stock_flag cannot be selected through state mapping');
v124c_check(stock_state_value('0') === 0 && stock_state_value(1) === 1, 'state values accept form strings and integers');
v124c_check(stock_state_value('2') === null && stock_state_value(true) === null, 'state values reject non-binary input');
v124c_check(stock_state_ids(['1', 2, '2']) === [1, 2], 'bulk IDs are positive integers and deduplicated');
v124c_check(stock_state_ids([]) === null, 'empty bulk list is rejected');
v124c_check(stock_state_ids(array_fill(0, 101, '1')) === null, 'raw bulk request above 100 entries is rejected');

$r = stock_state_api_dispatch('stock.state.update', 10, ['stock_id' => (string) $a, 'state' => 'processed', 'value' => '1']);
v124c_check($r['status'] === 200 && ($r['body']['ok'] ?? false) === true, 'dispatcher allows individual Stock state update');
v124c_check(v124c_state($pdo, $a, 'stock_processed') === 1, 'individual processed state is persisted');

$r = api_stock_state_update(10, ['stock_id' => (string) $a, 'state' => 'important', 'value' => '1']);
v124c_check($r['status'] === 200 && v124c_state($pdo, $a, 'stock_important') === 1, 'individual important state is independent');
v124c_check((int) $pdo->stocks[$a]['stock_flag'] === 0, 'state update never changes stock_flag');

$r = api_stock_state_update(20, ['stock_id' => (string) $a, 'state' => 'archived', 'value' => '1']);
v124c_check($r['status'] === 404, 'other owner cannot update Stock state');
v124c_check(v124c_state($pdo, $a, 'stock_archived') === 0, 'foreign update leaves Stock unchanged');

$r = api_stock_state_update(10, ['stock_id' => (string) $removed, 'state' => 'processed', 'value' => '1']);
v124c_check($r['status'] === 404, 'Stock解除 row cannot be updated');
v124c_check(v124c_state($pdo, $removed, 'stock_processed') === 0, 'Stock解除 row remains unchanged');

$r = stock_state_api_dispatch('stock.state.bulk', 10, ['stock_ids' => [(string) $b, (string) $c, (string) $b], 'state' => 'important', 'value' => '1']);
v124c_check($r['status'] === 200 && ($r['body']['data']['updated'] ?? 0) === 2, 'dispatcher bulk update deduplicates IDs and reports unique count');
v124c_check(v124c_state($pdo, $b, 'stock_important') === 1 && v124c_state($pdo, $c, 'stock_important') === 1, 'bulk important state is persisted');

$pdo->stocks[$b]['stock_processed'] = 0;
$pdo->stocks[$c]['stock_processed'] = 0;
$r = api_stock_state_bulk(10, ['stock_ids' => [(string) $b, (string) $other, (string) $c], 'state' => 'processed', 'value' => '1']);
v124c_check($r['status'] === 404, 'mixed-owner bulk request is rejected');
v124c_check(v124c_state($pdo, $b, 'stock_processed') === 0 && v124c_state($pdo, $c, 'stock_processed') === 0, 'mixed-owner failure is atomic with no partial write');

$r = api_stock_state_bulk(10, ['stock_ids' => [(string) $b, '999999', (string) $c], 'state' => 'processed', 'value' => '1']);
v124c_check($r['status'] === 404, 'bulk request with missing ID is rejected');
v124c_check(v124c_state($pdo, $b, 'stock_processed') === 0 && v124c_state($pdo, $c, 'stock_processed') === 0, 'missing-ID failure is atomic with no partial write');

$r = api_stock_state_bulk(10, ['stock_ids' => [(string) $b, (string) $removed], 'state' => 'processed', 'value' => '1']);
v124c_check($r['status'] === 404, 'bulk request with Stock解除 ID is rejected');
v124c_check(v124c_state($pdo, $b, 'stock_processed') === 0, 'Stock解除 mixed failure leaves active row unchanged');

$r = api_stock_state_bulk(10, ['stock_ids' => [(string) $b, (string) $c], 'state' => 'archived', 'value' => '1']);
v124c_check($r['status'] === 200, 'owner can bulk archive active Stock rows');
v124c_check(v124c_state($pdo, $b, 'stock_archived') === 1 && v124c_state($pdo, $c, 'stock_archived') === 1, 'archive state is stored independently');
$r = api_stock_state_bulk(10, ['stock_ids' => [(string) $b, (string) $c], 'state' => 'archived', 'value' => '0']);
v124c_check($r['status'] === 200 && v124c_state($pdo, $b, 'stock_archived') === 0, 'bulk unarchive is supported');

$r = stock_state_api_dispatch('stock.state.delete', 10, []);
v124c_check($r['status'] === 400 && ($r['body']['error']['code'] ?? '') === 'unknown_action', 'state dispatcher rejects unknown Stock action');

$r = api_stock_state_update(10, ['stock_id' => (string) $a, 'state' => 'stock_flag', 'value' => '1']);
v124c_check($r['status'] === 422, 'client cannot select stock_flag as a state column');
$r = api_stock_state_update(10, ['stock_id' => (string) $a, 'state' => 'processed', 'value' => '2']);
v124c_check($r['status'] === 422, 'non-binary state value is rejected');
$r = api_stock_state_bulk(10, ['stock_ids' => ['0'], 'state' => 'processed', 'value' => '1']);
v124c_check($r['status'] === 422, 'invalid bulk ID is rejected before DB write');
$r = api_stock_state_bulk(10, ['stock_ids' => array_fill(0, 101, (string) $a), 'state' => 'processed', 'value' => '1']);
v124c_check($r['status'] === 422, 'bulk API enforces 100-entry cap before dedupe');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . "/{$tests} V1.24-C Stock state checks failed.\n");
    exit(1);
}

echo "All {$tests} V1.24-C Stock state checks passed.\n";
