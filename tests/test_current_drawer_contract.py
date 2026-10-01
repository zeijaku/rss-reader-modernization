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
    bool(revision) and "loadScript(assetUrl('./js/drawer-categories.js'));" in calendar,
    'Dashboard / Stock loads the Drawer organizer through the centralized asset URL',
)
check(
    "app_asset_url('js/drawer-categories.js')" in settings,
    'Settings loads the same Drawer organizer through the application asset helper',
)

for label in ['表示', 'Widget追加', 'ファイル', '管理・設定', 'ユーザーリンク', 'アカウント']:
    check("label: '" + label + "'" in drawer, f'current Drawer keeps section: {label}')

for label in ['Feed', 'Information', 'Utility', 'Media', 'Game']:
    check("label: '" + label + "'" in drawer, f'Widget catalog keeps category: {label}')

check("./css/drawer-catalog.css" in drawer and 'assetRevision' in drawer,
      'catalog stylesheet inherits the PHP-versioned organizer asset revision')
check("'files': ['./file-library', './remote-files']" in drawer,
      'File Library / Remote Files retain their original URLs in the Files section')
check(all("'" + href + "'" in drawer for href in ['./rss-management', './settings#tabs', './settings#display', './settings#highlight']),
      'all management / settings entry URLs remain available in dev1')

css = text('public/css/drawer-catalog.css')
check('flex: 0 0 18px' in css and 'padding: 8px 12px' in css,
      'category arrows have a reserved width and inset padding')
check('.modal-' not in css and '.dashboard-widget' not in css,
      'catalog layout does not style modals or Dashboard cards')

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
