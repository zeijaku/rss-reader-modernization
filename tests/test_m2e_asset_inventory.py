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

expected_css = {
    'all.css',
    'auth.css',
    'bootstrap-5.3.8.min.css',
    'bootstrap-yeti-5.3.8.min.css',
    'bootstrap-minty-5.3.8.min.css',
    'bootstrap-flatly-5.3.8.min.css',
    'bootstrap-journal-5.3.8.min.css',
    'bootstrap-sketchy-5.3.8.min.css',
    'bootstrap-solar-5.3.8.min.css',
    'bootstrap-slate-5.3.8.min.css',
    'camera-video.css',
    'camera-video-playback.css',
    'dashboard.css',
    'mini-game.css',
    'clock-timer.css',
    'mail-widget.css',
    'utility-widgets.css',
}
expected_js = {
    'jquery-3.7.1.min.js',
    'bootstrap.bundle-5.3.8.min.js',
    'camera-video.js',
    'camera-video-playback.js',
    'dashboard.js',
    'calendar-core.js',
    'calendar.js',
    'mail-widget.js',
    'utility-widgets.js',
    'auth.js',
    'mini-game.js',
    'lights-out.js',
    'clock-timer.js',
}
expected_fonts = {
    f'fa-{family}-{weight}.{extension}'
    for family, weight in [('brands', '400'), ('regular', '400'), ('solid', '900')]
    for extension in ['ttf', 'woff2']
}
expected_fonts.update({'fa-v4compatibility.ttf', 'fa-v4compatibility.woff2'})

actual_css = {p.name for p in (PUBLIC / 'css').iterdir() if p.is_file()}
actual_js = {p.name for p in (PUBLIC / 'js').iterdir() if p.is_file()}
actual_fonts = {p.name for p in (PUBLIC / 'webfonts').iterdir() if p.is_file()}
check(actual_css == expected_css, 'CSS directory matches the retained application inventory')
check(actual_js == expected_js, 'JavaScript directory matches the retained application inventory')
check(actual_fonts == expected_fonts, 'Font Awesome webfont formats required by all.css remain present')

for directory in ['less', 'scss', 'metadata', 'sprites']:
    check(not (PUBLIC / directory).exists(), f'unused public/{directory} directory is removed')

dashboard_markup = dashboard_source(ROOT)
login = (ROOT / 'app/common/common_login.php').read_text(encoding='utf-8')
func = (ROOT / 'app/common/common_func.php').read_text(encoding='utf-8')
all_php = dashboard_markup + '\n' + login

direct_static_refs = {ref.split('?', 1)[0].split('#', 1)[0] for ref in re.findall(r'(?:href|src)="\./((?:css|js)/[^"]+|favicon\.png)"', all_php) if '<?php' not in ref}
helper_static_refs = set(re.findall(r"app_asset_url\('((?:css|js)/[^']+|favicon\.png)'\)", all_php))
static_refs = direct_static_refs | helper_static_refs
expected_static_refs = {
    'css/all.css',
    'css/dashboard.css',
    'css/auth.css',
    'css/mini-game.css',
    'css/clock-timer.css',
    'css/utility-widgets.css',
    'js/jquery-3.7.1.min.js',
    'js/bootstrap.bundle-5.3.8.min.js',
    'js/dashboard.js',
    'js/utility-widgets.js',
    'js/calendar.js',
    'js/auth.js',
    'js/mini-game.js',
    'js/lights-out.js',
    'js/clock-timer.js',
    'favicon.png',
}
check(static_refs == expected_static_refs, 'static HTML/PHP asset references match the Version 1.14 inventory')
for ref in static_refs:
    check((PUBLIC / ref).is_file(), f'directly referenced asset exists: {ref}')

expected_themes = {
    'bootstrap-5.3.8.min.css',
    'bootstrap-yeti-5.3.8.min.css',
    'bootstrap-minty-5.3.8.min.css',
    'bootstrap-flatly-5.3.8.min.css',
    'bootstrap-journal-5.3.8.min.css',
    'bootstrap-sketchy-5.3.8.min.css',
    'bootstrap-solar-5.3.8.min.css',
    'bootstrap-slate-5.3.8.min.css',
}
resolved_themes = set(re.findall(r"'bootstrap(?:-[a-z]+)?'\s*=>\s*'([^']+\.css)'", func))
check(resolved_themes == expected_themes, 'theme whitelist resolves to all eight Version 1.14 Bootstrap stylesheets')
for theme in resolved_themes:
    check((PUBLIC / 'css' / theme).is_file(), f'theme stylesheet exists: {theme}')

