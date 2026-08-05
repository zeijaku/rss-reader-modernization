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
function v11d_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) {
        $failures[] = $message;
    }
}

v11d_check(dashboard_widget_types() === ['feed', 'search', 'clock', 'memo', 'task', 'calendar', 'game'], 'Widget type allowlist is exact');
v11d_check(dashboard_widget_validate_type('feed') === 'feed', 'feed Widget type is accepted');
v11d_check(dashboard_widget_validate_type('iframe') === null, 'unknown Widget type is rejected');
v11d_check(dashboard_widget_validate_location('0') === 0 && dashboard_widget_validate_location(3) === 3, 'four tab locations 0..3 are accepted');
v11d_check(dashboard_widget_validate_location(4) === null && dashboard_widget_validate_location('-1') === null, 'out-of-range tab locations are rejected');
v11d_check(dashboard_widget_validate_width(1) === 1 && dashboard_widget_validate_width('4') === 4, 'Widget widths 1..4 are accepted');
v11d_check(dashboard_widget_validate_width(0) === null && dashboard_widget_validate_width(5) === null, 'out-of-range Widget widths are rejected');
v11d_check(dashboard_widget_width_class(1) === 'col-12 col-md-6 col-lg-3', 'default Widget width keeps the existing four-column layout');
v11d_check(dashboard_widget_width_class(4) === 'col-12', 'full-width Widget class is available');

$config = ['schema' => 1, 'show_seconds' => false, 'label' => '日本語'];
$encoded = dashboard_widget_encode_config($config);
v11d_check(dashboard_widget_decode_config($encoded) === $config, 'Widget JSON config round-trips Unicode safely');
v11d_check(dashboard_widget_decode_config('{broken') === [], 'malformed Widget JSON config is rejected safely');
v11d_check(dashboard_widget_decode_config('[1,2]') === [], 'list-shaped Widget config is not accepted as an object');
try {
    dashboard_widget_encode_config(['data' => str_repeat('x', 5000)]);
    $largeRejected = false;
} catch (InvalidArgumentException) {
    $largeRejected = true;
}
v11d_check($largeRejected, 'oversized Widget config is rejected');

$row = dashboard_widget_normalize_row([
    'widget_id' => '10',
    'widget_owner' => '2',
    'widget_location' => '1',
    'widget_type' => 'feed',
    'widget_reference_id' => '20',
    'widget_sort_order' => '30',
    'widget_width' => '1',
    'widget_style' => 'success',
    'widget_config' => '{"schema":1}',
]);
v11d_check(is_array($row) && $row['widget_reference_id'] === 20, 'valid Feed Widget DB row is normalized');
v11d_check(dashboard_widget_normalize_row(array_merge($row ?? [], ['widget_type' => 'feed', 'widget_reference_id' => null])) === null, 'Feed Widget without reference id is rejected');
v11d_check(dashboard_widget_normalize_row(array_merge($row ?? [], ['widget_style' => 'onload=alert(1)'])) === null, 'unsafe Widget style is rejected');

final class V11dFakeStatement extends PDOStatement
{
    private array $rows = [];
    private mixed $column = false;
    private int $affected = 0;

    public function __construct(private V11dFakePDO $pdo, private string $sql) {}

    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $this->rows = [];
        $this->column = false;
        $this->affected = 0;
        $sql = $this->sql;

        if (str_starts_with($sql, 'INSERT INTO ig_content ')) {
            $id = ++$this->pdo->contentSeq;
            $this->pdo->contents[$id] = [
                'content_id' => $id,
                'content_date' => (string) ($params[':date'] ?? ''),
                'content_owner' => (int) ($params[':owner'] ?? 0),
                'content_location' => (int) ($params[':location'] ?? 0),
                'content_style' => (string) ($params[':style'] ?? ''),
                'content_value' => (string) ($params[':value'] ?? ''),
                'content_flag' => 0,
            ];
            $this->pdo->lastId = $id;
            $this->affected = 1;
            return true;
        }

