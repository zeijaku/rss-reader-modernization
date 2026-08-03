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

expected_css = {
    'all.css',
    'bootstrap.min.css',
    'bootstrap.min.css.map',
    'bootstrap-yeti.min.css',
    'bootstrap-minty.min.css',
    'bootstrap-flatly.min.css',
    'bootstrap-journal.min.css',
    'bootstrap-sketchy.min.css',
    'bootstrap-solar.min.css',
    'bootstrap-slate.min.css',
    'drawer.min.css',
    'dashboard.css',
}
expected_js = {
    'jquery-3.7.1.min.js',
    'popper.min.js',
    'bootstrap.min.js',
    'bootstrap.min.js.map',
    'iscroll.js',
    'drawer.min.js',
    'dashboard.js',
    'calendar.js',
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
check(actual_css == expected_css, 'CSS directory contains only the retained M2-E inventory')
check(actual_js == expected_js, 'JavaScript directory contains only the retained M2-E inventory')
check(actual_fonts == expected_fonts, 'Font Awesome webfont formats required by all.css remain present')

for directory in ['less', 'scss', 'metadata', 'sprites']:
    check(not (PUBLIC / directory).exists(), f'unused public/{directory} directory is removed')

index = (PUBLIC / 'index.php').read_text(encoding='utf-8')
login = (ROOT / 'app/common/common_login.php').read_text(encoding='utf-8')
func = (ROOT / 'app/common/common_func.php').read_text(encoding='utf-8')
all_php = '\n'.join(p.read_text(encoding='utf-8', errors='replace') for p in [PUBLIC / 'index.php', ROOT / 'app/common/common_login.php'])

static_refs = {ref for ref in re.findall(r'(?:href|src)="\./((?:css|js)/[^"]+|favicon\.png)"', all_php) if '<?php' not in ref}
expected_static_refs = {
    'css/all.css', 'css/drawer.min.css', 'css/dashboard.css',
    'js/jquery-3.7.1.min.js', 'js/popper.min.js', 'js/bootstrap.min.js',
    'js/iscroll.js', 'js/drawer.min.js', 'js/dashboard.js', 'js/calendar.js', 'favicon.png',
}
check(static_refs == expected_static_refs, 'static HTML/PHP asset references match the retained inventory')
for ref in static_refs:
    check((PUBLIC / ref).is_file(), f'directly referenced asset exists: {ref}')

expected_themes = {
    'bootstrap.min.css', 'bootstrap-yeti.min.css', 'bootstrap-minty.min.css',
    'bootstrap-flatly.min.css', 'bootstrap-journal.min.css',
    'bootstrap-sketchy.min.css', 'bootstrap-solar.min.css', 'bootstrap-slate.min.css',
}
resolved_themes = set(re.findall(r"'bootstrap(?:-[a-z]+)?'\s*=>\s*'([^']+\.css)'", func))
check(resolved_themes == expected_themes, 'theme whitelist resolves to all eight retained Bootstrap stylesheets')
for theme in resolved_themes:
    check((PUBLIC / 'css' / theme).is_file(), f'theme stylesheet exists: {theme}')

# Resolve local url() references from the retained CSS, including query/fragment suffixes.
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

# Source map hints in retained files must resolve. Popper's stale hint was removed because no map existed in the baseline.
map_refs: list[Path] = []
for asset in list((PUBLIC / 'css').glob('*.css')) + list((PUBLIC / 'js').glob('*.js')):
    text = asset.read_text(encoding='utf-8', errors='replace')
    for value in re.findall(r'sourceMappingURL=([^\s*]+)', text):
        map_refs.append((asset.parent / value.strip()).resolve())
for path in map_refs:
    check(path.is_file(), f'Source Map hint resolves: {path.relative_to(ROOT)}')
check({p.name for p in map_refs} == {'bootstrap.min.css.map', 'bootstrap.min.js.map'}, 'only loaded Bootstrap files retain Source Map hints')
check('sourceMappingURL=popper.min.js.map' not in (PUBLIC / 'js/popper.min.js').read_text(encoding='utf-8', errors='replace'), 'stale Popper Source Map hint is removed')

# Every Font Awesome icon used by PHP markup should still be defined by all.css.
markup = '\n'.join(p.read_text(encoding='utf-8', errors='replace') for p in [PUBLIC / 'index.php', ROOT / 'app/common/common_login.php'])
icons = sorted(icon for icon in set(re.findall(r'\bfa-([a-z0-9-]+)\b', markup)) if icon not in {'fw', 'spin'} and not re.fullmatch(r'\d+x', icon))
fa_css = (PUBLIC / 'css/all.css').read_text(encoding='utf-8', errors='replace')
for icon in icons:
    check(re.search(rf'\.fa-{re.escape(icon)}\s*\{{[^}}]*--fa:', fa_css, re.S) is not None, f'Font Awesome definition remains for fa-{icon}')
check(len(icons) >= 15, 'icon inventory covers the Dashboard and authentication screens')

license_markers = {
    PUBLIC / 'css/bootstrap.min.css': 'Licensed under MIT',
    PUBLIC / 'css/all.css': 'Font Awesome Free 6.7.2',
    PUBLIC / 'css/drawer.min.css': 'License : MIT',
    PUBLIC / 'js/jquery-3.7.1.min.js': 'jQuery v3.7.1',
    PUBLIC / 'js/popper.min.js': 'Distributed under the MIT License',
    PUBLIC / 'js/bootstrap.min.js': 'Licensed under MIT',
    PUBLIC / 'js/iscroll.js': 'iScroll v5.2.0-snapshot',
    PUBLIC / 'js/drawer.min.js': 'License : MIT',
}
for path, marker in license_markers.items():
    check(marker in path.read_text(encoding='utf-8', errors='replace')[:800], f'license/version header remains in {path.relative_to(ROOT)}')

public_files = [p for p in PUBLIC.rglob('*') if p.is_file()]
public_size = sum(p.stat().st_size for p in public_files)
check(len(public_files) == 33, 'public inventory contains the 33 retained Version 1.1 files')
check(public_size < 4_000_000, 'public inventory is below 4 MB without removing runtime dependencies')
check(not (ROOT / 'package.json').exists(), 'asset cleanup adds no npm dependency')
check(not (ROOT / 'node_modules').exists(), 'asset cleanup adds no node_modules directory')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M2-E asset inventory checks passed.')
