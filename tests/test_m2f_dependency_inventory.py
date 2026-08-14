from __future__ import annotations

from pathlib import Path
import re
import sys
from urllib.parse import unquote

from dashboard_source_utils import dashboard_source

ROOT = Path(__file__).resolve().parents[1]
PUBLIC = ROOT / 'public'
checks: list[bool] = []

def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

index = dashboard_source(ROOT)
login = (ROOT / 'app/common/common_login.php').read_text(encoding='utf-8')
jquery = (PUBLIC / 'js/jquery-3.7.1.min.js').read_text(encoding='utf-8', errors='replace')
bootstrap_js = (PUBLIC / 'js/bootstrap.bundle-5.3.8.min.js').read_text(encoding='utf-8', errors='replace')
bootstrap_css = (PUBLIC / 'css/bootstrap-5.3.8.min.css').read_text(encoding='utf-8', errors='replace')
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

order = ["app_asset_url('js/jquery-3.7.1.min.js')", "app_asset_url('js/bootstrap.bundle-5.3.8.min.js')", "app_asset_url('js/dashboard.js')", "app_asset_url('js/calendar.js')"]
positions = [index.index(item) for item in order]
check(positions == sorted(positions), 'Dashboard dependency order remains jQuery, Bootstrap bundle, app')
check("app_asset_url('js/bootstrap.bundle-5.3.8.min.js')" not in login and "app_asset_url('js/auth.js')" in login, 'authentication screen remains dependency-free and does not load Bootstrap JavaScript')

check('Bootstrap v5.3.8' in bootstrap_js[:700], 'Bootstrap JavaScript bundle is 5.3.8')
check('v5.3.8' in bootstrap_css[:700] and 'Licensed under MIT' in bootstrap_css[:1000], 'Bootstrap CSS is 5.3.8 with upstream MIT header')
for theme in ['yeti', 'minty', 'flatly', 'journal', 'sketchy', 'solar', 'slate']:
    text = (PUBLIC / f'css/bootstrap-{theme}-5.3.8.min.css').read_text(errors='replace')[:1000]
    check('Bootswatch v5.3.8' in text, f'Bootswatch {theme} is paired at 5.3.8')
check('data-bs-toggle' in index and 'data-toggle=' not in index + login and 'data-dismiss=' not in index + login, 'Bootstrap 5 Data API is used without Bootstrap 4 attributes')
dashboard_js = (PUBLIC / 'js/dashboard.js').read_text(encoding='utf-8')
check('bootstrap.Modal.getOrCreateInstance' in dashboard_js, 'Bootstrap Modal uses the Bootstrap 5 native API')
check('bootstrap.Offcanvas.getOrCreateInstance' in dashboard_js, 'right menu uses Bootstrap 5 Offcanvas')

legacy_assets = [
    'public/css/bootstrap.min.css',
    'public/css/bootstrap.min.css.map',
    'public/js/bootstrap.min.js',
    'public/js/bootstrap.min.js.map',
    'public/js/popper.min.js',
    'public/css/drawer.min.css',
    'public/js/drawer.min.js',
    'public/js/iscroll.js',
]
legacy_assets.extend(f'public/css/bootstrap-{theme}.min.css' for theme in ['yeti', 'minty', 'flatly', 'journal', 'sketchy', 'solar', 'slate'])
for rel in legacy_assets:
    check(not (ROOT / rel).exists(), f'legacy dependency asset is absent: {rel}')
check('Popper' in bootstrap_js[:5000] or 'popper' in bootstrap_js[:5000].lower(), 'Popper support is carried only by the Bootstrap bundle')

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

check(not (ROOT / 'package.json').exists(), 'no npm manifest was added')
check(not (ROOT / 'node_modules').exists(), 'no node_modules directory was added')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M2-F dependency inventory checks passed.')
