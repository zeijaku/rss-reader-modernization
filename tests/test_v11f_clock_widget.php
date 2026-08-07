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
function v11f_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) {
        $failures[] = $message;
    }
}

$defaults = dashboard_widget_clock_defaults();
v11f_check($defaults['title'] === 'Clock' && $defaults['hour_format'] === '24', 'Clock defaults keep a simple 24-hour display');
v11f_check($defaults['show_date'] === true && $defaults['show_seconds'] === false, 'Clock defaults show date without seconds');
v11f_check(dashboard_widget_validate_clock_title('日本時間') === '日本時間', 'Clock title accepts safe UTF-8 text');
v11f_check(dashboard_widget_validate_clock_title('') === null, 'Clock title cannot be empty');
v11f_check(dashboard_widget_validate_clock_title(str_repeat('x', 33)) === null, 'Clock title is limited to 32 characters');
v11f_check(dashboard_widget_validate_clock_hour_format('12') === '12' && dashboard_widget_validate_clock_hour_format('24') === '24', '12-hour and 24-hour formats are accepted');
v11f_check(dashboard_widget_validate_clock_hour_format('13') === null, 'unknown hour format is rejected');
v11f_check(dashboard_widget_validate_boolean('1') === true && dashboard_widget_validate_boolean('0') === false, 'explicit form booleans are accepted');
v11f_check(dashboard_widget_validate_boolean('yes') === null, 'ambiguous boolean is rejected');

$config = dashboard_widget_clock_config_from_input([
    'clock_title' => 'Tokyo',
    'clock_hour_format' => '12',
    'clock_show_seconds' => '1',
    'clock_show_date' => '0',
]);
v11f_check(is_array($config) && $config['show_seconds'] === true && $config['show_date'] === false, 'Clock form settings normalize to a strict config');
v11f_check(dashboard_widget_clock_config_from_input([
    'clock_title' => '<script>',
    'clock_hour_format' => '24',
    'clock_show_seconds' => '1',
    'clock_show_date' => '1',
]) !== null, 'Clock title remains data and is escaped at output');
v11f_check(dashboard_widget_clock_config_from_input([
    'clock_title' => 'Clock',
    'clock_hour_format' => '24',
    'clock_show_seconds' => 'missing',
    'clock_show_date' => '1',
]) === null, 'invalid Clock form setting is rejected');

$stored = dashboard_widget_clock_config_from_storage('{"schema":1,"title":"Office","hour_format":"12","show_seconds":true,"show_date":false}');
v11f_check($stored['title'] === 'Office' && $stored['hour_format'] === '12' && $stored['show_seconds'] === true, 'stored Clock config round-trips');
$fallback = dashboard_widget_clock_config_from_storage('{broken');
v11f_check($fallback === $defaults, 'malformed stored Clock config falls back safely');

$row = dashboard_widget_normalize_row([
    'widget_id' => '5',
    'widget_owner' => '7',
    'widget_location' => '2',
    'widget_type' => 'clock',
    'widget_reference_id' => null,
    'widget_sort_order' => '10',
    'widget_width' => '2', 'widget_height' => '1',
    'widget_style' => 'primary',
    'widget_config' => '{"schema":1,"title":"Clock","hour_format":"24","show_seconds":false,"show_date":true}',
]);
v11f_check(is_array($row) && $row['widget_reference_id'] === null, 'Clock Widget row allows a null reference id');
v11f_check(is_array($row) && $row['widget_config_data']['title'] === 'Clock', 'Clock Widget row exposes normalized config');

final class V11fClockPDO extends PDO
{
    /** @var array<int,array<string,mixed>> */
    public array $widgets = [];
    public int $seq = 0;
    public int $lastId = 0;
    private bool $transaction = false;
    private ?array $snapshot = null;

    public function __construct() {}
    public function getAttribute(int $attribute): mixed { return $attribute === PDO::ATTR_DRIVER_NAME ? 'mysql' : null; }
    public function prepare(string $query, array $options = []): PDOStatement|false { return new V11fClockStatement($this, $query); }
    public function beginTransaction(): bool { $this->transaction = true; $this->snapshot = [$this->widgets, $this->seq, $this->lastId]; return true; }
    public function commit(): bool { $this->transaction = false; $this->snapshot = null; return true; }
    public function rollBack(): bool { if ($this->snapshot !== null) { [$this->widgets, $this->seq, $this->lastId] = $this->snapshot; } $this->transaction = false; $this->snapshot = null; return true; }
    public function inTransaction(): bool { return $this->transaction; }
    public function lastInsertId(?string $name = null): string|false { return (string) $this->lastId; }
}

