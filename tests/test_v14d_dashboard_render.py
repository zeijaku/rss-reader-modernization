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
$_SERVER['REQUEST_URI'] = '/?tab=0';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '80';
$_GET['tab'] = '0';
require $root . '/app/bootstrap.php';

final class V14dRenderStatement extends PDOStatement
{
    private array $rows = [];
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
        if (str_contains($this->sql, 'FROM `ig_dashboard_widget` w')) {
            if ((int) ($params[':owner'] ?? 0) !== 1 || (int) ($params[':location'] ?? -1) !== 0) {
                return true;
            }
            $base = [
                'widget_owner' => 1, 'widget_location' => 0, 'widget_reference_id' => null,
                'widget_flag' => 0, 'widget_created_at' => '2026-08-05 20:00:00', 'widget_updated_at' => '2026-08-05 20:00:00',
                'content_id' => null, 'content_date' => null, 'content_flag' => null, 'content_owner' => null,
                'content_location' => null, 'content_style' => null, 'content_value' => null,
                'memo_id' => null, 'memo_date' => null, 'memo_updated_at' => null, 'memo_flag' => null,
                'memo_owner' => null, 'memo_title' => null, 'memo_body' => null,
            ];
            $this->rows = [
                array_merge($base, [
                    'widget_id' => 31, 'widget_type' => 'game', 'widget_sort_order' => 10,
                    'widget_width' => 1, 'widget_style' => 'secondary',
                    'widget_config' => '{"schema":1,"title":"<Quest & One>","game":"icon_quest"}',
                ]),
                array_merge($base, [
                    'widget_id' => 11, 'widget_type' => 'feed', 'widget_reference_id' => 101,
                    'widget_sort_order' => 20, 'widget_width' => 1, 'widget_style' => 'success',
                    'widget_config' => null, 'content_id' => 101, 'content_date' => '2026-08-05 20:00:00',
                    'content_flag' => 0, 'content_owner' => 1, 'content_location' => 0,
                    'content_style' => 'success', 'content_value' => 'https://example.com/feed.xml',
                ]),
                array_merge($base, [
                    'widget_id' => 32, 'widget_type' => 'game', 'widget_sort_order' => 30,
                    'widget_width' => 2, 'widget_style' => 'primary',
                    'widget_config' => '{"schema":1,"title":"Quest Two","game":"icon_quest"}',
                ]),
            ];
            return true;
        }
        if (str_contains($this->sql, 'FROM ig_content_stock')) {
            return true;
        }
        throw new RuntimeException('Unexpected SQL: ' . $this->sql);
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
}
final class V14dRenderPDO extends PDO
{
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new V14dRenderStatement($query); }
}
set_db_connection_for_testing(new V14dRenderPDO());
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

with tempfile.TemporaryDirectory(prefix='v14d-render-') as temp:
    worker_path = Path(temp) / 'worker.php'
    worker_path.write_text(worker, encoding='utf-8')
    result = subprocess.run(['php', str(worker_path), str(ROOT)], cwd=ROOT, text=True, capture_output=True, timeout=30)