        if (str_starts_with($sql, 'SELECT widget_sort_order FROM `ig_dashboard_widget`')) {
            $owner = (int) ($params[':owner'] ?? 0);
            $location = (int) ($params[':location'] ?? 0);
            $orders = [];
            foreach ($this->pdo->widgets as $widget) {
                if ($widget['widget_owner'] === $owner && $widget['widget_location'] === $location && $widget['widget_flag'] === 0) {
                    $orders[] = $widget['widget_sort_order'];
                }
            }
            $this->column = $orders === [] ? false : max($orders);
            return true;
        }

        if (str_starts_with($sql, 'INSERT INTO `ig_dashboard_widget`')) {
            if ($this->pdo->failWidgetInsert) {
                throw new PDOException('fixture widget insert failure');
            }
            $id = ++$this->pdo->widgetSeq;
            $reference = (int) ($params[':reference_id'] ?? 0);
            foreach ($this->pdo->widgets as $widget) {
                if ($widget['widget_owner'] === (int) $params[':owner'] && $widget['widget_type'] === 'feed' && $widget['widget_reference_id'] === $reference) {
                    throw new PDOException('duplicate widget');
                }
            }
            $this->pdo->widgets[$id] = [
                'widget_id' => $id,
                'widget_owner' => (int) $params[':owner'],
                'widget_location' => (int) $params[':location'],
                'widget_type' => 'feed',
                'widget_reference_id' => $reference,
                'widget_sort_order' => (int) $params[':sort_order'],
                'widget_width' => 1,
                'widget_style' => (string) $params[':style'],
                'widget_config' => null,
                'widget_flag' => 0,
                'widget_created_at' => (string) $params[':created_at'],
                'widget_updated_at' => (string) $params[':updated_at'],
            ];
            $this->pdo->lastId = $id;
            $this->affected = 1;
            return true;
        }

        if (str_starts_with($sql, 'SELECT * FROM `ig_content`')) {
            $id = (int) ($params[':content_id'] ?? 0);
            $owner = (int) ($params[':owner'] ?? 0);
            $row = $this->pdo->contents[$id] ?? null;
            if (is_array($row) && $row['content_owner'] === $owner && $row['content_flag'] === 0) {
                $this->rows = [$row];
            }
            return true;
        }

        if (str_starts_with($sql, 'SELECT * FROM ig_content WHERE content_id =')) {
            $id = (int) ($params[':content_id'] ?? 0);
            $owner = (int) ($params[':owner'] ?? 0);
            $row = $this->pdo->contents[$id] ?? null;
            if (is_array($row) && $row['content_owner'] === $owner && $row['content_flag'] === 0) {
                $this->rows = [$row];
            }
            return true;
        }

        if (str_starts_with($sql, 'SELECT widget_id FROM `ig_dashboard_widget`')) {
            foreach ($this->pdo->widgets as $widget) {
                if ($widget['widget_owner'] === (int) $params[':owner'] && $widget['widget_type'] === 'feed' && $widget['widget_reference_id'] === (int) $params[':reference_id']) {
                    $this->column = $widget['widget_id'];
                    break;
                }
            }
            return true;
        }

        if (str_starts_with($sql, 'UPDATE ig_content SET content_flag = 0')) {
            $id = (int) $params[':content_id'];
            $owner = (int) $params[':owner'];
            if (isset($this->pdo->contents[$id]) && $this->pdo->contents[$id]['content_owner'] === $owner && $this->pdo->contents[$id]['content_flag'] === 0) {
                $this->pdo->contents[$id]['content_value'] = (string) $params[':value'];
                $this->pdo->contents[$id]['content_style'] = (string) $params[':style'];
                $this->affected = 1;
            }
            return true;
        }