final class V11fClockStatement extends PDOStatement
{
    /** @var list<array<string,mixed>> */
    private array $rows = [];
    private mixed $column = false;
    private int $affected = 0;

    public function __construct(private V11fClockPDO $pdo, private string $sql) {}

    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $this->rows = [];
        $this->column = false;
        $this->affected = 0;

        if (str_starts_with($this->sql, 'SELECT widget_sort_order FROM `ig_dashboard_widget`')) {
            $orders = [];
            foreach ($this->pdo->widgets as $widget) {
                if ($widget['widget_owner'] === (int) $params[':owner']
                    && $widget['widget_location'] === (int) $params[':location']
                    && $widget['widget_flag'] === 0) {
                    $orders[] = $widget['widget_sort_order'];
                }
            }
            $this->column = $orders === [] ? false : max($orders);
            return true;
        }

        if (str_starts_with($this->sql, 'INSERT INTO `ig_dashboard_widget`') && str_contains($this->sql, "'clock'")) {
            $id = ++$this->pdo->seq;
            $this->pdo->lastId = $id;
            $this->pdo->widgets[$id] = [
                'widget_id' => $id,
                'widget_owner' => (int) $params[':owner'],
                'widget_location' => (int) $params[':location'],
                'widget_type' => 'clock',
                'widget_reference_id' => null,
                'widget_sort_order' => (int) $params[':sort_order'],
                'widget_width' => (int) $params[':width'], 'widget_height' => (int) $params[':height'],
                'widget_style' => (string) $params[':style'],
                'widget_config' => (string) $params[':config'],
                'widget_flag' => 0,
                'widget_created_at' => (string) $params[':created_at'],
                'widget_updated_at' => (string) $params[':updated_at'],
            ];
            $this->affected = 1;
            return true;
        }

        if (str_starts_with($this->sql, 'SELECT * FROM `ig_dashboard_widget`')) {
            $widget = $this->pdo->widgets[(int) ($params[':widget_id'] ?? 0)] ?? null;
            if (is_array($widget)
                && $widget['widget_owner'] === (int) ($params[':owner'] ?? 0)
                && $widget['widget_type'] === (string) ($params[':widget_type'] ?? '')
                && $widget['widget_flag'] === 0) {
                $this->rows = [$widget];
            }
            return true;
        }

        if (str_starts_with($this->sql, 'UPDATE `ig_dashboard_widget` SET widget_width')) {
            $id = (int) ($params[':widget_id'] ?? 0);
            $widget = $this->pdo->widgets[$id] ?? null;
            if (is_array($widget) && $widget['widget_owner'] === (int) $params[':owner'] && $widget['widget_type'] === 'clock' && $widget['widget_flag'] === 0) {
                $this->pdo->widgets[$id]['widget_width'] = (int) $params[':width'];
                $this->pdo->widgets[$id]['widget_height'] = (int) $params[':height'];
                $this->pdo->widgets[$id]['widget_style'] = (string) $params[':style'];
                $this->pdo->widgets[$id]['widget_config'] = (string) $params[':config'];
                $this->pdo->widgets[$id]['widget_updated_at'] = (string) $params[':updated_at'];
                $this->affected = 1;
            }
            return true;
        }

        if (str_starts_with($this->sql, 'UPDATE `ig_dashboard_widget` SET widget_flag = 1')) {
            $id = (int) ($params[':widget_id'] ?? 0);
            $widget = $this->pdo->widgets[$id] ?? null;
            if (is_array($widget) && $widget['widget_owner'] === (int) $params[':owner'] && $widget['widget_type'] === 'clock' && $widget['widget_flag'] === 0) {
                $this->pdo->widgets[$id]['widget_flag'] = 1;
                $this->pdo->widgets[$id]['widget_updated_at'] = (string) $params[':updated_at'];
                $this->affected = 1;
            }
            return true;
        }

        throw new RuntimeException('Unexpected SQL in V1.1-F fixture: ' . $this->sql);
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return array_shift($this->rows) ?? false;
    }
    public function fetchColumn(int $column = 0): mixed { return $this->column; }
    public function rowCount(): int { return $this->affected; }
}

$pdo = new V11fClockPDO();
set_db_connection_for_testing($pdo);

