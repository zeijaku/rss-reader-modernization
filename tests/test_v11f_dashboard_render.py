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

final class V11fRenderStatement extends PDOStatement
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
            $this->rows = [
                [
                    'widget_id' => 21, 'widget_owner' => 1, 'widget_location' => 0,
                    'widget_type' => 'clock', 'widget_reference_id' => null,
                    'widget_sort_order' => 10, 'widget_width' => 1, 'widget_style' => 'primary',
                    'widget_config' => '{"schema":1,"title":"<Clock & Test>","hour_format":"24","show_seconds":false,"show_date":true}',
                    'widget_flag' => 0, 'widget_created_at' => '2026-08-02 10:00:00', 'widget_updated_at' => '2026-08-02 10:00:00',
                    'content_id' => null, 'content_date' => null, 'content_flag' => null, 'content_owner' => null,
                    'content_location' => null, 'content_style' => null, 'content_value' => null,
                ],
                [
                    'widget_id' => 11, 'widget_owner' => 1, 'widget_location' => 0,
                    'widget_type' => 'feed', 'widget_reference_id' => 101,
                    'widget_sort_order' => 20, 'widget_width' => 1, 'widget_style' => 'success',
                    'widget_config' => null, 'widget_flag' => 0,
                    'widget_created_at' => '2026-08-02 10:00:00', 'widget_updated_at' => '2026-08-02 10:00:00',
                    'content_id' => 101, 'content_date' => '2026-08-02 10:00:00', 'content_flag' => 0, 'content_owner' => 1,
                    'content_location' => 0, 'content_style' => 'success', 'content_value' => 'https://example.com/feed.xml',
                ],
                [
                    'widget_id' => 22, 'widget_owner' => 1, 'widget_location' => 0,
                    'widget_type' => 'clock', 'widget_reference_id' => null,
                    'widget_sort_order' => 30, 'widget_width' => 2, 'widget_style' => 'info',
                    'widget_config' => '{"schema":1,"title":"Seconds","hour_format":"12","show_seconds":true,"show_date":false}',
                    'widget_flag' => 0, 'widget_created_at' => '2026-08-02 10:00:00', 'widget_updated_at' => '2026-08-02 10:00:00',
                    'content_id' => null, 'content_date' => null, 'content_flag' => null, 'content_owner' => null,
                    'content_location' => null, 'content_style' => null, 'content_value' => null,
                ],
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
final class V11fRenderPDO extends PDO
{
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new V11fRenderStatement($query); }
}
set_db_connection_for_testing(new V11fRenderPDO());
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


with tempfile.TemporaryDirectory(prefix='v11f-render-') as temp:
    worker_path = Path(temp) / 'worker.php'
    worker_path.write_text(worker, encoding='utf-8')
    result = subprocess.run(
        ['php', str(worker_path), str(ROOT)],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=False,
        timeout=30,
    )

check(result.returncode == 0, 'mixed Feed and Clock Dashboard render exits successfully')
check(result.stderr.strip() == '', 'mixed Dashboard render has no PHP warning')
html = base64.b64decode(result.stdout.strip()).decode('utf-8') if result.returncode == 0 else ''
parser = Parser()
parser.feed(html)

widgets = [(tag, attrs) for tag, attrs in parser.records if attrs.get('data-dashboard-widget-id')]
check(len(widgets) == 3, 'Dashboard renders Feed and Clock Widgets together')
check([attrs.get('data-dashboard-widget-id') for _, attrs in widgets] == ['21', '11', '22'], 'mixed Widget output follows widget_sort_order')
check([attrs.get('data-dashboard-widget-type') for _, attrs in widgets] == ['clock', 'feed', 'clock'], 'mixed Widget types remain explicit')
clock_widgets = [attrs for _, attrs in widgets if attrs.get('data-dashboard-widget-type') == 'clock']
check(len(clock_widgets) == 2, 'multiple Clock Widgets can render in one tab')
check(clock_widgets[0].get('data-clock-hour-format') == '24' and clock_widgets[0].get('data-clock-show-date') == '1', 'first Clock exposes 24-hour date settings')
check(clock_widgets[1].get('data-clock-hour-format') == '12' and clock_widgets[1].get('data-clock-show-seconds') == '1', 'second Clock exposes 12-hour seconds settings')
check('col-lg-6' in clock_widgets[1].get('class', '').split(), 'Clock width=2 uses the existing Widget width class')
check(all(attrs.get('role') == 'region' and attrs.get('aria-labelledby') for attrs in clock_widgets), 'Clock Widgets are named regions')
check(len([1 for tag, attrs in parser.records if tag == 'time' and 'clock-time' in attrs.get('class', '').split()]) == 2, 'each Clock renders a semantic time element')
check(len([1 for _, attrs in parser.records if 'clock-edit-trigger' in attrs.get('class', '').split()]) == 2, 'each Clock has a separate edit control')
check(len([1 for _, attrs in parser.records if 'widget-drag-handle' in attrs.get('class', '').split()]) >= 3, 'Feed and Clock share the existing reorder handle')
check('&lt;Clock &amp; Test&gt;' in html and '<Clock & Test>' not in html, 'Clock title is escaped in raw HTML')
check('id="registerClockForm"' in html and 'id="changeClockForm"' in html, 'Clock add and edit modals render')
check('Clock追加' in ''.join(parser.text), 'Drawer contains the Clock add action')
check('RSS Reader Modernization V1.1-' in ''.join(parser.text) or 'RSS Reader Modernization 1.1.0' in ''.join(parser.text) or 'RSS Reader Modernization 1.2.0-dev.1' in ''.join(parser.text), 'Dashboard displays a V1.1 Version marker')

ids = [attrs['id'] for _, attrs in parser.records if attrs.get('id')]
check(len(ids) == len(set(ids)), 'mixed Dashboard render has no duplicate ids')

if failures:
    raise SystemExit(1)
print('All V1.1-F Dashboard render checks passed.')
