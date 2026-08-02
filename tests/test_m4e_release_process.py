#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import ast
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


builder_path = ROOT / 'tools/build_release_package.py'
verifier_path = ROOT / 'tools/verify_release_package.py'
builder = builder_path.read_text(encoding='utf-8')
verifier = verifier_path.read_text(encoding='utf-8')
tag = (ROOT / 'docs/tag-and-github-release.md').read_text(encoding='utf-8')
package = (ROOT / 'docs/release-package.md').read_text(encoding='utf-8')
check(ast.parse(builder), 'release builder Python syntax parses')
check(ast.parse(verifier), 'release verifier Python syntax parses')

for term in [
    "choices=('preview', 'rc', 'final')", 'FIXED_TIME', 'compresslevel=9',
    "'config/local.php'", "'.env'", "'rss.sql'", "'rss.zip'",
    'RELEASE_BUILD.txt', 'RELEASE_MANIFEST.sha256', 'publishable',
]:
    check(term in builder, f'builder contains release safety contract: {term}')

check("version != 'M4-E R1'" in builder, 'preview mode requires exact M4-E marker')
check("version != INTENDED_RELEASE" in builder, 'final mode requires intended final version')
check("label != 'RSS Reader Modernization 1.0.0'" in builder, 'final mode requires exact final label')
check('path.is_symlink()' in builder, 'builder rejects symlink source files')
check("lower.endswith(FORBIDDEN_SUFFIXES)" in builder, 'builder rejects forbidden runtime/database/archive extensions')
check("if generated:" in builder and 'runtime directory contains generated files' in builder, 'builder rejects generated runtime files')
check('hashlib.sha256(zip_path.read_bytes())' in builder, 'builder creates external ZIP SHA-256')
check("newline='\\n'" in builder, 'builder writes stable SHA-256 sidecar line ending')

for term in [
    'archive.testzip()', 'no duplicate entries', 'no parent traversal path',
    'release manifest file set matches ZIP payload', 'release metadata matches APP_VERSION',
    'high-signal secret pattern', 'preview package is not publishable',
]:
    check(term in verifier, f'verifier contains release validation: {term}')

# Safe tag and release procedure.
for term in [
    'git pull --ff-only', 'git status --short', 'git tag -a v1.0.0',
    'git show --no-patch --decorate v1.0.0', 'git push origin v1.0.0',
    'rss-reader-modernization-1.0.0.zip.sha256', 'Draft a new release',
    '公開済みTagを別Commitへ黙って移動しません', 'v1.0.1',
]:
    check(term in tag, f'tag / release guide contains safety step: {term}')
check('git push --tags' in tag and '使用しません' in tag, 'guide warns against pushing unrelated tags')
check('git reset --hard' not in tag, 'tag procedure does not use destructive reset')
check('git tag -f' not in tag and '--force' not in tag, 'tag procedure does not force-move tag')
check(tag.find('M4-Eでは手順を確定') < tag.find('Annotated Tag'), 'guide states M4-E non-execution before commands')

check('同じSourceから同じmodeで2回BuildしたZIPは同じSHA-256' in package, 'package docs define reproducible build evidence')
check('Release ZIPへ含めない' in package, 'package docs define exclusions')
check('`final` mode' in package and '完全一致しない限り実行できません' in package, 'package docs define final mode guard')

# No command should create the final tag during M4-E application.
checklist = (ROOT / 'CHECKLIST_FOR_USER.md').read_text(encoding='utf-8')
check('git tag -a v1.0.0' not in checklist, 'user checklist does not execute annotated final tag')
check('まだ行わないこと' in checklist and checklist.find('まだ行わないこと') < checklist.find('git tag v1.0.0'), 'user checklist explicitly defers final release')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M4-E release process checks passed.')
