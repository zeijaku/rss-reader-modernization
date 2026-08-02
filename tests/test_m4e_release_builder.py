#!/usr/bin/env python3
from __future__ import annotations

import ast
import hashlib
from pathlib import Path
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


def run(*args: str, expect: int = 0) -> subprocess.CompletedProcess[str]:
    result = subprocess.run(args, cwd=ROOT, text=True, capture_output=True)
    check(result.returncode == expect, f'command exit is {expect}: {" ".join(args)}')
    if result.returncode != expect:
        print(result.stdout)
        print(result.stderr, file=sys.stderr)
    return result


builder = ROOT / 'tools/build_release_package.py'
verifier = ROOT / 'tools/verify_release_package.py'
check(builder.is_file(), 'M4-E release package builder remains available')
check(verifier.is_file(), 'M4-E release package verifier remains available')
builder_text = builder.read_text(encoding='utf-8')
verifier_text = verifier.read_text(encoding='utf-8')
check(bool(ast.parse(builder_text)), 'release builder Python syntax parses')
check(bool(ast.parse(verifier_text)), 'release verifier Python syntax parses')
for term in ["choices=('preview', 'rc', 'final')", 'FIXED_TIME', 'RELEASE_BUILD.txt',
             'RELEASE_MANIFEST.sha256', 'publishable', "'config/local.php'", "'.env'"]:
    check(term in builder_text, f'M4-E builder contract remains present: {term}')

# The project has advanced to RC1. M4-E preview must now be rejected, while the same
# deterministic builder and verifier continue to work in RC mode.
preview = run(sys.executable, str(builder), '--mode', 'preview', '--output-dir', tempfile.gettempdir(), expect=1)
check('preview mode requires M4-E R1' in preview.stderr, 'historical M4-E preview mode rejects RC marker')

with tempfile.TemporaryDirectory(prefix='rss-m4e-history-a-') as tmp_a, tempfile.TemporaryDirectory(prefix='rss-m4e-history-b-') as tmp_b:
    out_a = Path(tmp_a)
    out_b = Path(tmp_b)
    run(sys.executable, str(builder), '--mode', 'rc', '--output-dir', str(out_a))
    run(sys.executable, str(builder), '--mode', 'rc', '--output-dir', str(out_b))
    zip_a = out_a / 'rss-reader-modernization-1.0.0-rc1.zip'
    zip_b = out_b / 'rss-reader-modernization-1.0.0-rc1.zip'
    side_a = out_a / 'rss-reader-modernization-1.0.0-rc1.zip.sha256'
    check(zip_a.is_file() and zip_b.is_file() and side_a.is_file(), 'builder produces RC artifacts after M4-E')
    check(hashlib.sha256(zip_a.read_bytes()).hexdigest() == hashlib.sha256(zip_b.read_bytes()).hexdigest(), 'M4-E deterministic build contract remains valid')
    verified = run(sys.executable, str(verifier), str(zip_a), str(side_a))
    check('release package checks passed' in verified.stdout, 'M4-E standalone verifier accepts current RC package')

final = run(sys.executable, str(builder), '--mode', 'final', '--output-dir', tempfile.gettempdir(), expect=1)
check('final mode requires the exact 1.0.0 version' in final.stderr, 'M4-E final guard still rejects non-final marker')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M4-E release builder checks passed.')
