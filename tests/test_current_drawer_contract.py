#!/usr/bin/env python3
from pathlib import Path

from version_contract_utils import current_asset_revision

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def text(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


revision = current_asset_revision(ROOT)
calendar = text('public/js/calendar.js')
drawer = text('public/js/drawer-categories.js')
dashboard = text('public/js/dashboard.js')
settings = text('public/settings.php')

check(bool(revision), 'current asset revision is available for Drawer loading')
check(
    bool(revision) and "loadScript('./js/drawer-categories.js?v=" + revision + "');" in calendar,
    'Dashboard / Stock loads the Drawer organizer with the current asset revision',
)
check(
    "app_asset_url('js/drawer-categories.js')" in settings,
    'Settings loads the same Drawer organizer through the application asset helper',
)

for label in ['DISPLAY', 'FEED', 'PRODUCTIVITY', 'INFORMATION', 'MEDIA', 'GAME', 'SETTINGS', 'USER LINKS', 'ACCOUNT']:
    check("label: '" + label + "'" in drawer, f'current Drawer keeps section: {label}')

check(
    "children('.drawer-logout-form')" in drawer,
    'Drawer organization preserves the existing logout form instead of rebuilding it',
)
check(
    '.html(' not in drawer and 'innerHTML' not in drawer,
    'Drawer organization does not introduce raw HTML rendering',
)
check(
    'bootstrap.Offcanvas' not in drawer and 'bootstrap.Offcanvas' in dashboard,
    'Bootstrap Offcanvas ownership remains in the Dashboard controller',
)

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
