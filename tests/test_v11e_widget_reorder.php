<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql');
putenv('DB_TABLE_PREFIX=ig_');

require_once $root . '/app/common/common_conf.php';
require_once $root . '/app/common/common_db.php';
require_once $root . '/app/validation.php';
require_once $root . '/app/dashboard_widget.php';
require_once $root . '/app/api.php';

$checks = 0;
$failures = [];
function v11e_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) {
        $failures[] = $message;
    }
}

v11e_check(dashboard_widget_decode_order_list('[1,"2",3]') === [1, 2, 3], 'Widget order accepts JSON integer IDs without losing string IDs');
v11e_check(dashboard_widget_decode_order_list('[1,1]') === null, 'duplicate Widget IDs are rejected');
v11e_check(dashboard_widget_decode_order_list('[0,2]') === null, 'zero Widget ID is rejected');
v11e_check(dashboard_widget_decode_order_list('{"1":2}') === null, 'object-shaped Widget order is rejected');
v11e_check(dashboard_widget_decode_order_list('[]') === null, 'empty Widget order is rejected');
v11e_check(dashboard_widget_decode_order_list('{broken') === null, 'malformed Widget order JSON is rejected');
v11e_check(dashboard_widget_decode_order_list(json_encode(range(1, 201))) === null, 'Widget order is bounded to 200 items');

final class V11eStatement extends PDOStatement
{
    private array $rows = [];
    private mixed $column = false;
    private int $affected = 0;

    public function __construct(private V11ePDO $pdo, private string $sql) {}

    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $this->rows = [];
        $this->column = false;
        $this->affected = 0;

        if (str_starts_with($this->sql, 'SELECT widget_id FROM `ig_dashboard_widget`')) {
            $owner = (int) $params[':owner'];
            $location = (int) $params[':location'];
            $rows = array_values(array_filter($this->pdo->widgets, static fn(array $row): bool =>
                $row['widget_owner'] === $owner && $row['widget_location'] === $location && $row['widget_flag'] === 0
            ));
            usort($rows, static fn(array $a, array $b): int => [$a['widget_sort_order'], $a['widget_id']] <=> [$b['widget_sort_order'], $b['widget_id']]);
            $this->rows = array_map(static fn(array $row): array => ['widget_id' => $row['widget_id']], $rows);
            return true;
        }

        if (str_starts_with($this->sql, 'SELECT widget_sort_order FROM `ig_dashboard_widget`')) {
            $owner = (int) $params[':owner'];
            $location = (int) $params[':location'];
            $orders = [];
            foreach ($this->pdo->widgets as $row) {
                if ($row['widget_owner'] === $owner && $row['widget_location'] === $location && $row['widget_flag'] === 0) {
                    $orders[] = $row['widget_sort_order'];
                }
            }
            $this->column = $orders === [] ? false : max($orders);
            return true;
        }

        if (str_starts_with($this->sql, 'UPDATE `ig_dashboard_widget` SET widget_sort_order')) {
            $id = (int) $params[':widget_id'];
            if ($this->pdo->failWidgetId === $id) {
                throw new PDOException('fixture reorder failure');
            }
            if (isset($this->pdo->widgets[$id])
                && $this->pdo->widgets[$id]['widget_owner'] === (int) $params[':owner']
                && $this->pdo->widgets[$id]['widget_location'] === (int) $params[':location']
                && $this->pdo->widgets[$id]['widget_flag'] === 0) {
                $this->pdo->widgets[$id]['widget_sort_order'] = (int) $params[':sort_order'];
                $this->pdo->widgets[$id]['widget_updated_at'] = (string) $params[':updated_at'];
                $this->affected = 1;
            }
            return true;
        }

        throw new RuntimeException('Unhandled V1.1-E fixture SQL: ' . $this->sql);
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        if ($mode === PDO::FETCH_COLUMN) {
            return array_map(static fn(array $row): mixed => reset($row), $this->rows);
        }
        return $this->rows;
    }
    public function fetchColumn(int $column = 0): mixed { return $this->column; }
    public function rowCount(): int { return $this->affected; }
}

final class V11ePDO extends PDO
{
    public array $widgets = [];
    public ?int $failWidgetId = null;
    private bool $transaction = false;
    private array $snapshot = [];

    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new V11eStatement($this, $query); }
    public function beginTransaction(): bool { $this->snapshot = $this->widgets; $this->transaction = true; return true; }
    public function commit(): bool { $this->snapshot = []; $this->transaction = false; return true; }
    public function rollBack(): bool { $this->widgets = $this->snapshot; $this->snapshot = []; $this->transaction = false; return true; }
    public function inTransaction(): bool { return $this->transaction; }
    public function getAttribute(int $attribute): mixed { return $attribute === PDO::ATTR_DRIVER_NAME ? 'mysql' : null; }
}