        if (str_starts_with($sql, 'UPDATE `ig_dashboard_widget` SET widget_location')) {
            $id = (int) $params[':widget_id'];
            if (isset($this->pdo->widgets[$id]) && $this->pdo->widgets[$id]['widget_owner'] === (int) $params[':owner']) {
                $this->pdo->widgets[$id]['widget_location'] = (int) $params[':location'];
                $this->pdo->widgets[$id]['widget_style'] = (string) $params[':style'];
                $this->pdo->widgets[$id]['widget_flag'] = 0;
                $this->pdo->widgets[$id]['widget_updated_at'] = (string) $params[':updated_at'];
                $this->affected = 1;
            }
            return true;
        }

        if (str_starts_with($sql, 'UPDATE ig_content SET content_flag = 1')) {
            $id = (int) $params[':content_id'];
            $owner = (int) $params[':owner'];
            if (isset($this->pdo->contents[$id]) && $this->pdo->contents[$id]['content_owner'] === $owner && $this->pdo->contents[$id]['content_flag'] === 0) {
                $this->pdo->contents[$id]['content_flag'] = 1;
                $this->affected = 1;
            }
            return true;
        }

        if (str_starts_with($sql, 'UPDATE `ig_dashboard_widget` SET widget_flag = 1')) {
            foreach ($this->pdo->widgets as &$widget) {
                if ($widget['widget_owner'] === (int) $params[':owner'] && $widget['widget_type'] === 'feed' && $widget['widget_reference_id'] === (int) $params[':reference_id'] && $widget['widget_flag'] === 0) {
                    $widget['widget_flag'] = 1;
                    $widget['widget_updated_at'] = (string) $params[':updated_at'];
                    $this->affected++;
                }
            }
            unset($widget);
            return true;
        }

        if (str_starts_with($sql, 'SELECT w.widget_id,')) {
            $owner = (int) $params[':owner'];
            $location = (int) $params[':location'];
            foreach ($this->pdo->widgets as $widget) {
                if ($widget['widget_owner'] !== $owner || $widget['widget_location'] !== $location || $widget['widget_flag'] !== 0) {
                    continue;
                }
                $content = $this->pdo->contents[$widget['widget_reference_id']] ?? [];
                $this->rows[] = array_merge($widget, $content);
            }
            usort($this->rows, static fn(array $a, array $b): int => [$a['widget_sort_order'], $a['widget_id']] <=> [$b['widget_sort_order'], $b['widget_id']]);
            return true;
        }

        throw new RuntimeException('Unhandled fixture SQL: ' . $sql);
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return array_shift($this->rows) ?: false;
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column = 0): mixed { return $this->column; }
    public function rowCount(): int { return $this->affected; }
}

final class V11dFakePDO extends PDO
{
    public array $contents = [];
    public array $widgets = [];
    public int $contentSeq = 0;
    public int $widgetSeq = 0;
    public int $lastId = 0;
    public bool $failWidgetInsert = false;
    private bool $transaction = false;
    private array $snapshot = [];

    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new V11dFakeStatement($this, $query); }
    public function beginTransaction(): bool {
        $this->snapshot = [$this->contents, $this->widgets, $this->contentSeq, $this->widgetSeq, $this->lastId];
        $this->transaction = true;
        return true;
    }
    public function commit(): bool { $this->transaction = false; $this->snapshot = []; return true; }
    public function rollBack(): bool {
        [$this->contents, $this->widgets, $this->contentSeq, $this->widgetSeq, $this->lastId] = $this->snapshot;
        $this->transaction = false;
        $this->snapshot = [];
        return true;
    }
    public function inTransaction(): bool { return $this->transaction; }
    public function lastInsertId(?string $name = null): string|false { return (string) $this->lastId; }
    public function getAttribute(int $attribute): mixed { return $attribute === PDO::ATTR_DRIVER_NAME ? 'sqlite' : null; }
}

$pdo = new V11dFakePDO();
set_db_connection_for_testing($pdo);
$contentA = dashboard_widget_create_feed(10, 'https://example.com/feed-a', 'success', 0);
$contentB = dashboard_widget_create_feed(10, 'https://example.com/feed-b', 'info', 0);
dashboard_widget_create_feed(20, 'https://example.com/other', 'danger', 0);

