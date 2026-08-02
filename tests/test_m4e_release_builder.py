#!/usr/bin/env python3
from __future__ import annotations

import hashlib
from pathlib import Path, PurePosixPath
import subprocess
import shutil
import sys
import tempfile
import zipfile

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



# Remove generated Python cache before testing the release allowlist.
for cache_dir in sorted(ROOT.rglob('__pycache__'), reverse=True):
    if cache_dir.is_dir():
        shutil.rmtree(cache_dir)
for cache_file in ROOT.rglob('*.py[co]'):
    if cache_file.is_file():
        cache_file.unlink()

builder = ROOT / 'tools/build_release_package.py'
verifier = ROOT / 'tools/verify_release_package.py'
check(builder.is_file(), 'release package builder exists')
check(verifier.is_file(), 'release package verifier exists')

with tempfile.TemporaryDirectory(prefix='rss-m4e-build-a-') as tmp_a, tempfile.TemporaryDirectory(prefix='rss-m4e-build-b-') as tmp_b:
    out_a = Path(tmp_a)
    out_b = Path(tmp_b)
    run(sys.executable, str(builder), '--mode', 'preview', '--output-dir', str(out_a))
    run(sys.executable, str(builder), '--mode', 'preview', '--output-dir', str(out_b))

    zip_a = out_a / 'rss-reader-modernization-1.0.0-preview-m4-e.zip'
    zip_b = out_b / 'rss-reader-modernization-1.0.0-preview-m4-e.zip'
    side_a = out_a / 'rss-reader-modernization-1.0.0-preview-m4-e.zip.sha256'
    side_b = out_b / 'rss-reader-modernization-1.0.0-preview-m4-e.zip.sha256'
    for path in [zip_a, zip_b, side_a, side_b]:
        check(path.is_file() and path.stat().st_size > 50, f'preview artifact exists: {path.name}')

    digest_a = hashlib.sha256(zip_a.read_bytes()).hexdigest()
    digest_b = hashlib.sha256(zip_b.read_bytes()).hexdigest()
    check(digest_a == digest_b, 'two preview builds are byte-for-byte deterministic')
    check(side_a.read_text(encoding='ascii') == side_b.read_text(encoding='ascii'), 'two SHA-256 sidecars are identical')
    check(side_a.read_text(encoding='ascii').startswith(digest_a + '  '), 'sidecar contains actual ZIP SHA-256')

    verified = run(sys.executable, str(verifier), str(zip_a), str(side_a))
    check('release package checks passed' in verified.stdout, 'standalone verifier accepts preview package')

    with zipfile.ZipFile(zip_a) as archive:
        check(archive.testzip() is None, 'preview ZIP passes CRC verification')
        names = [info.filename for info in archive.infolist() if not info.is_dir()]
        check(len(names) == len(set(names)), 'preview ZIP contains no duplicate entries')
        check(all('\\' not in name for name in names), 'preview ZIP paths use forward slashes')
        check(all('..' not in PurePosixPath(name).parts for name in names), 'preview ZIP has no parent traversal path')
        tops = {PurePosixPath(name).parts[0] for name in names}
        check(tops == {'rss-reader-modernization-1.0.0-preview-m4-e'}, 'preview ZIP top-level directory is stable')
        rels = {'/'.join(PurePosixPath(name).parts[1:]): name for name in names}
        for rel in [
            'RELEASE_BUILD.txt', 'RELEASE_MANIFEST.sha256', 'RELEASE_NOTES.md',
            'app/version.php', 'public/index.php', 'config/local.php.example',
            'database/schema.sql', 'docs/installation.md', 'docs/release-package.md',
            'docs/tag-and-github-release.md', 'LICENSE', 'THIRD_PARTY_NOTICES.md',
        ]:
            check(rel in rels, f'preview ZIP includes release file: {rel}')
        for rel in ['CHECKLIST_FOR_USER.md', '.gitignore', '.github/workflows/ci.yml']:
            check(rel not in rels, f'preview ZIP excludes repository/checkpoint file: {rel}')
        check(not any(rel.startswith('tests/') for rel in rels), 'preview ZIP excludes test suite')
        check(not any(rel.lower().endswith('.zip') for rel in rels), 'preview ZIP contains no nested ZIP')
        build = archive.read(rels['RELEASE_BUILD.txt']).decode('utf-8')
        check('package_status=PREVIEW' in build, 'preview build metadata has PREVIEW status')
        check('publishable=no' in build, 'preview build metadata forbids publication')
        check('application_version=M4-E R1' in build, 'preview build metadata records M4-E version')
        notes = archive.read(rels['RELEASE_NOTES.md']).decode('utf-8')
        check('正式Releaseではありません' in notes, 'preview notes warn against formal release')

# M4-E marker must not produce RC or final packages.
rc = run(sys.executable, str(builder), '--mode', 'rc', '--output-dir', tempfile.gettempdir(), expect=1)
check('rc mode requires APP_VERSION' in rc.stderr, 'RC mode rejects M4-E version marker')
final = run(sys.executable, str(builder), '--mode', 'final', '--output-dir', tempfile.gettempdir(), expect=1)
check('final mode requires the exact 1.0.0 version' in final.stderr, 'final mode rejects M4-E version marker')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M4-E release builder checks passed.')
