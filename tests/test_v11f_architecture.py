from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


widget = (ROOT / 'app/dashboard_widget.php').read_text(encoding='utf-8')
api = (ROOT / 'app/api.php').read_text(encoding='utf-8')
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
js = (ROOT / 'public/js/dashboard.js').read_text(encoding='utf-8')
css = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
schema = (ROOT / 'database/schema.sql').read_text(encoding='utf-8')
migrations = '\n'.join(p.name for p in (ROOT / 'database/migrations').iterdir())
run = (ROOT / 'tests/run.sh').read_text(encoding='utf-8')
local_run = (ROOT / 'tests/run-local-v1-1-f.sh').read_text(encoding='utf-8') if (ROOT / 'tests/run-local-v1-1-f.sh').exists() else ''

check('dashboard_widget_clock_defaults' in widget, 'Clock settings have explicit safe defaults')
check('dashboard_widget_validate_clock_title' in widget and '32' in widget, 'Clock title has a bounded text validator')
check("['12', '24']" in widget, 'Clock hour format is restricted to 12 or 24 hours')
check('dashboard_widget_validate_boolean' in widget, 'Clock checkbox values are normalized explicitly')
check('dashboard_widget_clock_config_from_storage' in widget and 'dashboard_widget_decode_config' in widget, 'stored Clock config is decoded through the bounded Widget JSON helper')
check("$type === 'clock'" in widget and 'dashboard_widget_clock_config_from_storage' in widget, 'Clock rows receive normalized config during DB row normalization')
check('dashboard_widget_create_clock' in widget and 'dashboard_widget_update_clock' in widget and 'dashboard_widget_delete_clock' in widget, 'Clock CRUD is implemented in the Widget domain layer')
check("widget_reference_id, widget_sort_order" in widget and "'clock', NULL" in widget, 'Clock uses the existing dashboard_widget table without another reference table')
check('dashboard_widget_next_sort_order' in widget, 'new Clock appends after the current Widget order')
check('dashboard_widget_lock_owned_widget' in widget and 'FOR UPDATE' in widget, 'Clock update/delete locks an owner-scoped active Widget')
check('beginTransaction' in widget and 'rollBack' in widget and 'commit' in widget, 'Clock writes are transactional')

check("'widget.clock.create' => api_widget_clock_create" in api, 'Clock create API is registered')
check("'widget.clock.update' => api_widget_clock_update" in api, 'Clock update API is registered')
check("'widget.clock.delete' => api_widget_clock_delete" in api, 'Clock delete API is registered')
check('dashboard_widget_create_clock($userId' in api and 'dashboard_widget_update_clock($userId' in api and 'dashboard_widget_delete_clock($userId' in api, 'Clock owner comes only from the authenticated user')
check("$input['widget_owner']" not in api, 'Clock API does not trust a client owner field')
check('Clock Widget settings are invalid.' in api and 'api_validation_error' in api, 'invalid Clock settings are rejected before mutation')
check('dashboard_widget_unavailable' in api and '503' in api, 'Clock DB failure has a structured service-unavailable response')

check('data-dashboard-widget-type="clock"' in index, 'Clock renders as a Dashboard Widget')
check('data-clock-hour-format' in index and 'data-clock-show-seconds' in index and 'data-clock-show-date' in index, 'Clock display settings are exposed as constrained data attributes')
check('class="clock-time"' in index and 'class="clock-date"' in index, 'Clock has semantic time and date output hooks')
check('class="btn btn-link widget-drag-handle"' in index and 'clock-edit-trigger' in index, 'Clock supports the existing reorder handle and a separate edit control')
check('id="registerClockForm"' in index and 'id="changeClockForm"' in index, 'Clock add and edit forms are explicit')
check('Clock追加' in index and 'data-target="#registerClock"' in index, 'Drawer exposes Clock addition without changing the four tabs')
check('app_html($clockTitle)' in index, 'Clock title is escaped at HTML output')

check("apiRequest('widget.clock.create'" in js and "apiRequest('widget.clock.update'" in js and "apiRequest('widget.clock.delete'" in js, 'Frontend uses the protected API for all Clock mutations')
check("new Intl.DateTimeFormat" in js and "hour12: hourFormat === '12'" in js, 'Clock uses Browser locale with the configured hour format')
check("window.setInterval(updateClocks, 1000)" in js, 'Clock display updates continuously from one shared timer')
check("attr('datetime', now.toISOString())" in js, 'Clock time element receives a machine-readable datetime')
check("find('.widget-title-text')" in js, 'generic reorder preview supports both Feed and Clock titles')
check("initClocks();" in js, 'Clock initialization is part of the existing Dashboard startup')

check('.clock-card-inner' in css and '.clock-card-header' in css and '.clock-card-body' in css, 'Clock card has bounded Dashboard styling')
check('font-variant-numeric: tabular-nums' in css, 'Clock digits use stable tabular spacing')
check('min-height: calc(13rem - 44px)' in css, 'Clock aligns with the existing Feed card height')
check('@media (max-width: 767.98px)' in css and 'clock-card-body' in css, 'Clock has a mobile layout rule')
check('clock' not in ''.join((ROOT / 'database' / 'migrations' / name).read_text(encoding='utf-8').lower() for name in migrations.splitlines() if name.startswith('004_')), 'V1.1-F Clock remains independent from later Memo migration')
check('`widget_config` TEXT NULL' in schema, 'new installs already contain the generic config storage used by Clock')
check(re.search(r"const APP_VERSION = '1\.1\.0-dev\.[5-9][0-9]*';", version) is not None and ('V1.1-F / R1' in version or 'V1.1-G / R1' in version), 'visible Version marker is V1.1-F or a later V1.1 checkpoint')
check('test_v11f_clock_widget.php' in run and 'test_v11f_frontend_runtime.js' in run, 'main regression runner includes V1.1-F checks')
check('test_v11f_clock_widget.php' in local_run and 'test_v11f_frontend_runtime.js' in local_run, 'local V1.1-F runner includes focused checks')

if not all(checks):
    raise SystemExit(f'{checks.count(False)}/{len(checks)} V1.1-F architecture checks failed')
print(f'All {len(checks)} V1.1-F architecture checks passed.')
