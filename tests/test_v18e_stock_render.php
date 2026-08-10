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
$_SERVER['REQUEST_URI'] = '/?tab=stock';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '80';
$_GET = ['tab' => 'stock'];
require $root . '/app/bootstrap.php';

final class V18eRenderStatement extends PDOStatement
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
            $this->rows = [
                ['widget_id' => 21, 'widget_location' => 0, 'widget_config' => '{"schema":1,"title":"Inbox"}'],
                ['widget_id' => 22, 'widget_location' => 2, 'widget_config' => '{"schema":1,"title":"Read later"}'],
            ];
            return true;
        }
        if (str_contains($this->sql, 'FROM ig_content_stock')) {
            if (str_starts_with($this->sql, 'SELECT COUNT(*)')) {
                $this->countValue = 1;
                return true;
            }
            $this->rows = [[
                'stock_id' => 31,
                'stock_date' => '2026-08-07 20:00:00',
                'stock_flag' => 0,
                'stock_owner' => 1,
                'stock_data' => 'https://www.qiita.com/example/article?x=1',
                'stock_title' => '<AI & PHP> tips',
            ]];
            return true;
        }
        throw new RuntimeException('Unexpected SQL: ' . $this->sql);
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column = 0): mixed { return $this->countValue; }
}

final class V18eRenderPDO extends PDO
{
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new V18eRenderStatement($query); }
}

set_db_connection_for_testing(new V18eRenderPDO());
app_session_start();
app_session_login(1);
ob_start();
require $root . '/public/index.php';
$html = ob_get_clean();
app_session_logout();

$tests = 0;
$failures = [];
function v18e_render_check(bool $condition, string $message): void
{
    global $tests, $failures;
    $tests++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) $failures[] = $message;
}

v18e_render_check(substr_count($html, '<article class="stock-card"') === 1, 'Stock renders one compact list item');
v18e_render_check(!str_contains($html, 'col-md-6 col-lg-3 stock-card'), 'Stock no longer renders the legacy four-column card');
v18e_render_check(str_contains($html, 'class="stock-domain"') && str_contains($html, 'qiita.com'), 'www prefix is removed from displayed domain');
v18e_render_check(str_contains($html, '2026-08-07 20:00:00'), 'saved date remains visible');
v18e_render_check(str_contains($html, '&lt;AI &amp; PHP&gt; tips'), 'Stock title remains HTML escaped');
v18e_render_check(str_contains($html, 'data-article-context="stock"'), 'Stock trigger marks shared Actions context');
v18e_render_check(str_contains($html, 'data-article-url="https://www.qiita.com/example/article?x=1"'), 'Stock trigger carries the validated article URL');
v18e_render_check(str_contains($html, 'data-stock-id="31"'), 'Stock trigger carries the Stock id used by logical removal');
v18e_render_check(str_contains($html, 'article-action-stock-remove') && str_contains($html, 'Stock解除'), 'shared Actions menu exposes Stock removal');
v18e_render_check(str_contains($html, 'id="stockTaskTargetModal"'), 'multiple Task Widgets render a target-selection modal');
v18e_render_check(str_contains($html, 'value="21">Inbox — Base</option>'), 'Task target modal shows Widget title and tab name');
v18e_render_check(str_contains($html, 'value="22">Read later — IT</option>'), 'Task target modal shows targets across tabs');
v18e_render_check(!str_contains($html, 'list-group-item-primary') && !str_contains($html, 'list-group-item-success'), 'random colored Stock presentation is gone');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . "/{$tests} V1.8-E render checks failed.\n");
    exit(1);
}
echo "All {$tests} V1.8-E render checks passed.\n";
