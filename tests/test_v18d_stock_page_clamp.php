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
$_SERVER['REQUEST_URI'] = '/?tab=stock&page=999';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '80';
$_GET = ['tab' => 'stock', 'page' => '999'];
require $root . '/app/bootstrap.php';

$GLOBALS['v18d_clamp_select_sql'] = '';

final class V18dClampStatement extends PDOStatement
{
    private array $rows = [];
    private int $countValue = 0;
    public function __construct(private string $sql) {}
    public function execute(?array $params = null): bool
    {
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
        if (str_contains($this->sql, 'FROM ig_content_stock')) {
            if (str_starts_with($this->sql, 'SELECT COUNT(*)')) {
                $this->countValue = 45;
                return true;
            }
            $GLOBALS['v18d_clamp_select_sql'] = $this->sql;
            for ($i = 0; $i < 5; $i++) {
                $this->rows[] = [
                    'stock_id' => 41 + $i,
                    'stock_date' => '2026-08-01 12:00:00',
                    'stock_flag' => 0,
                    'stock_owner' => 1,
                    'stock_data' => 'https://example.com/' . (41 + $i),
                    'stock_title' => 'Article ' . (41 + $i),
                ];
            }
            return true;
        }
        throw new RuntimeException('Unexpected SQL: ' . $this->sql);
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column = 0): mixed { return $this->countValue; }
}

final class V18dClampPDO extends PDO
{
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new V18dClampStatement($query); }
}

set_db_connection_for_testing(new V18dClampPDO());
app_session_start();
app_session_login(1);
ob_start();
require $root . '/public/index.php';
$html = ob_get_clean();
app_session_logout();

$tests = 0;
$failures = [];
function v18d_clamp_check(bool $condition, string $message): void
{
    global $tests, $failures;
    $tests++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) $failures[] = $message;
}

$sql = (string) $GLOBALS['v18d_clamp_select_sql'];
v18d_clamp_check(str_contains($sql, 'LIMIT 20 OFFSET 40'), 'page 999 is clamped before offset is applied');
v18d_clamp_check(substr_count($html, 'class="stock-card"') === 5, 'final page renders only its remaining five rows');
v18d_clamp_check(str_contains($html, '41〜45件を表示 / 3 / 3ページ'), 'out-of-range request renders the normalized final page summary');
v18d_clamp_check(str_contains($html, '<li class="page-item active" aria-current="page"><span class="page-link">3</span></li>'), 'normalized final page is active');
v18d_clamp_check(str_contains($html, 'class="page-item disabled" aria-disabled="true"><span class="page-link">&raquo;</span>'), 'next control is disabled on normalized final page');
v18d_clamp_check(str_contains($html, 'data-stock-empty-redirect="./?tab=stock&amp;page=2"'), 'last-card removal on final page recovers to previous page');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . "/{$tests} V1.8-D page clamp checks failed.\n");
    exit(1);
}
echo "All {$tests} V1.8-D page clamp checks passed.\n";
