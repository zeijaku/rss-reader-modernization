from __future__ import annotations

from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
path = ROOT / 'tools/apply_m2f_cleanup.ps1'
data = path.read_bytes()
text = data.decode('ascii')
checks: list[bool] = []

def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

old_files = [
    'public/js/jquery-3.3.1.min.js',
    'public/webfonts/fa-brands-400.eot', 'public/webfonts/fa-brands-400.svg', 'public/webfonts/fa-brands-400.woff',
    'public/webfonts/fa-regular-400.eot', 'public/webfonts/fa-regular-400.svg', 'public/webfonts/fa-regular-400.woff',
    'public/webfonts/fa-solid-900.eot', 'public/webfonts/fa-solid-900.svg', 'public/webfonts/fa-solid-900.woff',
]
check(all(byte < 128 for byte in data), 'M2-F cleanup helper is ASCII-only')
check(b'\r' not in data.replace(b'\r\n', b'') and (b'\r\n' not in data or b'\n' not in data.replace(b'\r\n', b'')), 'M2-F cleanup helper uses consistent LF or CRLF line endings')
check(not data.startswith((b'\xef\xbb\xbf', b'\xff\xfe', b'\xfe\xff')), 'M2-F cleanup helper does not depend on BOM')
check('[switch]$WhatIf' in text, 'M2-F cleanup helper supports dry-run')
check('Git working tree not found' in text and 'public/index.php not found' in text, 'M2-F cleanup helper has safety sentinels')
check(text.count('"') % 2 == 0 and text.count('{') == text.count('}') and text.count('(') == text.count(')'), 'M2-F cleanup helper delimiters are balanced')
for old in old_files:
    check(f'"{old}"' in text, f'cleanup helper includes old asset: {old}')
    check(not (ROOT / old).exists(), f'old asset is absent from package: {old}')
for retained in ['public/js/jquery-3.7.1.min.js', 'public/webfonts/fa-solid-900.woff2', 'public/webfonts/fa-v4compatibility.woff2']:
    check(retained not in text, f'cleanup helper does not remove retained asset: {retained}')
check('Remove-Item -LiteralPath $Target -Force -WhatIf:$WhatIf' in text, 'cleanup helper uses exact literal paths')
check('git clean' not in text.lower(), 'cleanup helper does not use broad git clean')
check('"public"' not in text and "'public'" not in text, 'cleanup helper does not remove public directory')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M2-F cleanup helper checks passed.')
