#!/usr/bin/env python3
from pathlib import Path
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
css = text('public/css/drawer-v121b.css')
base_css = text('public/css/dashboard.css')

check("const APP_VERSION = '1.21.0';" in version, 'Formal V1.21 release marker is 1.21.0')
check("const APP_ASSET_REVISION = '1.21.0';" in version, 'Formal V1.21 asset revision is 1.21.0')
check("loadScript('./js/drawer-categories.js?v=1.21.0');" in calendar, 'Dashboard / Stock reload the Drawer organizer under the final cache key')
check("./css/drawer-v121b.css?v=1.21.0" in drawer and 'data-drawer-v121b-style' in drawer, 'Drawer organizer stages the finalized B stylesheet once')

check('background-color: #f6f7f9' in css, 'Drawer uses a light gray surface instead of pure white')
check('background-color: #eef2f6' in css and '.drawer-section-title > i' in css and 'color: #0d6efd' in css, 'Section headers use a neutral surface with restrained blue icon accent')
check('border-left: 3px solid #0d6efd' not in css, 'Section headers do not reuse the Current-item left blue indicator')
check('margin: 12px 8px 4px' in css, 'Section spacing is explicit')
check('width: 28px' in css and 'height: 28px' in css and 'border-radius: 6px' in css, 'Drawer item icons use compact recognition tiles')
check('background-color: #e9eef5' in css and 'color: #212529 !important' in css, 'Hover / focus state is more visible')
check('background-color: #e7f1ff !important' in css and 'border-left-color: #0d6efd !important' in css, 'Current item remains clearly distinguishable')
check('.drawer-item-current .drawer-item-icon' in css and 'background-color: #0d6efd' in css, 'Current item icon tile reinforces selection')
check('.drawer-logout-button {' in css and 'color: #a52834 !important' in css, 'Logout keeps a restrained Danger treatment even before hover')
check('outline: 2px solid #0d6efd' in css, 'Keyboard focus has an explicit visible outline')
check('@media' not in css, 'V1.21-B stylesheet itself does not mix Smartphone breakpoint tuning')
check('[data-drawer-section=' not in css, 'Categories share one accent instead of rainbow category coloring')

check('@media (pointer: coarse)' in base_css and 'min-height: 44px' in base_css, 'Existing 44px touch target remains available for coarse pointers')
check('overflow-y: auto' in base_css and 'overscroll-behavior: contain' in base_css, 'Existing Drawer scrolling behavior remains available')
check('bootstrap.Offcanvas' not in drawer, 'Drawer organizer does not replace Bootstrap Offcanvas behavior')

migration_names = [path.name for path in (ROOT / 'database').rglob('*.sql') if 'v1_21' in path.name.lower() or 'v121' in path.name.lower()]
check(migration_names == [], 'V1.21-B/C adds no database migration')

passed = sum(checks)
failed = len(checks) - passed
print(f'RESULT: PASS {passed} / FAIL {failed}')
sys.exit(1 if failed else 0)
