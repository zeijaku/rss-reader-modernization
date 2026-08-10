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
require_once $root . '/app/mini_game.php';
require_once $root . '/app/api.php';

$checks = 0;
$failures = [];
function v14b_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) {
        $failures[] = $message;
    }
}

$defaults = mini_game_widget_defaults();
v14b_check($defaults === ['schema' => 1, 'title' => 'Icon Quest', 'game' => 'icon_quest'], 'Game Widget defaults are explicit');
v14b_check(mini_game_widget_validate_type('icon_quest') === 'icon_quest', 'known Game type is accepted');
v14b_check(mini_game_widget_validate_type('unknown') === null, 'unknown Game type is rejected');
v14b_check(mini_game_widget_validate_title('Game') === 'Game', 'Game title accepts bounded text');
v14b_check(mini_game_widget_validate_title('') === null, 'Game title cannot be empty');
v14b_check(mini_game_widget_validate_title(str_repeat('x', 33)) === null, 'Game title is limited to 32 characters');

$config = mini_game_widget_config_from_input(['game_title' => '休憩', 'game_type' => 'icon_quest']);
v14b_check(is_array($config) && $config['title'] === '休憩' && $config['game'] === 'icon_quest', 'Game form input normalizes to a strict config');
v14b_check(mini_game_widget_config_from_input(['game_title' => 'Game', 'game_type' => 'other']) === null, 'invalid Game form input is rejected');
v14b_check(mini_game_widget_config_from_storage('{broken') === $defaults, 'broken stored config falls back safely');
v14b_check(mini_game_widget_config_from_storage('{"schema":2,"title":"Old","game":"icon_quest"}') === $defaults, 'unknown config schema falls back safely');

$board = mini_game_icon_quest_initial_board();
v14b_check(count($board) === 25, 'initial board is exactly 5x5');
v14b_check(count(array_filter($board, static fn(string $cell): bool => $cell === 'player')) === 1, 'initial board has one Player');
v14b_check(count(array_filter($board, static fn(string $cell): bool => $cell === 'enemy')) === 1, 'initial board has one Enemy');
v14b_check(count(array_filter($board, static fn(string $cell): bool => $cell === 'treasure')) === 1, 'initial board has one Treasure');
v14b_check(count(array_filter($board, static fn(string $cell): bool => $cell === 'goal')) === 1, 'initial board has one Goal');

$row = dashboard_widget_normalize_row([
    'widget_id' => '8',
    'widget_owner' => '7',
    'widget_location' => '1',
    'widget_type' => 'game',
    'widget_reference_id' => null,
    'widget_sort_order' => '4',
    'widget_width' => '1', 'widget_height' => '1',
    'widget_style' => 'secondary',
    'widget_config' => '{"schema":1,"title":"Icon Quest","game":"icon_quest"}',
]);
v14b_check(is_array($row) && $row['widget_reference_id'] === null, 'Game Widget uses no reference table');
v14b_check(is_array($row) && $row['widget_config_data']['game'] === 'icon_quest', 'Game Widget row exposes normalized config');

final class V14bGamePDO extends PDO
{
    /** @var array<int,array<string,mixed>> */
    public array $widgets = [];
    public int $seq = 0;
    public int $lastId = 0;
    private bool $transaction = false;
    private ?array $snapshot = null;

    public function __construct() {}
    public function getAttribute(int $attribute): mixed { return $attribute === PDO::ATTR_DRIVER_NAME ? 'mysql' : null; }
    public function prepare(string $query, array $options = []): PDOStatement|false { return new V14bGameStatement($this, $query); }
    public function beginTransaction(): bool { $this->transaction = true; $this->snapshot = [$this->widgets, $this->seq, $this->lastId]; return true; }
    public function commit(): bool { $this->transaction = false; $this->snapshot = null; return true; }
    public function rollBack(): bool { if ($this->snapshot !== null) { [$this->widgets, $this->seq, $this->lastId] = $this->snapshot; } $this->transaction = false; $this->snapshot = null; return true; }
    public function inTransaction(): bool { return $this->transaction; }
    public function lastInsertId(?string $name = null): string|false { return (string) $this->lastId; }
}

final class V14bGameStatement extends PDOStatement
{
    /** @var list<array<string,mixed>> */
    private array $rows = [];
    private mixed $column = false;
    private int $affected = 0;

    public function __construct(private V14bGamePDO $pdo, private string $sql) {}

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

