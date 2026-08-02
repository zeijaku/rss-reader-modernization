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
$GLOBALS['m2d_render_mode'] = $mode;
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql');
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

final class M2dRenderStatement extends PDOStatement
{
    private array $rows = [];
    public function __construct(private string $sql) {}
    public function execute(?array $params = null): bool
    {
        $mode = (string) ($GLOBALS['m2d_render_mode'] ?? 'feed');
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
        if (str_contains($this->sql, 'FROM ig_content ')) {
            $this->rows = [];
            if ($mode === 'feed') {
                for ($i = 1; $i <= 8; $i++) {
                    $this->rows[] = [
                        'content_id' => $i, 'content_owner' => 1, 'content_location' => 0,
                        'content_style' => $i % 2 === 0 ? 'info' : 'success',
                        'content_value' => 'https://example.com/feed' . $i . '.xml', 'content_flag' => 0,
                    ];
                }
            }
            return true;
        }
        if (str_contains($this->sql, 'FROM ig_content_stock')) {
            $this->rows = [];
            if ($mode === 'stock') {
                for ($i = 1; $i <= 5; $i++) {
                    $this->rows[] = [
                        'stock_data' => 'https://example.com/path/' . str_repeat('very-long-segment-', 8) . $i,
                        'stock_title' => '非常に長いStockタイトル' . str_repeat('テスト', 24) . $i,
                        'stock_date' => '2026-08-01 23:30:0' . $i,
                    ];
                }
            }
            return true;
        }
        throw new RuntimeException('Unexpected SQL: ' . $this->sql);
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
}
final class M2dRenderPDO extends PDO
{
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new M2dRenderStatement($query); }
}
set_db_connection_for_testing(new M2dRenderPDO());
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
        self.stack: list[tuple[str, dict[str, str]]] = []
        self.records: list[tuple[str, dict[str, str], list[tuple[str, dict[str, str]]]]] = []
        self.text: list[str] = []
    def handle_starttag(self, tag: str, attrs) -> None:
        attr_map = {str(k): '' if v is None else str(v) for k, v in attrs}
        self.records.append((tag, attr_map, list(self.stack)))
        if tag not in {'area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr'}:
            self.stack.append((tag, attr_map))
    def handle_endtag(self, tag: str) -> None:
        for i in range(len(self.stack)-1, -1, -1):
            if self.stack[i][0] == tag:
                del self.stack[i:]
                return
    def handle_data(self, data: str) -> None:
        self.text.append(data)

def render(mode: str) -> tuple[Parser, str]:
    with tempfile.TemporaryDirectory(prefix='m2d-render-') as temp:
        worker_path = Path(temp) / 'worker.php'
        worker_path.write_text(worker, encoding='utf-8')
        result = subprocess.run(['php', str(worker_path), str(ROOT), mode], cwd=ROOT, text=True, capture_output=True, check=False)
    check(result.returncode == 0, f'{mode} Dashboard render exits successfully')
    check(result.stderr.strip() == '', f'{mode} Dashboard render has no PHP warning')
    html = base64.b64decode(result.stdout.strip()).decode('utf-8') if result.returncode == 0 else ''
    parser = Parser(); parser.feed(html)
    return parser, html

def has_class(attrs: dict[str, str], name: str) -> bool:
    return name in attrs.get('class', '').split()

feed, feed_html = render('feed')
feed_records = feed.records
feed_text = ''.join(feed.text)
feed_cards = [(tag, attrs, ancestors) for tag, attrs, ancestors in feed_records if attrs.get('data-feed-content-id')]
check(len(feed_cards) == 8, 'Feed render contains eight cards')
check(all(tag == 'section' and all(has_class(attrs, name) for name in ['col-12','col-md-6','col-lg-3','feed-card']) for tag, attrs, _ in feed_cards), 'all Feed cards use responsive 1/2/4 column classes')
check(len([1 for tag, attrs, _ in feed_records if tag == 'div' and has_class(attrs, 'feed-grid')]) == 1, 'Feed cards share one grid row')
check(len([1 for tag, attrs, _ in feed_records if tag == 'div' and has_class(attrs, 'feed-card-inner')]) == 8, 'each Feed card has a stable inner surface')
check(len([1 for tag, attrs, _ in feed_records if tag == 'table' and has_class(attrs, 'feed-table')]) == 8, 'each Feed card uses fixed-layout table class')
check(len([1 for tag, attrs, _ in feed_records if tag == 'colgroup']) == 8, 'each Feed table renders a column group')
check(len([1 for tag, attrs, _ in feed_records if tag == 'col' and has_class(attrs, 'feed-stock-column')]) == 8, 'each Feed table renders the 44px Stock column hook')
check(any(tag == 'div' and attrs.get('id') == 'app-notice' and 'hidden' in attrs for tag, attrs, _ in feed_records), 'Dashboard renders the hidden shared notice')
check(any(tag == 'button' and has_class(attrs, 'delete_content') and attrs.get('type') == 'button' for tag, attrs, _ in feed_records), 'RSS edit modal renders explicit delete button')
check('追加先：' in feed_text and 'Base' in feed_text, 'RSS add modal displays the destination tab')
check('Release M4-C / R1' in feed_text, 'Feed Dashboard displays current version')

stock, stock_html = render('stock')
stock_records = stock.records
stock_text = ''.join(stock.text)
stock_cards = [(tag, attrs, ancestors) for tag, attrs, ancestors in stock_records if tag == 'article' and has_class(attrs, 'stock-card')]
check(len(stock_cards) == 5, 'Stock render contains five cards')
check(all(all(has_class(attrs, name) for name in ['col-12','col-md-6','col-lg-3']) for _, attrs, _ in stock_cards), 'all Stock cards use responsive 1/2/4 column classes')
check(len([1 for tag, attrs, _ in stock_records if tag == 'div' and has_class(attrs, 'stock-grid')]) == 1, 'Stock cards share one grid row')
check(len([1 for tag, attrs, _ in stock_records if tag == 'small' and has_class(attrs, 'stock-title')]) == 5, 'Stock titles use wrapping hook')
check('追加先：' in stock_text and 'Base' in stock_text, 'Stock page makes tab-1 RSS destination visible')
check('Release M4-C / R1' in stock_text, 'Stock Dashboard displays current version')

for parser, label in [(feed, 'Feed'), (stock, 'Stock')]:
    ids = [attrs['id'] for _, attrs, _ in parser.records if attrs.get('id')]
    check(len(ids) == len(set(ids)), f'{label} render has no duplicate ids')

if failures:
    raise SystemExit(1)
print('All M2-D Dashboard render checks passed.')
