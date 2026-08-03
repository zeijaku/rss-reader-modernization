from __future__ import annotations

import base64
from html.parser import HTMLParser
from pathlib import Path
import subprocess
import tempfile
import textwrap

ROOT = Path(__file__).resolve().parents[1]
failures: list[str] = []


def check(condition: bool, message: str) -> None:
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        failures.append(message)


worker = textwrap.dedent(r'''<?php
$root = $argv[1];
$mode = $argv[2];
$GLOBALS['v11d_render_mode'] = $mode;
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql');
putenv('DB_TABLE_PREFIX=ig_');
putenv('DB_HOST=test');
putenv('DB_NAME=test');
putenv('DB_USER=test');
putenv('DB_PASSWORD=test');
putenv('REGISTRATION_ENABLED=true');
putenv('APP_LOG_ENABLED=false');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = $mode === 'stock' ? '/?tab=stock' : '/?tab=0';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '80';
$_GET['tab'] = $mode === 'stock' ? 'stock' : '0';
require $root . '/app/bootstrap.php';

final class V11dRenderStatement extends PDOStatement
{
    private array $rows = [];
    public function __construct(private string $sql) {}
    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $mode = (string) ($GLOBALS['v11d_render_mode'] ?? 'feed');
        $this->rows = [];
        if (str_contains($this->sql, 'FROM ig_user_conf')) {
            $this->rows = [[
                'conf_style' => 'bootstrap', 'conf_style_nav' => 'dark',
                'conf_style_tabname1' => 'Base', 'conf_style_tabname2' => 'Maint',
                'conf_style_tabname3' => 'IT', 'conf_style_tabname4' => 'Observe',
                'conf_style_navlink1' => 'https://map.google.com/', 'conf_style_navlink_view1' => 'Map', 'conf_style_navlink_icon1' => 'map-marker-alt',
                'conf_style_navlink2' => '', 'conf_style_navlink_view2' => '', 'conf_style_navlink_icon2' => 'mail-bulk',
                'conf_style_navlink3' => '', 'conf_style_navlink_view3' => '', 'conf_style_navlink_icon3' => 'search',
                'conf_style_navlink4' => '', 'conf_style_navlink_view4' => '', 'conf_style_navlink_icon4' => 'images',
            ]];
            return true;
        }
        if (str_contains($this->sql, 'FROM `ig_dashboard_widget` w')) {
            if ($mode !== 'feed' || (int) ($params[':owner'] ?? 0) !== 1 || (int) ($params[':location'] ?? -1) !== 0) {
                return true;
            }
            $fixtures = [
                [12, 102, 1, 10, 'info', 2],
                [11, 101, 1, 20, 'success', 1],
                [13, 103, 1, 30, 'warning', 1],
                [99, 999, 2, 5, 'danger', 1],
            ];
            foreach ($fixtures as [$widgetId, $contentId, $owner, $sort, $style, $width]) {
                if ($owner !== (int) $params[':owner']) continue;
                $this->rows[] = [
                    'widget_id' => $widgetId,
                    'widget_owner' => $owner,
                    'widget_location' => 0,
                    'widget_type' => 'feed',
                    'widget_reference_id' => $contentId,
                    'widget_sort_order' => $sort,
                    'widget_width' => $width,
                    'widget_style' => $style,
                    'widget_config' => null,
                    'widget_flag' => 0,
                    'widget_created_at' => '2026-08-02 10:00:00',
                    'widget_updated_at' => '2026-08-02 10:00:00',
                    'content_id' => $contentId,
                    'content_date' => '2026-08-02 10:00:00',
                    'content_flag' => 0,
                    'content_owner' => $owner,
                    'content_location' => 0,
                    'content_style' => $style,
                    'content_value' => 'https://example.com/feed' . $contentId . '.xml',
                ];
            }
            usort($this->rows, static fn(array $a, array $b): int => [$a['widget_sort_order'], $a['widget_id']] <=> [$b['widget_sort_order'], $b['widget_id']]);
            return true;
        }
        if (str_contains($this->sql, 'FROM ig_content_stock')) {
            if ($mode === 'stock') {
                $this->rows = [[
                    'stock_data' => 'https://example.com/article',
                    'stock_title' => 'Stock title',
                    'stock_date' => '2026-08-02 10:00:00',
                ]];
            }
            return true;
        }
        throw new RuntimeException('Unexpected SQL: ' . $this->sql);
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
}
final class V11dRenderPDO extends PDO
{
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new V11dRenderStatement($query); }
}
set_db_connection_for_testing(new V11dRenderPDO());
app_session_start();
app_session_login(1);
ob_start();
require $root . '/public/index.php';
$html = ob_get_clean();
app_session_logout();
echo base64_encode($html);
''')