        if (str_starts_with($this->sql, 'INSERT INTO `ig_dashboard_widget`') && str_contains($this->sql, "'game'")) {
            $id = ++$this->pdo->seq;
            $this->pdo->lastId = $id;
            $this->pdo->widgets[$id] = [
                'widget_id' => $id,
                'widget_owner' => (int) $params[':owner'],
                'widget_location' => (int) $params[':location'],
                'widget_type' => 'game',
                'widget_reference_id' => null,
                'widget_sort_order' => (int) $params[':sort_order'],
                'widget_width' => (int) $params[':width'], 'widget_height' => '1',
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
            if (is_array($widget) && $widget['widget_owner'] === (int) $params[':owner'] && $widget['widget_type'] === 'game' && $widget['widget_flag'] === 0) {
                $this->pdo->widgets[$id]['widget_width'] = (int) $params[':width'];
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
            if (is_array($widget) && $widget['widget_owner'] === (int) $params[':owner'] && $widget['widget_type'] === 'game' && $widget['widget_flag'] === 0) {
                $this->pdo->widgets[$id]['widget_flag'] = 1;
                $this->pdo->widgets[$id]['widget_updated_at'] = (string) $params[':updated_at'];
                $this->affected = 1;
            }
            return true;
        }

        throw new RuntimeException('Unexpected SQL in V1.4-B fixture: ' . $this->sql);
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return array_shift($this->rows) ?? false;
    }
    public function fetchColumn(int $column = 0): mixed { return $this->column; }
    public function rowCount(): int { return $this->affected; }
}

$pdo = new V14bGamePDO();
set_db_connection_for_testing($pdo);

$first = api_dispatch('widget.game.create', 7, [
    'widget_owner' => '999',
    'widget_location' => '1',
    'widget_style' => 'secondary',
    'widget_width' => '1', 'widget_height' => '1',
    'game_title' => 'Icon Quest A',
    'game_type' => 'icon_quest',
]);
v14b_check($first['status'] === 201 && ($first['body']['ok'] ?? false) === true, 'authenticated user can create a Game Widget');
$firstId = (int) ($first['body']['data']['widget_id'] ?? 0);
v14b_check($firstId === 1 && $pdo->widgets[$firstId]['widget_owner'] === 7, 'client owner field is ignored');
v14b_check($pdo->widgets[$firstId]['widget_reference_id'] === null, 'Game Widget stores no reference id');

$second = api_dispatch('widget.game.create', 7, [
    'widget_location' => '1',
    'widget_style' => 'primary',
    'widget_width' => '2', 'widget_height' => '1',
    'game_title' => 'Icon Quest B',
    'game_type' => 'icon_quest',
]);
$secondId = (int) ($second['body']['data']['widget_id'] ?? 0);
v14b_check($second['status'] === 201 && $secondId === 2, 'same user can create multiple Game Widgets');
v14b_check($pdo->widgets[$secondId]['widget_sort_order'] > $pdo->widgets[$firstId]['widget_sort_order'], 'new Game Widget appends after current order');

$invalid = api_dispatch('widget.game.create', 7, [
    'widget_location' => '1', 'widget_style' => 'secondary', 'widget_width' => '1', 'widget_height' => '1',
    'game_title' => 'Broken', 'game_type' => 'unknown',
]);
v14b_check($invalid['status'] === 422, 'unknown Game type is rejected before mutation');

$foreignUpdate = api_dispatch('widget.game.update', 8, [
    'widget_id' => (string) $firstId,
    'widget_style' => 'danger',
    'widget_width' => '4', 'widget_height' => '1',
    'game_title' => 'Foreign',
    'game_type' => 'icon_quest',
]);
v14b_check($foreignUpdate['status'] === 404, 'another user cannot update a Game Widget');

$update = api_dispatch('widget.game.update', 7, [
    'widget_id' => (string) $firstId,
    'widget_style' => 'info',
    'widget_width' => '2', 'widget_height' => '1',
    'game_title' => 'Updated Game',
    'game_type' => 'icon_quest',
]);
v14b_check($update['status'] === 200 && $pdo->widgets[$firstId]['widget_style'] === 'info', 'owner can update Game Widget presentation');
$updatedConfig = json_decode((string) $pdo->widgets[$firstId]['widget_config'], true);
v14b_check(is_array($updatedConfig) && $updatedConfig['title'] === 'Updated Game', 'Game config is stored in existing widget_config');

$foreignDelete = api_dispatch('widget.game.delete', 8, ['widget_id' => (string) $firstId]);
v14b_check($foreignDelete['status'] === 404 && $pdo->widgets[$firstId]['widget_flag'] === 0, 'another user cannot delete a Game Widget');
$delete = api_dispatch('widget.game.delete', 7, ['widget_id' => (string) $firstId]);
v14b_check($delete['status'] === 200 && $pdo->widgets[$firstId]['widget_flag'] === 1, 'owner can logically delete a Game Widget');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . '/' . $checks . " V1.4-B checks failed.\n");
    exit(1);
}

echo "All {$checks} V1.4-B Game Widget checks passed.\n";
