#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
path = ROOT / 'tools/apply_m4b_cleanup.ps1'
data = path.read_bytes()
checks = []

def check(ok: bool, message: str) -> None:
    checks.append(ok)
    print(('PASS' if ok else 'FAIL') + ': ' + message)

check(path.is_file(), 'M4-B cleanup helper exists')
check(all(byte < 128 for byte in data), 'M4-B cleanup helper is ASCII only')
check(b'\r\n' in data and b'\n' not in data.replace(b'\r\n', b''), 'M4-B cleanup helper uses CRLF only')
text = data.decode('ascii')
check('param(' in text and '[switch]$WhatIf' in text, 'cleanup helper supports WhatIf')
check('app\\version.php' in text and 'public\\index.php' in text, 'cleanup helper validates project markers')
check('licenses\\fontawesome-5.3.1-LICENSE.txt' in text, 'cleanup helper contains the complete deletion target')
check('GetFullPath' in text and 'StartsWith' in text, 'cleanup helper checks the safe project boundary')
check('Remove-Item -LiteralPath' in text, 'cleanup helper uses literal deletion')
check('fontawesome-6.7.2-LICENSE.txt' not in text, 'cleanup helper never deletes current Font Awesome license')
if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M4-B cleanup helper checks passed.')