v11d_check(count($pdo->contents) === 3 && count($pdo->widgets) === 3, 'Feed creation writes content and Widget together');
v11d_check($pdo->widgets[1]['widget_sort_order'] === 10 && $pdo->widgets[2]['widget_sort_order'] === 20, 'new Feed appends to the current Widget order');
v11d_check($pdo->widgets[1]['widget_width'] === 1, 'new Feed Widget keeps existing width');

$list = search_dashboard_widgets(10, 0);
v11d_check(count($list) === 2, 'Widget list returns only the authenticated owner and selected tab');
v11d_check(array_column($list, 'content_id') === [$contentA, $contentB], 'Widget list follows sort order then Widget id');
v11d_check(search_dashboard_widgets(99, 0) === [], 'another owner cannot list private Widgets');
$public = dashboard_widget_public_list(10, 0);
v11d_check(!array_key_exists('widget_owner', $public[0]), 'public Widget metadata does not expose owner id');
v11d_check(($public[0]['widget_type'] ?? '') === 'feed', 'public Widget metadata keeps allowlisted type');

v11d_check(dashboard_widget_update_feed(10, $contentA, 'https://example.com/feed-a2', 'warning'), 'owned Feed update succeeds');
v11d_check($pdo->contents[$contentA]['content_style'] === 'warning' && $pdo->widgets[1]['widget_style'] === 'warning', 'Feed style remains mirrored in content and Widget');
v11d_check(!dashboard_widget_update_feed(20, $contentA, 'https://evil.example/', 'danger'), 'another owner cannot update Feed Widget');
v11d_check($pdo->contents[$contentA]['content_value'] === 'https://example.com/feed-a2', 'failed cross-owner update leaves content unchanged');

v11d_check(dashboard_widget_delete_feed(10, $contentA), 'owned Feed delete succeeds');
v11d_check($pdo->contents[$contentA]['content_flag'] === 1 && $pdo->widgets[1]['widget_flag'] === 1, 'Feed delete soft-deletes content and Widget together');
v11d_check(!dashboard_widget_delete_feed(20, $contentB), 'another owner cannot delete Feed Widget');
v11d_check(count(search_dashboard_widgets(10, 0)) === 1, 'deleted Feed Widget is excluded from Dashboard');

$beforeContents = $pdo->contents;
$beforeWidgets = $pdo->widgets;
$pdo->failWidgetInsert = true;
try {
    dashboard_widget_create_feed(10, 'https://example.com/fail', 'success', 0);
    $rolledBack = false;
} catch (PDOException) {
    $rolledBack = true;
}
$pdo->failWidgetInsert = false;
v11d_check($rolledBack, 'Widget insert failure is surfaced');
v11d_check($pdo->contents === $beforeContents && $pdo->widgets === $beforeWidgets, 'Widget insert failure rolls back the content insert');

$apiList = api_dispatch('widget.list', 10, ['widget_location' => '0', 'owner_id' => 20]);
v11d_check($apiList['status'] === 200 && count($apiList['body']['data']['widgets'] ?? []) === 1, 'widget.list ignores client owner id and uses authenticated owner');
$apiInvalid = api_dispatch('widget.list', 10, ['widget_location' => 9]);
v11d_check($apiInvalid['status'] === 422, 'widget.list rejects invalid tab location');
$apiAnonymous = api_dispatch('widget.list', 0, ['widget_location' => 0]);
v11d_check($apiAnonymous['status'] === 401, 'widget.list requires authentication');

set_db_connection_for_testing(null);
if ($failures !== []) {
    fwrite(STDERR, count($failures) . "/{$checks} V1.1-D checks failed\n");
    exit(1);
}
echo "All {$checks} V1.1-D Dashboard Widget checks passed.\n";
