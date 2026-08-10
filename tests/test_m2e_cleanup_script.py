from __future__ import annotations

from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
script_path = ROOT / 'tools/apply_m2e_cleanup.ps1'
script_bytes = script_path.read_bytes()
script = script_bytes.decode('ascii')
deleted_doc = (ROOT / 'docs/m2-e-deleted-assets.txt').read_text(encoding='utf-8').splitlines()
paths = [line.strip() for line in deleted_doc if line.startswith('public/')]

checks: list[bool] = []
def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

check(all(byte < 128 for byte in script_bytes), 'cleanup helper is ASCII-only for Windows PowerShell 5.1')
check(script_bytes.decode('cp932') == script, 'cleanup helper decodes identically with Japanese Windows ANSI code page')
check(b'\r' not in script_bytes.replace(b'\r\n', b'') and (b'\r\n' not in script_bytes or b'\n' not in script_bytes.replace(b'\r\n', b'')), 'cleanup helper uses consistent LF or CRLF line endings')
check(not script_bytes.startswith((b'\xef\xbb\xbf', b'\xff\xfe', b'\xfe\xff')), 'cleanup helper does not depend on a BOM')
check('Dry run complete. No files were removed.' in script, 'cleanup helper messages are encoding-safe')
check('Git working tree not found' in script and 'public/index.php not found' in script, 'cleanup helper guard messages are encoding-safe')
check(script.count('"') % 2 == 0, 'cleanup helper has balanced double quotes')
check(script.count('{') == script.count('}'), 'cleanup helper has balanced braces')
check(script.count('(') == script.count(')'), 'cleanup helper has balanced parentheses')
check(len(paths) == 88, 'deleted asset document lists all 88 removed files')
check(len(paths) == len(set(paths)), 'deleted asset document has no duplicate paths')
check(all(not (ROOT / path).exists() for path in paths), 'all documented deleted files are absent from M2-E')
check(all(path.startswith(('public/css/', 'public/js/', 'public/less/', 'public/scss/', 'public/metadata/', 'public/sprites/')) for path in paths), 'cleanup scope is limited to known Frontend asset locations')
check('param(' in script and '[switch]$WhatIf' in script, 'cleanup helper supports a dry-run')
check('Test-Path -LiteralPath (Join-Path $ProjectRoot ".git")' in script, 'cleanup helper requires a Git working tree')
check('public/index.php' in script, 'cleanup helper checks the application sentinel')
check('Remove-Item -LiteralPath $Target' in script, 'cleanup helper removes literal validated paths')
check('public/less' in script and 'public/scss' in script and 'public/metadata' in script and 'public/sprites' in script, 'cleanup helper removes the four unused source directories')
check('public/webfonts' not in script, 'cleanup helper does not remove retained webfonts')
check('"public"' not in script and "'public'" not in script, 'cleanup helper has no broad public directory deletion')
check('git clean' not in script.lower(), 'cleanup helper does not use broad git clean')
for path in [p for p in paths if not p.startswith(('public/less/', 'public/scss/', 'public/metadata/', 'public/sprites/'))]:
    check(f'"{path}"' in script, f'cleanup helper includes removed file: {path}')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M2-E cleanup helper checks passed.')