$create = api_dispatch('widget.clock.create', 7, [
    'widget_owner' => '999',
    'widget_location' => '1',
    'widget_style' => 'primary',
    'widget_width' => '1', 'widget_height' => '2',
    'clock_title' => 'Main Clock',
    'clock_hour_format' => '24',
    'clock_show_seconds' => '0',
    'clock_show_date' => '1',
]);
v11f_check($create['status'] === 201 && ($create['body']['ok'] ?? false) === true, 'authenticated user can create a Clock Widget');
$firstId = (int) ($create['body']['data']['widget_id'] ?? 0);
v11f_check($firstId === 1 && $pdo->widgets[$firstId]['widget_owner'] === 7, 'Clock owner always comes from the authenticated session');
v11f_check($pdo->widgets[$firstId]['widget_sort_order'] === 10, 'first Clock is appended with the initial order');
v11f_check($pdo->widgets[$firstId]['widget_height'] === 2, 'Clock create stores the requested vertical height');
v11f_check($pdo->widgets[$firstId]['widget_reference_id'] === null, 'Clock does not require another database table reference');

$second = api_dispatch('widget.clock.create', 7, [
    'widget_location' => '1',
    'widget_style' => 'info',
    'widget_width' => '2', 'widget_height' => '1',
    'clock_title' => 'Second',
    'clock_hour_format' => '12',
    'clock_show_seconds' => '1',
    'clock_show_date' => '0',
]);
$secondId = (int) ($second['body']['data']['widget_id'] ?? 0);
v11f_check($second['status'] === 201 && $pdo->widgets[$secondId]['widget_sort_order'] === 20, 'multiple Clocks append after the current Widget order');

$wrongOwnerUpdate = api_dispatch('widget.clock.update', 8, [
    'widget_id' => (string) $firstId,
    'widget_style' => 'danger',
    'widget_width' => '4', 'widget_height' => '1',
    'clock_title' => 'Hijack',
    'clock_hour_format' => '12',
    'clock_show_seconds' => '1',
    'clock_show_date' => '1',
]);
v11f_check($wrongOwnerUpdate['status'] === 404 && $pdo->widgets[$firstId]['widget_style'] === 'primary', 'another user cannot update the Clock');

$update = api_dispatch('widget.clock.update', 7, [
    'widget_id' => (string) $firstId,
    'widget_style' => 'success',
    'widget_width' => '3', 'widget_height' => '1',
    'clock_title' => 'Updated Clock',
    'clock_hour_format' => '12',
    'clock_show_seconds' => '1',
    'clock_show_date' => '0',
]);
$updatedConfig = dashboard_widget_decode_config($pdo->widgets[$firstId]['widget_config']);
v11f_check($update['status'] === 200 && $pdo->widgets[$firstId]['widget_width'] === 3, 'Clock style and width can be updated');
v11f_check($pdo->widgets[$firstId]['widget_height'] === 1, 'Clock update stores the requested vertical height');
v11f_check(($updatedConfig['title'] ?? '') === 'Updated Clock' && ($updatedConfig['show_seconds'] ?? false) === true, 'Clock display settings are stored in widget_config');
v11f_check($pdo->widgets[$firstId]['widget_location'] === 1, 'Clock edit does not silently move it to another tab');

$wrongOwnerDelete = api_dispatch('widget.clock.delete', 8, ['widget_id' => (string) $firstId]);
v11f_check($wrongOwnerDelete['status'] === 404 && $pdo->widgets[$firstId]['widget_flag'] === 0, 'another user cannot delete the Clock');
$delete = api_dispatch('widget.clock.delete', 7, ['widget_id' => (string) $firstId]);
v11f_check($delete['status'] === 200 && $pdo->widgets[$firstId]['widget_flag'] === 1, 'Clock delete is an owner-scoped logical delete');

$invalid = api_dispatch('widget.clock.create', 7, [
    'widget_location' => '9',
    'widget_style' => 'script',
    'widget_width' => '9', 'widget_height' => '1',
    'clock_title' => '',
    'clock_hour_format' => '99',
    'clock_show_seconds' => 'x',
    'clock_show_date' => 'x',
]);
v11f_check($invalid['status'] === 422, 'invalid Clock payload is rejected before database access');
$unauthenticated = api_dispatch('widget.clock.create', 0, []);
v11f_check($unauthenticated['status'] === 401, 'Clock API requires authentication');

set_db_connection_for_testing(null);
if ($failures !== []) {
    fwrite(STDERR, count($failures) . '/' . $checks . " V1.1-F Clock Widget checks failed.\n");
    exit(1);
}
echo 'All ' . $checks . " V1.1-F Clock Widget checks passed.\n";
