from __future__ import annotations

from pathlib import Path
import re
import sys
from urllib.parse import unquote

ROOT = Path(__file__).resolve().parents[1]
PUBLIC = ROOT / 'public'
checks: list[bool] = []

def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

index = (PUBLIC / 'index.php').read_text(encoding='utf-8')
login = (ROOT / 'app/common/common_login.php').read_text(encoding='utf-8')
jquery = (PUBLIC / 'js/jquery-3.7.1.min.js').read_text(encoding='utf-8', errors='replace')
bootstrap_js = (PUBLIC / 'js/bootstrap.min.js').read_text(encoding='utf-8', errors='replace')
fa_css = (PUBLIC / 'css/all.css').read_text(encoding='utf-8', errors='replace')

check('jQuery v3.7.1' in jquery[:200], 'jQuery is updated to 3.7.1')
check('jQuery v3.3.1' not in jquery[:200], 'old jQuery header is absent')
check('ajax:function' in jquery or '.ajax=' in jquery or 'ajaxSettings' in jquery, 'jQuery full build contains AJAX implementation')
check('slim' not in jquery[:200].lower(), 'jQuery slim build is not used')
check(not (PUBLIC / 'js/jquery-3.3.1.min.js').exists(), 'old jQuery file is absent')
check("app_asset_url('js/jquery-3.7.1.min.js')" in index and "app_asset_url('js/jquery-3.7.1.min.js')" not in login and "app_asset_url('js/auth.js')" in login, 'Dashboard keeps jQuery while the dedicated authentication screen uses dependency-free JavaScript')
check('jquery-3.3.1.min.js' not in index + login, 'old jQuery reference is absent')
healthcheck = (ROOT / 'tools/healthcheck.php').read_text(encoding='utf-8')
check('js/jquery-3.7.1.min.js' in healthcheck and 'jquery-3.3.1.min.js' not in healthcheck, 'healthcheck requires the updated jQuery asset')

order = ["app_asset_url('js/jquery-3.7.1.min.js')", "app_asset_url('js/popper.min.js')", "app_asset_url('js/bootstrap.min.js')", "app_asset_url('js/iscroll.js')", "app_asset_url('js/drawer.min.js')", "app_asset_url('js/dashboard.js')", "app_asset_url('js/calendar.js')"]
positions = [index.index(item) for item in order]
check(positions == sorted(positions), 'Dashboard dependency order remains jQuery, Popper, Bootstrap, iScroll, Drawer, app')
check("app_asset_url('js/popper.min.js')" not in login and "app_asset_url('js/bootstrap.min.js')" not in login and "app_asset_url('js/auth.js')" in login, 'authentication screen no longer loads unnecessary Popper or Bootstrap JavaScript')

check('Bootstrap v4.1.3' in bootstrap_js[:500], 'Bootstrap JavaScript remains paired at 4.1.3')
check('Bootstrap v4.1.3' in (PUBLIC / 'css/bootstrap.min.css').read_text(errors='replace')[:500], 'Bootstrap CSS remains paired at 4.1.3')
for theme in ['yeti', 'minty', 'flatly', 'journal', 'sketchy', 'solar', 'slate']:
    text = (PUBLIC / f'css/bootstrap-{theme}.min.css').read_text(errors='replace')[:500]
    check('Bootswatch v4.1.3' in text, f'Bootswatch {theme} remains paired at 4.1.3')
check('data-bs-toggle' not in index + login, 'Bootstrap 5 data attributes are not mixed into Bootstrap 4 markup')
check('data-toggle=' in index + login and 'data-dismiss=' in index + login, 'Bootstrap 4 data attributes remain present')
check('.modal(' in (PUBLIC / 'js/dashboard.js').read_text(), 'existing Bootstrap modal plugin calls remain')
check('.popover(' in (PUBLIC / 'js/dashboard.js').read_text(), 'existing Bootstrap popover plugin calls remain')

check('Font Awesome Free 6.7.2' in fa_css[:300], 'Font Awesome Free is updated to 6.7.2')
check('Font Awesome Free 5.3.1' not in fa_css[:300], 'old Font Awesome header is absent')
expected_fonts = {
    'fa-brands-400.ttf', 'fa-brands-400.woff2',
    'fa-regular-400.ttf', 'fa-regular-400.woff2',
    'fa-solid-900.ttf', 'fa-solid-900.woff2',
    'fa-v4compatibility.ttf', 'fa-v4compatibility.woff2',
}
actual_fonts = {p.name for p in (PUBLIC / 'webfonts').iterdir() if p.is_file()}
check(actual_fonts == expected_fonts, 'Font Awesome webfont inventory matches CSS 6.7.2 package')
refs = set()
for raw in re.findall(r'url\(([^)]+)\)', fa_css):
    value = raw.strip().strip('"\'')
    if value.startswith('../webfonts/'):
        refs.add(Path(unquote(value.split('?', 1)[0].split('#', 1)[0])).name)
check(refs == expected_fonts, 'Font Awesome CSS references exactly the retained eight webfonts')

markup = index + login
icons = sorted(icon for icon in set(re.findall(r'\bfa-([a-z0-9-]+)\b', markup)) if icon not in {'fw', 'spin'} and not re.fullmatch(r'\d+x', icon))
for icon in icons:
    check(re.search(rf'\.fa-{re.escape(icon)}\s*\{{[^}}]*--fa:', fa_css, re.S) is not None, f'Font Awesome 6 alias exists for fa-{icon}')
check(all(token in fa_css for token in ['.fas', '.far', '.fab']), 'Font Awesome style classes remain available')

check('License : MIT' in (PUBLIC / 'js/drawer.min.js').read_text(errors='replace')[:800], 'Drawer 3.2.2 license header remains')
check('iScroll v5.2.0-snapshot' in (PUBLIC / 'js/iscroll.js').read_text(errors='replace')[:200], 'iScroll version remains unchanged')
check('sourceMappingURL=popper.min.js.map' not in (PUBLIC / 'js/popper.min.js').read_text(errors='replace'), 'Popper has no broken Source Map reference')
check(not (ROOT / 'package.json').exists(), 'no npm manifest was added')
check(not (ROOT / 'node_modules').exists(), 'no node_modules directory was added')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M2-F dependency inventory checks passed.')
