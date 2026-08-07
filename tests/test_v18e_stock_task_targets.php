<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql');
putenv('DB_TABLE_PREFIX=ig_');
putenv('DB_HOST=test');
putenv('DB_NAME=test');
putenv('DB_USER=test');
putenv('DB_PASSWORD=test');
putenv('APP_LOG_ENABLED=false');
require $root . '/app/bootstrap.php';

$GLOBALS['v18e_target_sql'] = '';
$GLOBALS['v18e_target_params'] = [];

final class V18eTargetStatement extends PDOStatement
{
    private array $rows = [];
    public function __construct(private string $sql) {}
    public function execute(?array $params = null): bool
    {
        $GLOBALS['v18e_target_sql'] = $this->sql;
        $GLOBALS['v18e_target_params'] = $params ?? [];
        $this->rows = [
            ['widget_id' => 11, 'widget_location' => 0, 'widget_config' => '{"schema":1,"title":"Work"}'],
            ['widget_id' => 12, 'widget_location' => 2, 'widget_config' => '{"schema":1,"title":"Later"}'],
            ['widget_id' => 0, 'widget_location' => 1, 'widget_config' => '{"schema":1,"title":"Invalid"}'],
            ['widget_id' => 13, 'widget_location' => 9, 'widget_config' => '{"schema":1,"title":"Invalid location"}'],
        ];
        return true;
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
}

final class V18eTargetPDO extends PDO
{
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new V18eTargetStatement($query); }
}

set_db_connection_for_testing(new V18eTargetPDO());
$targets = dashboard_widget_task_targets(55);

$tests = 0;
$failures = [];
function v18e_target_check(bool $condition, string $message): void
{
    global $tests, $failures;
    $tests++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) $failures[] = $message;
}

$sql = (string) $GLOBALS['v18e_target_sql'];
$params = (array) $GLOBALS['v18e_target_params'];
v18e_target_check(str_contains($sql, 'ig_dashboard_widget'), 'Task targets use the Dashboard Widget table');
v18e_target_check(str_contains($sql, 'widget_owner = :owner'), 'Task targets are scoped to the authenticated owner');
v18e_target_check(str_contains($sql, "widget_type = 'task'"), 'Task targets include only Task Widgets');
v18e_target_check(str_contains($sql, 'widget_flag = 0'), 'Task targets include only active Widgets');
v18e_target_check(($params[':owner'] ?? null) === 55, 'Task target query binds the authenticated owner');
v18e_target_check(count($targets) === 2, 'invalid Widget rows are rejected');
v18e_target_check(($targets[0]['widget_id'] ?? null) === 11 && ($targets[0]['title'] ?? null) === 'Work', 'first Task target keeps id and configured title');
v18e_target_check(($targets[1]['widget_location'] ?? null) === 2 && ($targets[1]['title'] ?? null) === 'Later', 'second Task target keeps location and configured title');
v18e_target_check(dashboard_widget_task_targets(0) === [], 'invalid owner returns no targets without widening scope');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . "/{$tests} V1.8-E Task target checks failed.\n");
    exit(1);
}
echo "All {$tests} V1.8-E Task target checks passed.\n";
