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
index = text('public/index.php')
settings = text('public/settings.php')
mail = text('public/js/mail-widget.js')
camera = text('public/js/camera-video.js')

check("const APP_VERSION = '1.21.0';" in version, 'Formal V1.21 release marker is 1.21.0')
revision_match = re.search(r"const APP_ASSET_REVISION = '([^']+)';", version)
active_revision = revision_match.group(1) if revision_match else ''
check(active_revision == '1.21.0' or re.fullmatch(r'1\.22\.0(?:-[A-Za-z0-9._-]+)?', active_revision) is not None,
      'Asset revision is the formal V1.21 key or a V1.22 checkpoint/final key')

drawer_loader_pos = calendar.find("loadScript('./js/drawer-categories.js?v=" + active_revision + "');")
check(drawer_loader_pos >= 0, 'Dashboard / Stock loader includes the Drawer categories module at the active revision')
check(0 <= calendar.find('./js/mail-widget.js?v=' + active_revision) < drawer_loader_pos, 'Mail initializes before Drawer categorization')
check(0 <= calendar.find('./js/camera-video.js?v=' + active_revision) < drawer_loader_pos, 'Camera / Video initializes before Drawer categorization')
check(0 <= calendar.find('./js/widget-settings-no-reload.js?v=' + active_revision) < drawer_loader_pos, 'Drawer categorization remains the final staged Dashboard module')
check("app_asset_url('js/drawer-categories.js')" in settings, 'Settings loads the same Drawer categorizer')

expected_sections = ['DISPLAY', 'FEED', 'PRODUCTIVITY', 'INFORMATION', 'MEDIA', 'GAME', 'SETTINGS', 'USER LINKS', 'ACCOUNT']
positions = [drawer.find("label: '" + label + "'") for label in expected_sections]
check(all(position >= 0 for position in positions) and positions == sorted(positions), 'Drawer section metadata follows the V1.21-A category order')

for sequence in [
    ["'#registerContent'", "'#registerSearchFeed'"],
    ["'#registerTaskWidget'", "'#registerCalendarWidget'", "'#registerMemo'", "'#registerClock'", "'#registerMailWidget'"],
    ["'#registerLinksWidget'", "'#registerWeatherWidget'"],
    ["'#registerCameraVideo'"],
    ["'#registerGameWidget'"],
    ["'#accountSettings'"],
]:
    found = [drawer.find(value) for value in sequence]
    check(all(position >= 0 for position in found) and found == sorted(found), 'Drawer action order is retained for ' + ', '.join(sequence))

for href in ['./?tab=0', './?tab=1', './?tab=2', './?tab=3', './stock', './settings#tabs', './settings#display', './settings#highlight']:
    check("'" + href + "'" in drawer, 'Drawer categorizer keeps navigation target ' + href)

check("$menu.children('li.drawer-mobile-links').not('.drawer-section-title')" in drawer, 'Smartphone user links are preserved as their existing nodes')
check("mobileOnly: true" in drawer and "addClass('drawer-mobile-links')" in drawer, 'USER LINKS heading remains Smartphone-only')
check("children('.drawer-logout-form')" in drawer, 'Logout form is moved without rebuilding its POST / CSRF form')
check("detach()" in drawer, 'Existing Drawer nodes are moved rather than recreated')
check("window.setTimeout(organizeDrawer, 0)" in drawer, 'Categorization waits until existing dynamic ready handlers complete')
check("data-drawer-categories" in drawer and "data-drawer-section" in drawer, 'V1.21-B/C have stable Drawer hooks')
check('.html(' not in drawer and 'innerHTML' not in drawer, 'Drawer categorization does not add raw HTML rendering')

check('>Widget追加<' in index and '>カスタマイズ<' in index, 'Dashboard keeps the pre-V1.21 fallback Drawer HTML when JavaScript is unavailable')
check('#registerMailWidget' in mail, 'Mail dynamic Drawer action remains available')
check('#registerCameraVideo' in camera and 'drawerCameraVideoAdd' in camera, 'Camera / Video dynamic Drawer action remains available')
check('カスタマイズ' in camera, 'Camera / Video keeps its existing insertion contract until categorization runs')

migration_names = [path.name for path in (ROOT / 'database').rglob('*.sql') if 'v1_21' in path.name.lower() or 'v121' in path.name.lower()]
check(migration_names == [], 'V1.21-A adds no database migration')

passed = sum(checks)
failed = len(checks) - passed
print(f'RESULT: PASS {passed} / FAIL {failed}')
sys.exit(1 if failed else 0)