$pdo = new V11ePDO();
$pdo->widgets = [
    1 => ['widget_id'=>1, 'widget_owner'=>10, 'widget_location'=>0, 'widget_sort_order'=>10, 'widget_flag'=>0, 'widget_updated_at'=>''],
    2 => ['widget_id'=>2, 'widget_owner'=>10, 'widget_location'=>0, 'widget_sort_order'=>20, 'widget_flag'=>0, 'widget_updated_at'=>''],
    3 => ['widget_id'=>3, 'widget_owner'=>10, 'widget_location'=>0, 'widget_sort_order'=>30, 'widget_flag'=>0, 'widget_updated_at'=>''],
    4 => ['widget_id'=>4, 'widget_owner'=>20, 'widget_location'=>0, 'widget_sort_order'=>10, 'widget_flag'=>0, 'widget_updated_at'=>''],
    5 => ['widget_id'=>5, 'widget_owner'=>10, 'widget_location'=>1, 'widget_sort_order'=>10, 'widget_flag'=>0, 'widget_updated_at'=>''],
];
set_db_connection_for_testing($pdo);

$result = dashboard_widget_reorder(10, 0, [1,2,3], [3,1,2]);
v11e_check(!$result['conflict'] && $result['updated'], 'owned Widgets can be reordered in one tab');
v11e_check([$pdo->widgets[3]['widget_sort_order'], $pdo->widgets[1]['widget_sort_order'], $pdo->widgets[2]['widget_sort_order']] === [10,20,30], 'saved order is normalized to stable increments');
v11e_check($pdo->widgets[4]['widget_sort_order'] === 10 && $pdo->widgets[5]['widget_sort_order'] === 10, 'another owner and another tab remain unchanged');
v11e_check(dashboard_widget_next_sort_order($pdo, 10, 0) === 40, 'new Feed Widget appends after the reordered list');

$conflict = dashboard_widget_reorder(10, 0, [1,2,3], [2,1,3]);
v11e_check($conflict['conflict'] && $conflict['widget_ids'] === [3,1,2], 'stale previous order is rejected as a conflict');
v11e_check([$pdo->widgets[3]['widget_sort_order'], $pdo->widgets[1]['widget_sort_order'], $pdo->widgets[2]['widget_sort_order']] === [10,20,30], 'conflict does not modify stored order');

try {
    dashboard_widget_reorder(10, 0, [3,1,2], [3,1,99]);
    $setRejected = false;
} catch (InvalidArgumentException) {
    $setRejected = true;
}
v11e_check($setRejected, 'replacement or foreign Widget ID is rejected before update');

$same = dashboard_widget_reorder(10, 0, [3,1,2], [3,1,2]);
v11e_check(!$same['updated'] && !$same['conflict'], 'same order remains a valid idempotent request');

$beforeFailure = $pdo->widgets;
$pdo->failWidgetId = 1;
try {
    dashboard_widget_reorder(10, 0, [3,1,2], [2,1,3]);
    $failed = false;
} catch (PDOException) {
    $failed = true;
}
$pdo->failWidgetId = null;
v11e_check($failed, 'database error during reorder is surfaced');
v11e_check($pdo->widgets === $beforeFailure, 'database error rolls back the whole Widget order');

$api = api_dispatch('widget.reorder', 10, [
    'widget_location' => '0',
    'previous_widget_ids' => '[3,1,2]',
    'widget_ids' => '[2,3,1]',
    'widget_owner' => '20',
]);
v11e_check($api['status'] === 200 && ($api['body']['data']['widget_ids'] ?? []) === [2,3,1], 'API uses authenticated owner and returns the saved order');
v11e_check($pdo->widgets[4]['widget_sort_order'] === 10, 'client-supplied owner cannot reorder another owner');

$apiConflict = api_dispatch('widget.reorder', 10, [
    'widget_location' => '0',
    'previous_widget_ids' => '[3,1,2]',
    'widget_ids' => '[1,2,3]',
]);
v11e_check($apiConflict['status'] === 409 && ($apiConflict['body']['error']['code'] ?? '') === 'widget_order_conflict', 'API returns 409 for stale Dashboard order');

$apiInvalid = api_dispatch('widget.reorder', 10, [
    'widget_location' => '0',
    'previous_widget_ids' => '[2,3,1]',
    'widget_ids' => '[2,2,1]',
]);
v11e_check($apiInvalid['status'] === 422, 'API rejects duplicate order IDs');
v11e_check(api_dispatch('widget.reorder', 0, [])['status'] === 401, 'reorder API requires authentication');

set_db_connection_for_testing(null);
if ($failures !== []) {
    fwrite(STDERR, count($failures) . "/{$checks} V1.1-E checks failed\n");
    exit(1);
}
echo "All {$checks} V1.1-E Widget reorder checks passed.\n";