class Parser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.records: list[tuple[str, dict[str, str]]] = []
        self.text: list[str] = []

    def handle_starttag(self, tag: str, attrs) -> None:
        self.records.append((tag, {str(k): '' if v is None else str(v) for k, v in attrs}))

    def handle_data(self, data: str) -> None:
        self.text.append(data)


def render(mode: str) -> tuple[Parser, str]:
    with tempfile.TemporaryDirectory(prefix='v11d-render-') as temp:
        worker_path = Path(temp) / 'worker.php'
        worker_path.write_text(worker, encoding='utf-8')
        result = subprocess.run(
            ['php', str(worker_path), str(ROOT), mode],
            cwd=ROOT,
            text=True,
            capture_output=True,
            check=False,
            timeout=30,
        )
    check(result.returncode == 0, f'{mode} Dashboard render exits successfully')
    check(result.stderr.strip() == '', f'{mode} Dashboard render has no PHP warning')
    html = base64.b64decode(result.stdout.strip()).decode('utf-8') if result.returncode == 0 else ''
    parser = Parser()
    parser.feed(html)
    return parser, html


def classes(attrs: dict[str, str]) -> set[str]:
    return set(attrs.get('class', '').split())


feed, feed_html = render('feed')
feed_cards = [(tag, attrs) for tag, attrs in feed.records if attrs.get('data-dashboard-widget-id')]
check(len(feed_cards) == 3, 'Feed Dashboard renders three owned Widget records')
check(all(tag == 'section' for tag, _ in feed_cards), 'all Dashboard Widgets use section regions')
check([attrs.get('data-dashboard-widget-id') for _, attrs in feed_cards] == ['12', '11', '13'], 'Widget output follows widget_sort_order')
check([attrs.get('data-feed-content-id') for _, attrs in feed_cards] == ['102', '101', '103'], 'Feed references remain connected to content ids')
check(all(attrs.get('data-dashboard-widget-type') == 'feed' for _, attrs in feed_cards), 'Widget type hook is feed')
check(all(attrs.get('data-dashboard-widget-location') == '0' for _, attrs in feed_cards), 'Widget location hook keeps the active tab')
check('col-lg-6' in classes(feed_cards[0][1]), 'width=2 Widget renders as a two-column-width card')
check('col-lg-3' in classes(feed_cards[1][1]), 'width=1 Widget retains the existing four-column layout')
check('999' not in feed_html, 'another owner Widget is not rendered')
check(all(attrs.get('role') == 'region' and attrs.get('aria-busy') == 'true' for _, attrs in feed_cards), 'Widget Feed cards retain region and loading semantics')
check(all(name in ''.join(feed.text) for name in ['Base', 'Maint', 'IT', 'Observe']), 'all four existing tab labels remain visible')
check('RSS Reader Modernization V1.1-' in ''.join(feed.text) or 'RSS Reader Modernization 1.1.0' in ''.join(feed.text), 'Dashboard displays a Version 1.1 marker')

stock, stock_html = render('stock')
stock_cards = [(tag, attrs) for tag, attrs in stock.records if tag == 'article' and 'stock-card' in classes(attrs)]
check(len(stock_cards) == 1, 'Stock page remains independent from Dashboard Widget rows')
check('Stock title' in ''.join(stock.text), 'Stock page still renders existing Stock data')
check('data-dashboard-widget-id' not in stock_html, 'Stock cards are not incorrectly converted into Dashboard Widgets')

for parser, label in [(feed, 'Feed'), (stock, 'Stock')]:
    ids = [attrs['id'] for _, attrs in parser.records if attrs.get('id')]
    check(len(ids) == len(set(ids)), f'{label} render has no duplicate ids')

if failures:
    raise SystemExit(1)
print('All V1.1-D Dashboard render checks passed.')
