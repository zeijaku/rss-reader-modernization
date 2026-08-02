#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import hashlib
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
PUBLIC = ROOT / 'public'
checks: list[bool] = []

def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

notice = (ROOT / 'THIRD_PARTY_NOTICES.md').read_text(encoding='utf-8')
deps = (ROOT / 'docs/dependencies.md').read_text(encoding='utf-8')
root_license = (ROOT / 'LICENSE').read_text(encoding='utf-8')
jquery_license = (ROOT / 'licenses/jquery-MIT.txt').read_text(encoding='utf-8')
fa_license = (ROOT / 'licenses/fontawesome-6.7.2-LICENSE.txt').read_text(encoding='utf-8')

# Project license boundary.
check(root_license.startswith('MIT License'), 'project root license remains MIT')
check('Copyright (c) 2026 Arima Masao' in root_license, 'project copyright remains present')
check('does **not** replace or relicense third-party components' in notice, 'third-party license boundary is explicit')
check('Root [`LICENSE`](../LICENSE)' in deps, 'dependency document explains project license boundary')

# Notice versions and paths match runtime assets.
expected_notice = [
    '| Bootstrap | 4.1.3 |',
    '| Bootswatch themes | 4.1.3 |',
    '| jQuery | 3.7.1 |',
    '| Popper.js | 1.x vendored build |',
    '| jquery-drawer | 3.2.2 |',
    '| iScroll | 5.2.0-snapshot |',
    '| Font Awesome Free | 6.7.2 |',
    '`public/js/jquery-3.7.1.min.js`',
    '`public/css/all.css`',
    '`licenses/fontawesome-6.7.2-LICENSE.txt`',
]
for token in expected_notice:
    check(token in notice, f'current notice contains: {token}')
for stale in [
    'jQuery | 3.3.1',
    'Font Awesome Free | 5.3.1',
    'jquery-3.3.1.min.js',
    'fontawesome-5.3.1-LICENSE.txt',
    'public/less', 'public/scss', 'public/sprites', 'public/metadata',
]:
    check(stale not in notice, f'stale notice entry is absent: {stale}')

# Current license copies.
expected_license_files = {
    'bootstrap-MIT.txt',
    'bootswatch-MIT.txt',
    'jquery-MIT.txt',
    'popper-MIT.txt',
    'jquery-drawer-MIT.txt',
    'iscroll-MIT.txt',
    'fontawesome-6.7.2-LICENSE.txt',
}
actual_license_files = {p.name for p in (ROOT / 'licenses').iterdir() if p.is_file()}
check(actual_license_files == expected_license_files, 'license directory contains exactly the current seven copies')
check(not (ROOT / 'licenses/fontawesome-5.3.1-LICENSE.txt').exists(), 'old Font Awesome 5.3.1 license copy is removed')
check('Copyright OpenJS Foundation and other contributors' in jquery_license, 'jQuery 3.7.1 license uses current OpenJS copyright')
check(not jquery_license.startswith('Copyright JS Foundation'), 'old jQuery Foundation wording is absent')
for token in [
    'Font Awesome Free License',
    '# Icons: CC BY 4.0 License',
    '# Fonts: SIL OFL 1.1 License',
    'Copyright (c) 2024 Fonticons, Inc.',
    'Reserved Font Name: "Font Awesome"',
    '# Code: MIT License',
    '# Brand Icons',
]:
    check(token in fa_license, f'Font Awesome 6.7.2 license contains: {token}')
check(len(fa_license.encode('utf-8')) > 7000, 'Font Awesome full license copy is retained')

# Embedded upstream headers and runtime versions remain untouched.
jquery = (PUBLIC / 'js/jquery-3.7.1.min.js').read_text(encoding='utf-8', errors='replace')
fa_css = (PUBLIC / 'css/all.css').read_text(encoding='utf-8', errors='replace')
bootstrap_css = (PUBLIC / 'css/bootstrap.min.css').read_text(encoding='utf-8', errors='replace')
bootstrap_js = (PUBLIC / 'js/bootstrap.min.js').read_text(encoding='utf-8', errors='replace')
drawer_js = (PUBLIC / 'js/drawer.min.js').read_text(encoding='utf-8', errors='replace')
iscroll_js = (PUBLIC / 'js/iscroll.js').read_text(encoding='utf-8', errors='replace')
check('jQuery v3.7.1' in jquery[:200] and 'OpenJS Foundation' in jquery[:200], 'jQuery runtime header matches notice')
check('Font Awesome Free 6.7.2' in fa_css[:300] and 'Copyright 2024 Fonticons, Inc.' in fa_css[:300], 'Font Awesome runtime header matches notice')
check('Bootstrap v4.1.3' in bootstrap_css[:500] and 'Bootstrap v4.1.3' in bootstrap_js[:500], 'Bootstrap CSS/JS headers match notice')
check('jquery-drawer v3.2.2' in drawer_js[:300] and 'License : MIT' in drawer_js[:800], 'Drawer header matches notice')
check('iScroll v5.2.0-snapshot' in iscroll_js[:200], 'iScroll header matches notice')
for theme in ['yeti', 'minty', 'flatly', 'journal', 'sketchy', 'solar', 'slate']:
    text = (PUBLIC / f'css/bootstrap-{theme}.min.css').read_text(encoding='utf-8', errors='replace')[:500]
    check('Bootswatch v4.1.3' in text, f'Bootswatch {theme} header matches notice')

# Exact retained Font Awesome file inventory.
expected_fonts = {
    'fa-brands-400.ttf', 'fa-brands-400.woff2',
    'fa-regular-400.ttf', 'fa-regular-400.woff2',
    'fa-solid-900.ttf', 'fa-solid-900.woff2',
    'fa-v4compatibility.ttf', 'fa-v4compatibility.woff2',
}
actual_fonts = {p.name for p in (PUBLIC / 'webfonts').iterdir() if p.is_file()}
check(actual_fonts == expected_fonts, 'Font Awesome notice covers the retained TTF/WOFF2 inventory')

# Freeze M4-B license copies so accidental truncation is detected later.
expected_sha256 = {
    'LICENSE': None,
    'licenses/jquery-MIT.txt': None,
    'licenses/fontawesome-6.7.2-LICENSE.txt': None,
}
# Hash values are recorded in docs/m4-b-license-sha256.txt and checked without
# coupling this test source to line-ending conversions.
hash_file = ROOT / 'docs/m4-b-license-sha256.txt'
check(hash_file.is_file(), 'M4-B license hash record exists')
expected = {}
for line in hash_file.read_text(encoding='utf-8').splitlines():
    if line.strip():
        digest, rel = line.split('  ', 1)
        expected[rel] = digest
for rel in expected_sha256:
    check(rel in expected, f'license hash record contains {rel}')
    check(hashlib.sha256((ROOT / rel).read_bytes()).hexdigest() == expected[rel], f'license SHA-256 matches: {rel}')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M4-B license inventory checks passed.')
