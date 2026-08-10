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
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/?tab=stock&q=AI&sort=oldest';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '80';
$_GET = ['tab' => 'stock', 'q' => 'AI', 'sort' => 'oldest'];
require $root . '/app/bootstrap.php';

$GLOBALS['v18c_stock_sql'] = '';
$GLOBALS['v18c_stock_params'] = [];

final class V18cRenderStatement extends PDOStatement
{
    private array $rows = [];
    private int $countValue = 0;
    public function __construct(private string $sql) {}
    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $this->rows = [];
        if (str_contains($this->sql, 'FROM ig_user_conf')) {
            $this->rows = [[
                'conf_style' => 'bootstrap', 'conf_style_nav' => 'dark',
                'conf_style_tabname1' => 'Base', 'conf_style_tabname2' => 'Maint',
                'conf_style_tabname3' => 'IT', 'conf_style_tabname4' => 'Observe',
                'conf_style_navlink1' => '', 'conf_style_navlink_view1' => '', 'conf_style_navlink_icon1' => 'map-marker-alt',
                'conf_style_navlink2' => '', 'conf_style_navlink_view2' => '', 'conf_style_navlink_icon2' => 'mail-bulk',
                'conf_style_navlink3' => '', 'conf_style_navlink_view3' => '', 'conf_style_navlink_icon3' => 'search',
                'conf_style_navlink4' => '', 'conf_style_navlink_view4' => '', 'conf_style_navlink_icon4' => 'images',
            ]];
            return true;
        }
        if (str_contains($this->sql, 'ig_dashboard_widget') && str_contains($this->sql, "widget_type = 'task'")) {
            $this->rows = [];
            return true;
        }
        if (str_contains($this->sql, 'FROM `ig_feed_keyword`')) {
            return true;
        }
        if (str_starts_with($this->sql, 'SELECT t.tag_id')) {
            return true;
        }
        if (str_starts_with($this->sql, 'SELECT m.map_stock_id')) {
            return true;
        }
        if (str_contains($this->sql, 'FROM ig_content_stock')) {
            if (str_starts_with($this->sql, 'SELECT COUNT(*)')) {
                $this->countValue = 1;
                return true;
            }
            $GLOBALS['v18c_stock_sql'] = $this->sql;
            $GLOBALS['v18c_stock_params'] = $params;
            $this->rows = [[
                'stock_id' => 7,
                'stock_date' => '2026-08-01 12:00:00',
                'stock_flag' => 0,
                'stock_owner' => 1,
                'stock_data' => 'https://example.com/ai/1',
                'stock_title' => 'AI article',
            ]];
            return true;
        }
        throw new RuntimeException('Unexpected SQL: ' . $this->sql);
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column = 0): mixed { return $this->countValue; }
}

final class V18cRenderPDO extends PDO
{
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $query, $matches);
        if (count($matches[0]) !== count(array_unique($matches[0]))) {
            throw new RuntimeException('Duplicate named placeholder would fail native PDO prepare.');
        }
        return new V18cRenderStatement($query);
    }
}

set_db_connection_for_testing(new V18cRenderPDO());
app_session_start();
app_session_login(1);
ob_start();
require $root . '/public/index.php';
$html = ob_get_clean();
app_session_logout();

$tests = 0;
$failures = [];
function v18c_render_check(bool $condition, string $message): void
{
    global $tests, $failures;
    $tests++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) $failures[] = $message;
}

$sql = (string) $GLOBALS['v18c_stock_sql'];
$params = (array) $GLOBALS['v18c_stock_params'];
v18c_render_check(str_contains($sql, 's.stock_flag = 0 AND s.stock_owner = :owner'), 'rendered search keeps active owner scope');
v18c_render_check(str_contains($sql, "stock_title LIKE :stock_title_query ESCAPE '!'"), 'rendered search includes title filter with a unique placeholder');
v18c_render_check(str_contains($sql, "stock_data LIKE :stock_data_query ESCAPE '!'"), 'rendered search includes URL/domain filter with a unique placeholder');
v18c_render_check(str_contains($sql, 'ORDER BY stock_id ASC'), 'oldest request selects only the whitelisted oldest SQL');
v18c_render_check(($params[':owner'] ?? null) === 1, 'rendered search binds authenticated owner');
v18c_render_check(($params[':stock_title_query'] ?? null) === '%AI%', 'rendered search binds the title query pattern');
v18c_render_check(($params[':stock_data_query'] ?? null) === '%AI%', 'rendered search binds the URL/domain query pattern');
v18c_render_check(str_contains($html, 'value="AI"'), 'search query is retained in rendered form');
v18c_render_check(str_contains($html, '<option value="oldest" selected>古い順</option>'), 'selected sort is retained in rendered form');
v18c_render_check(str_contains($html, '「AI」の検索結果: 1件'), 'filtered result count is shown');
v18c_render_check(str_contains($html, 'AI article'), 'matching Stock row is rendered');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . "/{$tests} V1.8-C render checks failed.\n");
    exit(1);
}
echo "All {$tests} V1.8-C render checks passed.\n";
