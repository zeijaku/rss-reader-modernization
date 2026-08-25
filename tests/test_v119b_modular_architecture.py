#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
checks: list[tuple[str, bool]] = []

def check(name: str, condition: bool) -> None:
    checks.append((name, bool(condition)))

def text(rel: str) -> str:
    return (ROOT / rel).read_text(encoding='utf-8')

api_facade = text('app/api.php')
dash_facade = text('app/dashboard_widget.php')
public_api = text('public/api_v1.php')
version = text('app/version.php')

api_modules = {
    'content': 'app/api/content.php',
    'dashboard': 'app/api/dashboard.php',
    'account': 'app/api/account.php',
    'integrations': 'app/api/integrations.php',
}
dash_modules = {
    'feed': 'app/dashboard/feed_widgets.php',
    'personal': 'app/dashboard/personal_widgets.php',
    'utility': 'app/dashboard/utility_widgets.php',
}

for label, rel in api_modules.items():
    check(f'API {label} module exists', (ROOT / rel).is_file())
    check(f'API facade loads {label} module', f"require_once __DIR__ . '/api/{Path(rel).name}';" in api_facade)
for label, rel in dash_modules.items():
    check(f'Dashboard {label} module exists', (ROOT / rel).is_file())
    check(f'Dashboard facade loads {label} module', f"require_once __DIR__ . '/dashboard/{Path(rel).name}';" in dash_facade)

check('API dispatcher remains in stable facade', 'function api_dispatch(' in api_facade)
check('public API entry still loads stable API facade', "require_once dirname(__DIR__) . '/app/api.php';" in public_api)
check('API action table still routes content create', "'content.create' => api_content_create" in api_facade)
check('API action table still routes widget reorder', "'widget.reorder' => api_widget_reorder" in api_facade)
check('API action table still routes account password update', "'account.password.update' => api_account_password_update" in api_facade)
check('API action table still routes X timeline fetch', "'x.timeline.fetch' => api_x_timeline_fetch" in api_facade)

api_content = text(api_modules['content'])
api_dashboard = text(api_modules['dashboard'])
api_account = text(api_modules['account'])
api_integrations = text(api_modules['integrations'])
check('content group owns Feed/Stock handlers', 'function api_feed_fetch(' in api_content and 'function api_stock_create(' in api_content)
check('dashboard group owns reorder/local Widget handlers', 'function api_widget_reorder(' in api_dashboard and 'function api_widget_memo_create(' in api_dashboard)
check('account group owns Settings/Account handlers', 'function api_settings_update(' in api_account and 'function api_account_password_update(' in api_account)
check('integrations group owns external/information handlers', 'function api_weather_forecast(' in api_integrations and 'function api_x_timeline_fetch(' in api_integrations)

feed = text(dash_modules['feed'])
personal = text(dash_modules['personal'])
utility = text(dash_modules['utility'])
check('Feed persistence moved as a broad group', 'function dashboard_widget_create_feed(' in feed and 'function dashboard_widget_delete_feed(' in feed)
check('Memo/Task persistence moved together', 'function dashboard_widget_create_memo(' in personal and 'function dashboard_widget_create_task_item(' in personal)
check('Clock/Calculator/Blind Spot persistence moved together', 'function dashboard_widget_create_clock(' in utility and 'function dashboard_widget_create_calculator(' in utility and 'function dashboard_widget_create_blind_spot(' in utility)
check('generic ownership lock remains in Dashboard facade', 'function dashboard_widget_lock_owned_widget(' in dash_facade)
check('generic reorder remains in Dashboard facade', 'function dashboard_widget_reorder(' in dash_facade)
check('generic public projection remains in Dashboard facade', 'function dashboard_widget_public_list(' in dash_facade)

# Structural phase intentionally does not add a public endpoint or migration.
check('no V1.19 API PHP file is placed under public', not (ROOT / 'public/api').exists() and not (ROOT / 'public/dashboard').exists())
check('V1.19 architecture remains valid on the V1.19 or later release line', re.search(r"const APP_VERSION = '(?:1\.19\.0(?:-rc[1-9][0-9]*)?|1\.20\.[0-9]+(?:-rc[1-9][0-9]*)?|1\.21\.0)';", version) is not None)
check('V1.19-B adds no V1.19 DB migration', not any(re.search(r'1[_\.-]19', p.name, re.I) for p in (ROOT / 'database/migrations').glob('*')) if (ROOT / 'database/migrations').is_dir() else True)

# Modules are implementation-only and must not create a dependency back to public entrypoints.
for rel in list(api_modules.values()) + list(dash_modules.values()):
    source = text(rel)
    check(f'{rel} does not include public entrypoints', "'/public/" not in source and 'public/api_v1.php' not in source)

failed = [name for name, ok in checks if not ok]
for name, ok in checks:
    print(('PASS' if ok else 'FAIL') + ': ' + name)
print(f'RESULT: PASS {len(checks)-len(failed)} / FAIL {len(failed)} / SKIP 0')
sys.exit(1 if failed else 0)
