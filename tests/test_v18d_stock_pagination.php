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
$_SERVER['REQUEST_URI'] = '/stock?q=AI&sort=oldest&page=8';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '80';
$_GET = ['q' => 'AI', 'sort' => 'oldest', 'page' => '8'];
require $root . '/app/bootstrap.php';

$GLOBALS['v18d_count_sql'] = '';
$GLOBALS['v18d_count_params'] = [];
$GLOBALS['v18d_select_sql'] = '';
$GLOBALS['v18d_select_params'] = [];

final class V18dPaginationStatement extends PDOStatement
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
            preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $this->sql, $matches);
            if (count($matches[0]) !== count(array_unique($matches[0]))) {
                throw new RuntimeException('Duplicate named placeholder would fail native PDO prepare.');
            }
            if (str_starts_with($this->sql, 'SELECT COUNT(*)')) {
                $GLOBALS['v18d_count_sql'] = $this->sql;
                $GLOBALS['v18d_count_params'] = $params;
                $this->countValue = 300;
                return true;
            }
            $GLOBALS['v18d_select_sql'] = $this->sql;
            $GLOBALS['v18d_select_params'] = $params;
            for ($i = 0; $i < 20; $i++) {
                $this->rows[] = [
                    'stock_id' => 141 + $i,
                    'stock_date' => '2026-08-01 12:00:00',
                    'stock_flag' => 0,
                    'stock_owner' => 1,
                    'stock_data' => 'https://example.com/ai/' . (141 + $i),
                    'stock_title' => 'AI article ' . (141 + $i),
                ];
            }
            return true;
        }
        throw new RuntimeException('Unexpected SQL: ' . $this->sql);
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column = 0): mixed { return $this->countValue; }
}

final class V18dPaginationPDO extends PDO
{
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new V18dPaginationStatement($query);
    }
}

set_db_connection_for_testing(new V18dPaginationPDO());
app_session_start();
app_session_login(1);
ob_start();
require $root . '/public/stock.php';
$html = ob_get_clean();
app_session_logout();

$tests = 0;
$failures = [];
function v18d_check(bool $condition, string $message): void
{
    global $tests, $failures;
    $tests++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) $failures[] = $message;
}

$countSql = (string) $GLOBALS['v18d_count_sql'];
$countParams = (array) $GLOBALS['v18d_count_params'];
$selectSql = (string) $GLOBALS['v18d_select_sql'];
$selectParams = (array) $GLOBALS['v18d_select_params'];

v18d_check(str_starts_with($countSql, 'SELECT COUNT(*) FROM ig_content_stock'), 'pagination performs a dedicated COUNT query');
v18d_check(str_contains($countSql, 's.stock_flag = 0 AND s.stock_owner = :owner'), 'COUNT query keeps active owner scope');
v18d_check(str_contains($countSql, 'stock_title LIKE :stock_title_query') && str_contains($countSql, 'stock_data LIKE :stock_data_query'), 'COUNT query applies search filters');
v18d_check(($countParams[':owner'] ?? null) === 1, 'COUNT query binds authenticated owner');
v18d_check(($countParams[':stock_title_query'] ?? null) === '%AI%' && ($countParams[':stock_data_query'] ?? null) === '%AI%', 'COUNT query binds both search placeholders');
v18d_check(str_contains($selectSql, 'ORDER BY stock_id ASC LIMIT 20 OFFSET 140'), 'page 8 fetches only rows 141-160 in oldest order');
v18d_check(($selectParams[':owner'] ?? null) === 1, 'page query binds authenticated owner');
v18d_check(($selectParams[':stock_title_query'] ?? null) === '%AI%' && ($selectParams[':stock_data_query'] ?? null) === '%AI%', 'page query preserves search bindings');
v18d_check(substr_count($html, 'class="stock-card"') === 20, 'only 20 Stock cards are rendered');
v18d_check(str_contains($html, '「AI」の検索結果: 300件'), 'search result count shows all matching rows');
v18d_check(str_contains($html, '141〜160件を表示 / 8 / 15ページ'), 'current row range and page count are shown');
v18d_check(str_contains($html, '<li class="page-item active" aria-current="page"><span class="page-link">8</span></li>'), 'current page is marked active');
v18d_check(str_contains($html, 'href="./stock?q=AI&amp;sort=oldest&amp;page=7" aria-label="前のページ"'), 'previous link preserves search and sort');
v18d_check(str_contains($html, 'href="./stock?q=AI&amp;sort=oldest&amp;page=9" aria-label="次のページ"'), 'next link preserves search and sort');
v18d_check(str_contains($html, 'href="./stock?q=AI&amp;sort=oldest">1</a>'), 'page 1 link preserves conditions without a redundant page parameter');
v18d_check(substr_count($html, '<span class="page-link">…</span>') === 2, 'large page sets use compact ellipses');
v18d_check(str_contains($html, 'data-stock-empty-redirect="./stock?q=AI&amp;sort=oldest&amp;page=7"'), 'paginated last-card removal has a previous-page recovery URL');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . "/{$tests} V1.8-D pagination checks failed.\n");
    exit(1);
}
echo "All {$tests} V1.8-D pagination checks passed.\n";
