#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
checks = []


def text(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def check(condition: bool, message: str) -> None:
    ok = bool(condition)
    checks.append(ok)
    print(('PASS' if ok else 'FAIL') + ': ' + message)


def release_tuple(value: str) -> tuple[int, int, int]:
    match = re.fullmatch(r'(\d+)\.(\d+)\.(\d+)(?:-[A-Za-z0-9._-]+)?', value)
    return tuple(int(part) for part in match.groups()) if match else (0, 0, 0)


version = text('app/version.php')
calendar = text('public/js/calendar.js')
drawer = text('public/js/drawer-categories.js')
mobile_css = text('public/css/drawer-v121c.css')
visual_css = text('public/css/drawer-v121b.css')
dashboard_js = text('public/js/dashboard.js')
utility_js = text('public/js/utility-widgets.js')
readme = text('README.md')
changelog = text('CHANGELOG.md')
notes = text('RELEASE_NOTES.md')
apply_note = text('APPLY_NOTE_V1_21_0.md')
release_doc = text('docs/v1-21-0-final-release.md')

version_match = re.search(r"const APP_VERSION = '([^']+)';", version)
current_version = version_match.group(1) if version_match else ''
check(release_tuple(current_version) >= (1, 21, 0), 'Current application remains on V1.21 or a later formal release line')
check(f"const APP_VERSION_LABEL = 'RSS Reader Modernization {current_version}';" in version,
      'Visible release label matches the current application version')
revision_match = re.search(r"const APP_ASSET_REVISION = '([^']+)';", version)
active_revision = revision_match.group(1) if revision_match else ''
check(release_tuple(active_revision) >= (1, 21, 0), 'Asset revision remains on V1.21 or a later release line')
check(f'**Stable release:** `RSS Reader Modernization {current_version}`' in readme, 'README names the current formal release as stable')
check(f'Release tag: `v{current_version}`' in readme, 'README names the current formal release tag')
check('Version 1.21.0は' in readme, 'README retains the Version 1.21.0 release history')
check('## 1.21.0 - 2026-08-25' in changelog, 'CHANGELOG contains Version 1.21.0 entry')
check(f'# RSS Reader Modernization {current_version}' in notes and f'Release tag: `v{current_version}`' in notes,
      'Release Notes target the current formal release')
check('v1.21.0' in apply_note and 'DB Migrationはありません' in apply_note, 'Production apply note records the V1.21 tag and no DB migration')
check('v1.21.0' in release_doc and 'Version 1.20.1' in release_doc, 'V1.21 final release document records baseline and target')

check("loadScript('./js/drawer-categories.js?v=" + active_revision + "');" in calendar, 'Dashboard loader uses the active Drawer cache key')
check('./css/drawer-v121b.css?v=1.21.0' in drawer, 'Unchanged V1.21 Drawer visual layer keeps the formal cache key')
check('./css/drawer-v121c.css?v=1.21.0' in drawer, 'Unchanged V1.21 Drawer Smartphone layer keeps the formal cache key')
check('1.21-c3' not in version + calendar + drawer, 'Checkpoint C cache key is absent from formal/runtime loader')
check('1.21-b1' not in drawer, 'Checkpoint B cache key is absent from formal/runtime loader')

expected_order = ["'display'", "'feed'", "'productivity'", "'information'", "'media'", "'game'", "'settings'", "'user-links'", "'account'"]
pos = [drawer.find(item, drawer.find('var sectionOrder')) for item in expected_order]
check(all(value >= 0 for value in pos) and pos == sorted(pos), 'Final Drawer section order remains stable')
check("'productivity': ['#registerTaskWidget', '#registerCalendarWidget', '#registerMemo', '#registerClock', '#registerMailWidget']" in drawer, 'Mail remains in PRODUCTIVITY')
check("'media': ['#registerCameraVideo']" in drawer, 'Camera / Video remains in MEDIA')
check("mobileOnly: true" in drawer and "'user-links'" in drawer, 'Configured user links remain Smartphone-only')
check('window.setTimeout(organizeDrawer, 0)' in drawer, 'Dynamic Mail / Camera insertion remains compatible')

check('background-color: #f6f7f9' in visual_css, 'Drawer keeps the restrained light-gray surface')
check('border-left-color: #0d6efd' in visual_css, 'Current item keeps the single blue left indicator')
check('.drawer-section-title' in visual_css and 'background-color: #eef2f6' in visual_css, 'Section headers keep neutral visual hierarchy')
check('#drawerMenu .widget-catalog-toggle' in mobile_css and 'padding-right: 12px' in mobile_css, 'Smartphone Widget Catalog chevron remains inset')
check('min-height: 44px' in mobile_css, 'Smartphone touch targets remain at least 44px')
check('fa-chevron-right widget-catalog-chevron' in utility_js, 'Accordion chevron source remains the existing Widget Catalog implementation')

check('bootstrap.Offcanvas.getOrCreateInstance(drawerElement)' in dashboard_js, 'Bootstrap 5 Offcanvas remains the Drawer controller')
check("hidden.bs.offcanvas" in dashboard_js and 'bootstrap.Modal.getOrCreateInstance(nextModal.element).show' in dashboard_js, 'Drawer-to-Modal transition still waits for Offcanvas hidden')
check('bootstrap.Offcanvas' not in drawer, 'Drawer organizer does not replace Bootstrap behavior')

for tool in (
    'tools/build_release_package.py',
    'tools/verify_release_package.py',
    'tools/build_complete_package.py',
    'tools/verify_complete_package.py',
):
    body = text(tool)
    check(current_version in body and '1.20.1' not in body, f'{tool} targets the current formal release')

migration_names = [p.name for p in (ROOT / 'database').rglob('*.sql') if 'v1_21' in p.name.lower() or 'v121' in p.name.lower()]
check(migration_names == [], 'Version 1.21 adds no database migration')
check(not (ROOT / 'config/local.php').exists(), 'Repository does not contain config/local.php')
check('File Upload' in release_doc and 'Imgur' in release_doc and 'Grid' in release_doc, 'Deferred features remain documented outside Version 1.21 scope')

passed = sum(checks)
failed = len(checks) - passed
print(f'RESULT: PASS {passed} / FAIL {failed}')
sys.exit(1 if failed else 0)
