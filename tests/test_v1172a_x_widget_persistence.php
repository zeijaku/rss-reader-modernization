<?php

declare(strict_types=1);

function app_validate_text(mixed $value, int $maxLength, bool $allowEmpty = true): ?string
{
    if (!is_string($value)) return null;
    $value = trim($value);
    if (!$allowEmpty && $value === '') return null;
    return strlen($value) <= $maxLength ? $value : null;
}
function app_validate_positive_int(mixed $value): ?int
{
    if (is_int($value)) return $value > 0 ? $value : null;
    if (!is_string($value) || preg_match('/^[1-9][0-9]*$/', $value) !== 1) return null;
    return (int) $value;
}
function dashboard_widget_validate_boolean(mixed $value): ?bool
{
    if (is_bool($value)) return $value;
    if ($value === 1 || $value === '1' || $value === 'true') return true;
    if ($value === 0 || $value === '0' || $value === 'false') return false;
    return null;
}
function dashboard_widget_decode_config(mixed $value): array
{
    if (!is_string($value) || $value === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) && !array_is_list($decoded) ? $decoded : [];
}
function dashboard_widget_encode_config(array $config): string
{
    return json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
function dashboard_widget_validate_location(mixed $value): ?int
{
    $v = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 3]]);
    return is_int($v) ? $v : null;
}
function dashboard_widget_validate_width(mixed $value): ?int
{
    $v = is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : -1);
    return in_array($v, [1,2,3,4], true) ? $v : null;
}
function dashboard_widget_validate_height(mixed $value): ?int
{
    $v = is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : -1);
    return in_array($v, [1,2], true) ? $v : null;
}
function app_normalize_content_style(mixed $value): ?string
{
    return is_string($value) && in_array($value, ['success','primary','info','secondary','dark','warning','danger'], true) ? $value : null;
}
function db_table_identifier(string $name): string { return '`ig_' . $name . '`'; }
function app_now(): string { return '2026-08-19 21:00:00'; }

final class V1172AStatement extends PDOStatement
{
    public string $sql;
    public array $params = [];
    public mixed $row = false;
    public function __construct(string $sql) { $this->sql = $sql; }
    public function execute(?array $params = null): bool { $this->params = $params ?? []; return true; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return $this->row; }
}
final class V1172APdo extends PDO
{
    public bool $transaction = false;
    /** @var list<V1172AStatement> */ public array $statements = [];
    public function __construct() {}
    public function inTransaction(): bool { return $this->transaction; }
    public function beginTransaction(): bool { $this->transaction = true; return true; }
    public function commit(): bool { $this->transaction = false; return true; }
    public function rollBack(): bool { $this->transaction = false; return true; }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $statement = new V1172AStatement($query);
        if (str_starts_with($query, 'SELECT widget_id, widget_location')) {
            $statement->row = [
                'widget_id' => 77,
                'widget_location' => 1,
                'widget_width' => 2,
                'widget_height' => 1,
                'widget_style' => 'dark',
                'widget_config' => '{"schema":1,"title":"X News","username":"XDevelopers","display_count":5,"show_replies":false,"show_reposts":false}',
            ];
        }
        $this->statements[] = $statement;
        return $statement;
    }
    public function lastInsertId(?string $name = null): string|false { return '77'; }
}
$GLOBALS['v1172a_pdo'] = new V1172APdo();
function conn_db(string $type = ''): PDO { return $GLOBALS['v1172a_pdo']; }
function dashboard_widget_next_sort_order(PDO $pdo, int $ownerId, int $location): int { return 40; }
function dashboard_widget_lock_owned_widget(PDO $pdo, int $ownerId, int $widgetId, string $type): ?array
{
    return $ownerId === 5 && $widgetId === 77 && $type === 'x_timeline'
        ? ['widget_id' => 77, 'widget_owner' => 5, 'widget_type' => 'x_timeline', 'widget_flag' => 0, 'widget_config' => '{}']
        : null;
}

require dirname(__DIR__) . '/app/information_widget.php';
require dirname(__DIR__) . '/app/x_widget.php';

$pass = 0; $fail = 0;
function v1172ap_check(bool $condition, string $message): void
{
    global $pass, $fail;
    if ($condition) { $pass++; echo "PASS: {$message}\n"; }
    else { $fail++; echo "FAIL: {$message}\n"; }
}

$config = [
    'title' => 'X News',
    'username' => 'XDevelopers',
    'display_count' => 5,
    'show_replies' => false,
    'show_reposts' => false,
];

$id = x_widget_create(5, 1, 'dark', 2, 1, $config);
v1172ap_check($id === 77, 'create returns inserted X widget id');
$insert = $GLOBALS['v1172a_pdo']->statements[0] ?? null;
v1172ap_check($insert instanceof V1172AStatement && str_contains($insert->sql, 'INSERT INTO `ig_dashboard_widget`'), 'create uses existing dashboard_widget table');
v1172ap_check(($insert->params[':type'] ?? null) === 'x_timeline', 'create stores x_timeline type');
v1172ap_check(($insert->params[':sort_order'] ?? null) === 40, 'create appends through existing widget sort order');
v1172ap_check(str_contains((string) ($insert->params[':config'] ?? ''), 'XDevelopers'), 'create stores normalized JSON config');

$updated = x_widget_update(5, 77, 'info', 3, 2, $config);
v1172ap_check($updated, 'owned X widget update succeeds');
$update = $GLOBALS['v1172a_pdo']->statements[1] ?? null;
v1172ap_check($update instanceof V1172AStatement && str_contains($update->sql, 'UPDATE `ig_dashboard_widget`'), 'update uses existing dashboard_widget table');
v1172ap_check(($update->params[':type'] ?? null) === 'x_timeline' && ($update->params[':owner'] ?? null) === 5, 'update remains owner/type scoped');

$owned = x_widget_owned_config(5, 77);
v1172ap_check(is_array($owned) && ($owned['username'] ?? null) === 'XDevelopers', 'owned config reads normalized X settings');

$deleted = x_widget_delete(5, 77);
v1172ap_check($deleted, 'owned X widget delete succeeds');
$delete = $GLOBALS['v1172a_pdo']->statements[count($GLOBALS['v1172a_pdo']->statements) - 1] ?? null;
v1172ap_check($delete instanceof V1172AStatement && ($delete->params[':type'] ?? null) === 'x_timeline', 'delete is soft-delete scoped to x_timeline');

v1172ap_check(x_widget_update(6, 77, 'dark', 2, 1, $config) === false, 'different owner cannot update X widget');
v1172ap_check(x_widget_delete(6, 77) === false, 'different owner cannot delete X widget');

echo "SUMMARY PASS={$pass} FAIL={$fail}\n";
exit($fail === 0 ? 0 : 1);