local_css_refs: set[Path] = set()
for css_path in sorted((PUBLIC / 'css').glob('*.css')):
    text = css_path.read_text(encoding='utf-8', errors='replace')
    for raw in re.findall(r'url\(([^)]+)\)', text):
        value = raw.strip().strip('"\'')
        if not value or value.startswith(('data:', 'http://', 'https://', '//', '#')):
            continue
        clean = unquote(value.split('?', 1)[0].split('#', 1)[0])
        local_css_refs.add((css_path.parent / clean).resolve())
for path in sorted(local_css_refs):
    check(path.is_file(), f'local CSS dependency exists: {path.relative_to(ROOT)}')
check(len(local_css_refs) == 8, 'all.css resolves the expected eight local Font Awesome files')

app_owned_assets = [
    p for p in list((PUBLIC / 'css').glob('*.css')) + list((PUBLIC / 'js').glob('*.js'))
    if not p.name.startswith('bootstrap') and p.name not in {'all.css', 'jquery-3.7.1.min.js'}
]
for asset in app_owned_assets:
    text = asset.read_text(encoding='utf-8', errors='replace')
    check('sourceMappingURL=' not in text, f'application asset has no Source Map dependency: {asset.relative_to(ROOT)}')

markup = dashboard_markup + '\n' + login
icons = sorted(icon for icon in set(re.findall(r'\bfa-([a-z0-9-]+)\b', markup)) if icon not in {'fw', 'spin'} and not re.fullmatch(r'\d+x', icon))
fa_css = (PUBLIC / 'css/all.css').read_text(encoding='utf-8', errors='replace')
for icon in icons:
    check(re.search(rf'\.fa-{re.escape(icon)}\s*\{{[^}}]*--fa:', fa_css, re.S) is not None, f'Font Awesome definition remains for fa-{icon}')
check(len(icons) >= 15, 'icon inventory covers the Dashboard and authentication screens')

bootstrap_css_header = (PUBLIC / 'css/bootstrap-5.3.8.min.css').read_text(encoding='utf-8', errors='replace')[:1000]
check('v5.3.8' in bootstrap_css_header and 'Licensed under MIT' in bootstrap_css_header,
      'Bootstrap 5.3.8 CSS keeps its upstream version/license header')
license_markers = {
    PUBLIC / 'js/bootstrap.bundle-5.3.8.min.js': 'Bootstrap v5.3.8',
    PUBLIC / 'css/all.css': 'Font Awesome Free 6.7.2',
    PUBLIC / 'js/jquery-3.7.1.min.js': 'jQuery v3.7.1',
}
for path, marker in license_markers.items():
    check(marker in path.read_text(encoding='utf-8', errors='replace')[:1000], f'license/version header remains in {path.relative_to(ROOT)}')

legacy_assets = {
    'css/bootstrap.min.css',
    'css/bootstrap.min.css.map',
    'css/bootstrap-yeti.min.css',
    'css/bootstrap-minty.min.css',
    'css/bootstrap-flatly.min.css',
    'css/bootstrap-journal.min.css',
    'css/bootstrap-sketchy.min.css',
    'css/bootstrap-solar.min.css',
    'css/bootstrap-slate.min.css',
    'css/drawer.min.css',
    'js/popper.min.js',
    'js/bootstrap.min.js',
    'js/bootstrap.min.js.map',
    'js/iscroll.js',
    'js/drawer.min.js',
}
for rel in sorted(legacy_assets):
    check(not (PUBLIC / rel).exists(), f'legacy frontend asset is absent: public/{rel}')

public_files = [p for p in PUBLIC.rglob('*') if p.is_file()]
public_size = sum(p.stat().st_size for p in public_files)
check(len(public_files) == 46, 'public inventory contains the 46 retained files including Camera / Video playback assets')
check(public_size < 4_200_000, 'public inventory remains below 4.2 MB after Camera / Video playback assets')
check(not (ROOT / 'package.json').exists(), 'asset cleanup adds no npm dependency')
check(not (ROOT / 'node_modules').exists(), 'asset cleanup adds no node_modules directory')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M2-E asset inventory checks passed.')
