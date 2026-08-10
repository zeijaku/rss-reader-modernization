from __future__ import annotations

import base64
from html.parser import HTMLParser
from pathlib import Path
import subprocess
import tempfile
import textwrap

from version_test_utils import is_later_application_release, is_later_visible_label
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

final class V15bRenderStatement extends PDOStatement
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
            $this->rows = [
                [
                    'widget_id' => 21, 'widget_owner' => 1, 'widget_location' => 0,
                    'widget_type' => 'clock', 'widget_reference_id' => null,
                    'widget_sort_order' => 10, 'widget_width' => 1, 'widget_style' => 'primary',
                    'widget_config' => '{"schema":1,"title":"Clock One","hour_format":"24","show_seconds":false,"show_date":true}',
                    'widget_flag' => 0, 'widget_created_at' => '2026-08-06 07:00:00', 'widget_updated_at' => '2026-08-06 07:00:00',
                    'content_id' => null, 'content_date' => null, 'content_flag' => null, 'content_owner' => null,
                    'content_location' => null, 'content_style' => null, 'content_value' => null,
                    'memo_id' => null, 'memo_date' => null, 'memo_updated_at' => null, 'memo_flag' => null,
                    'memo_owner' => null, 'memo_title' => null, 'memo_body' => null,
                ],
                [
                    'widget_id' => 22, 'widget_owner' => 1, 'widget_location' => 0,
                    'widget_type' => 'clock', 'widget_reference_id' => null,
                    'widget_sort_order' => 20, 'widget_width' => 2, 'widget_style' => 'info',
                    'widget_config' => '{"schema":1,"title":"Clock Two","hour_format":"12","show_seconds":true,"show_date":false}',
                    'widget_flag' => 0, 'widget_created_at' => '2026-08-06 07:00:00', 'widget_updated_at' => '2026-08-06 07:00:00',
                    'content_id' => null, 'content_date' => null, 'content_flag' => null, 'content_owner' => null,
                    'content_location' => null, 'content_style' => null, 'content_value' => null,
                    'memo_id' => null, 'memo_date' => null, 'memo_updated_at' => null, 'memo_flag' => null,
                    'memo_owner' => null, 'memo_title' => null, 'memo_body' => null,
                ],
            ];
            return true;
        }
        if (str_contains($this->sql, 'FROM `ig_feed_keyword`')) return true;
        if (str_contains($this->sql, 'FROM ig_content_stock')) return true;
        throw new RuntimeException('Unexpected SQL: ' . $this->sql);
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
}
final class V15bRenderPDO extends PDO
{
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new V15bRenderStatement($query); }
}
set_db_connection_for_testing(new V15bRenderPDO());
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


with tempfile.TemporaryDirectory(prefix='v15b-render-') as temp:
    worker_path = Path(temp) / 'worker.php'
    worker_path.write_text(worker, encoding='utf-8')
    result = subprocess.run(['php', str(worker_path), str(ROOT)], cwd=ROOT, text=True, capture_output=True, timeout=30)

check(result.returncode == 0, 'Clock Timer Dashboard render exits successfully')
check(result.stderr.strip() == '', 'Clock Timer Dashboard render has no PHP warning')
html = base64.b64decode(result.stdout.strip()).decode('utf-8') if result.returncode == 0 else ''
parser = Parser()
parser.feed(html)

clock_widgets = [attrs for _, attrs in parser.records if attrs.get('data-dashboard-widget-type') == 'clock']
check(len(clock_widgets) == 2, 'multiple Clock Widgets render with Timer support')
check(all(attrs.get('role') == 'region' and attrs.get('aria-labelledby') for attrs in clock_widgets), 'Clock Timer Widgets remain named regions')
check('col-lg-6' in clock_widgets[1].get('class', '').split(), 'existing Clock width configuration remains effective')

view_triggers = [attrs for tag, attrs in parser.records if tag == 'button' and attrs.get('data-clock-view-trigger')]
check(len(view_triggers) == 4, 'each Clock renders Clock and Timer view controls')
check(sum(attrs.get('data-clock-view-trigger') == 'clock' for attrs in view_triggers) == 2, 'each Clock has one Clock view trigger')
check(sum(attrs.get('data-clock-view-trigger') == 'timer' for attrs in view_triggers) == 2, 'each Clock has one Timer view trigger')

presets = [attrs for tag, attrs in parser.records if tag == 'button' and 'clock-timer-preset' in attrs.get('class', '').split()]
check(len(presets) == 10, 'each Clock renders five Timer presets')
check({attrs.get('data-clock-timer-seconds') for attrs in presets} == {'60', '180', '300', '600', '1500'}, 'Timer presets use the approved durations')

inputs = [attrs for tag, attrs in parser.records if tag == 'input' and 'clock-timer-custom-minutes' in attrs.get('class', '').split()]
check(len(inputs) == 2, 'each Clock renders one custom minutes input')
check(all(attrs.get('min') == '1' and attrs.get('max') == '1440' and attrs.get('step') == '1' for attrs in inputs), 'custom minutes inputs have strict HTML bounds')

starts = [attrs for tag, attrs in parser.records if tag == 'button' and 'clock-timer-start' in attrs.get('class', '').split()]
pauses = [attrs for tag, attrs in parser.records if tag == 'button' and 'clock-timer-pause' in attrs.get('class', '').split()]
resets = [attrs for tag, attrs in parser.records if tag == 'button' and 'clock-timer-reset' in attrs.get('class', '').split()]
check(len(starts) == len(pauses) == len(resets) == 2, 'each Clock renders Start, Pause and Reset controls')

statuses = [attrs for tag, attrs in parser.records if tag == 'p' and 'clock-timer-status' in attrs.get('class', '').split()]
check(len(statuses) == 2 and all(attrs.get('aria-live') == 'polite' and attrs.get('aria-atomic') == 'true' for attrs in statuses), 'Timer status uses one atomic live region per Widget')
check(all(attrs.get('data-dashboard-swipe-ignore') == 'true' for _, attrs in parser.records if 'clock-timer-enabled' in attrs.get('class', '').split()), 'Timer body opts out of Dashboard swipe')
check('./css/clock-timer.css' in html and './js/clock-timer.js' in html, 'separate Clock Timer assets are included')
check('RSS Reader Modernization V1.5-' in ''.join(parser.text) or 'RSS Reader Modernization 1.5.0' in ''.join(parser.text) or 'RSS Reader Modernization V1.6-' in ''.join(parser.text) or 'RSS Reader Modernization 1.6.0' in ''.join(parser.text) or 'RSS Reader Modernization V1.7-' in ''.join(parser.text) or is_later_visible_label(''.join(parser.text), (1, 5, 0)), 'Dashboard displays a Version 1.5 or later marker')

ids = [attrs['id'] for _, attrs in parser.records if attrs.get('id')]
check(len(ids) == len(set(ids)), 'multiple Clock Timer Widgets have no duplicate IDs')

if failures:
    raise SystemExit(1)
print('All V1.5-B Dashboard render checks passed.')