check(result.returncode == 0, 'mixed Feed and Game Dashboard render exits successfully')
check(result.stderr.strip() == '', 'mixed Dashboard render has no PHP warning')
html = base64.b64decode(result.stdout.strip()).decode('utf-8') if result.returncode == 0 else ''
parser = Parser()
parser.feed(html)
widgets = [(tag, attrs) for tag, attrs in parser.records if attrs.get('data-dashboard-widget-id')]
check(len(widgets) == 3, 'Dashboard renders Feed and Game Widgets together')
check([attrs.get('data-dashboard-widget-id') for _, attrs in widgets] == ['31', '11', '32'], 'mixed Widget output follows widget_sort_order')
check([attrs.get('data-dashboard-widget-type') for _, attrs in widgets] == ['game', 'feed', 'game'], 'mixed Widget types remain explicit')
games = [attrs for _, attrs in widgets if attrs.get('data-dashboard-widget-type') == 'game']
check(len(games) == 2, 'multiple Game Widgets render in one tab')
check(all(attrs.get('data-dashboard-swipe-ignore') == 'true' for attrs in games), 'each Game Widget prevents Dashboard Tab swipe conflicts')
check(all(attrs.get('role') == 'region' and attrs.get('aria-labelledby') for attrs in games), 'each Game Widget is a named region')
check('col-lg-6' in games[1].get('class', '').split(), 'Game width=2 uses the existing Widget width class')
main = next((attrs for tag, attrs in parser.records if tag == 'main' and attrs.get('id') == 'main-content'), {})
check(main.get('data-dashboard-user-id') == '1', 'Dashboard exposes authenticated User ID for Storage namespacing')
boards = [attrs for _, attrs in parser.records if 'mini-game-board' in attrs.get('class', '').split()]
check(len(boards) == 2 and all(attrs.get('role') == 'grid' for attrs in boards), 'each Game Widget renders one accessible 5x5 board')
cells = [attrs for tag, attrs in parser.records if tag == 'button' and 'mini-game-cell' in attrs.get('class', '').split()]
check(len(cells) == 50, 'two Game Widgets render 25 cells each')
check(sum(1 for attrs in cells if attrs.get('tabindex') == '0') == 2, 'each initial board has one roving focus entry point')
check(all(attrs.get('aria-rowindex') and attrs.get('aria-colindex') and attrs.get('aria-label') for attrs in cells), 'all cells expose row, column and text labels')
icon_classes = ' '.join(attrs.get('class', '') for _, attrs in parser.records if attrs.get('class'))
check('fa-user-shield' in icon_classes and 'fa-skull-crossbones' in icon_classes and 'fa-gem' in icon_classes and 'fa-door-open' in icon_classes, 'Font Awesome Player, Enemy, Treasure and Goal icons render')
check('&lt;Quest &amp; One&gt;' in html and '<Quest & One>' not in html, 'Game title is escaped in raw HTML')
check('id="registerGameWidgetForm"' in html and 'id="changeGameWidgetForm"' in html, 'Game add and edit modals render')
check('Game追加' in ''.join(parser.text), 'Drawer contains the Game add action')
check('./css/mini-game.css' in html and './js/mini-game.js' in html, 'separate Mini Game assets are included')
check(html.count('data-mini-game-cell-index=') == 50, 'every Game cell has a stable runtime index')
check(html.count('mini-game-new-game') == 2 and html.count('mini-game-reset') == 2, 'each Game renders New Game and Reset')
check(html.count('data-mini-game-direction=') == 8, 'each Game renders four direction controls')
check(html.count('mini-game-tutorial-toggle') == 2 and html.count('mini-game-storage-reset') == 2, 'each Game renders Tutorial and record reset controls')
check(html.count('mini-game-result') >= 2 and html.count('mini-game-wins') == 2 and html.count('mini-game-losses') == 2, 'each Game renders result and win/loss status')
check(html.count('aria-atomic="true"') >= 2, 'each Game live status is atomic')
check(main.get('data-dashboard-theme') == 'bootstrap', 'Dashboard exposes current Theme for Game styling')
tutorial_ids=[attrs.get('id') for _,attrs in parser.records if 'mini-game-tutorial' in attrs.get('class','').split()]
controls=[attrs.get('aria-controls') for _,attrs in parser.records if 'mini-game-tutorial-toggle' in attrs.get('class','').split()]
check(len(tutorial_ids)==2 and set(tutorial_ids)==set(controls), 'Tutorial buttons reference unique Widget panels')
check('Mock盤面' not in html and '盤面操作はV1.4-Cで実装' not in html, 'Mock-only guidance is removed')
ids = [attrs['id'] for _, attrs in parser.records if attrs.get('id')]
check(len(ids) == len(set(ids)), 'mixed Dashboard render has no duplicate ids')

if failures:
    raise SystemExit(1)
print('All V1.4-D Dashboard render checks passed.')
