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

check("const APP_VERSION = '1.21.0';" in version, 'Formal APP_VERSION remains 1.21.0 during V1.22 checkpoints')
check("const APP_VERSION_LABEL = 'RSS Reader Modernization 1.21.0';" in version, 'Formal release label remains 1.21.0 during V1.22 checkpoints')
revision_match = re.search(r"const APP_ASSET_REVISION = '([^']+)';", version)
active_revision = revision_match.group(1) if revision_match else ''
check(active_revision == '1.21.0' or re.fullmatch(r'1\.22\.0(?:-[A-Za-z0-9._-]+)?', active_revision) is not None,
      'Asset revision is the formal V1.21 key or a V1.22 checkpoint/final key')
check('**Stable release:** `RSS Reader Modernization 1.21.0`' in readme, 'README names Version 1.21.0 as stable')
check('Release tag: `v1.21.0`' in readme, 'README names the final tag')
check('## 1.21.0 - 2026-08-25' in changelog, 'CHANGELOG contains Version 1.21.0 entry')
check('# RSS Reader Modernization 1.21.0' in notes, 'Release Notes target Version 1.21.0')
check('v1.21.0' in apply_note and 'DB Migrationはありません' in apply_note, 'Production apply note records tag and no DB migration')
check('v1.21.0' in release_doc and 'Version 1.20.1' in release_doc, 'Final release document records baseline and target')

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
    check('1.21.0' in body and '1.20.1' not in body, f'{tool} retains the formal Version 1.21.0 release target')

migration_names = [p.name for p in (ROOT / 'database').rglob('*.sql') if 'v1_21' in p.name.lower() or 'v121' in p.name.lower()]
check(migration_names == [], 'Version 1.21 adds no database migration')
check(not (ROOT / 'config/local.php').exists(), 'Repository does not contain config/local.php')
check('File Upload' in release_doc and 'Imgur' in release_doc and 'Grid' in release_doc, 'Deferred features remain documented outside Version 1.21 scope')

passed = sum(checks)
failed = len(checks) - passed
print(f'RESULT: PASS {passed} / FAIL {failed}')
sys.exit(1 if failed else 0)
