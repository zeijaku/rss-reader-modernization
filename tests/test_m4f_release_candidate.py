#!/usr/bin/env python3
from __future__ import annotations

import hashlib
from pathlib import Path, PurePosixPath
import subprocess
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


builder = ROOT / 'tools/build_release_package.py'
verifier = ROOT / 'tools/verify_release_package.py'
check(builder.is_file(), 'release package builder exists')
check(verifier.is_file(), 'release package verifier exists')

with tempfile.TemporaryDirectory(prefix='rss-m4f-rc-a-') as tmp_a, tempfile.TemporaryDirectory(prefix='rss-m4f-rc-b-') as tmp_b:
    out_a = Path(tmp_a)
    out_b = Path(tmp_b)
    run(sys.executable, str(builder), '--mode', 'rc', '--output-dir', str(out_a))
    run(sys.executable, str(builder), '--mode', 'rc', '--output-dir', str(out_b))

    zip_a = out_a / 'rss-reader-modernization-1.0.0-rc1.zip'
    zip_b = out_b / 'rss-reader-modernization-1.0.0-rc1.zip'
    side_a = out_a / 'rss-reader-modernization-1.0.0-rc1.zip.sha256'
    side_b = out_b / 'rss-reader-modernization-1.0.0-rc1.zip.sha256'
    for path in [zip_a, zip_b, side_a, side_b]:
        check(path.is_file() and path.stat().st_size > 50, f'RC artifact exists: {path.name}')

    digest_a = hashlib.sha256(zip_a.read_bytes()).hexdigest()
    digest_b = hashlib.sha256(zip_b.read_bytes()).hexdigest()
    check(digest_a == digest_b, 'two RC builds are byte-for-byte deterministic')
    check(side_a.read_text(encoding='ascii') == side_b.read_text(encoding='ascii'), 'two RC sidecars are identical')
    check(side_a.read_text(encoding='ascii').startswith(digest_a + '  '), 'RC sidecar contains actual ZIP SHA-256')

    verified = run(sys.executable, str(verifier), str(zip_a), str(side_a))
    check('release package checks passed' in verified.stdout, 'standalone verifier accepts RC package')

    with zipfile.ZipFile(zip_a) as archive:
        check(archive.testzip() is None, 'RC ZIP passes CRC verification')
        names = [info.filename for info in archive.infolist() if not info.is_dir()]
        check(len(names) == len(set(names)), 'RC ZIP contains no duplicate entries')
        check(all('\\' not in name for name in names), 'RC ZIP paths use forward slashes')
        check(all('..' not in PurePosixPath(name).parts for name in names), 'RC ZIP has no parent traversal path')
        tops = {PurePosixPath(name).parts[0] for name in names}
        check(tops == {'rss-reader-modernization-1.0.0-rc1'}, 'RC ZIP top-level directory is exact')
        rels = {'/'.join(PurePosixPath(name).parts[1:]): name for name in names}
        for rel in [
            'RELEASE_BUILD.txt', 'RELEASE_MANIFEST.sha256', 'RELEASE_NOTES.md',
            'app/version.php', 'public/index.php', 'config/local.php.example',
            'database/schema.sql', 'docs/installation.md', 'docs/m4-f-validation.md',
            'tools/m4f_environment_probe.php', 'tools/m4f_evidence_gate.py',
            'LICENSE', 'THIRD_PARTY_NOTICES.md',
        ]:
            check(rel in rels, f'RC ZIP includes release file: {rel}')
        for rel in ['CHECKLIST_FOR_USER.md', '.gitignore', '.github/workflows/ci.yml',
                    'docs/m4-f-validation-template.json']:
            # Documentation JSON is included by release builder because docs are public.
            if rel == 'docs/m4-f-validation-template.json':
                check(rel in rels, f'RC ZIP includes safe validation template: {rel}')
            else:
                check(rel not in rels, f'RC ZIP excludes repository/checkpoint file: {rel}')
        check(not any(rel.startswith('tests/') for rel in rels), 'RC ZIP excludes test suite')
        check(not any(rel.startswith('var/m4f-evidence/') and PurePosixPath(rel).name != '.gitkeep' for rel in rels), 'RC ZIP excludes private M4-F evidence files')
        check(not any(rel.lower().endswith('.zip') for rel in rels), 'RC ZIP contains no nested ZIP')
        build = archive.read(rels['RELEASE_BUILD.txt']).decode('utf-8')
        check('package_status=RELEASE_CANDIDATE' in build, 'RC build metadata has RELEASE_CANDIDATE status')
        check('publishable=no' in build, 'RC build metadata forbids final publication')
        check('application_version=1.0.0-rc1' in build, 'RC build metadata records exact version')
        version = archive.read(rels['app/version.php']).decode('utf-8')
        check("APP_VERSION = '1.0.0-rc1'" in version, 'RC package has exact APP_VERSION')
        check("APP_VERSION_LABEL = 'RSS Reader Modernization 1.0.0-RC1'" in version, 'RC package has exact visible label')
        notes = archive.read(rels['RELEASE_NOTES.md']).decode('utf-8')
        check('m4-f release candidate' in notes.lower(), 'RC notes identify M4-F release candidate')
        check('正式Releaseではありません' in notes, 'RC notes warn against formal release')


# A private evidence result must stop the builder rather than be silently packaged.
evidence_result = ROOT / 'var/m4f-evidence/m4-f-result.json'
evidence_result.write_text('{"checkpoint":"1.0.0-rc1"}\n', encoding='utf-8')
try:
    blocked = run(sys.executable, str(builder), '--mode', 'rc', '--output-dir', tempfile.gettempdir(), expect=1)
    check('runtime directory contains generated files: var/m4f-evidence' in blocked.stderr, 'RC builder rejects private evidence payload')
finally:
    evidence_result.unlink(missing_ok=True)

preview = run(sys.executable, str(builder), '--mode', 'preview', '--output-dir', tempfile.gettempdir(), expect=1)
check('preview mode requires M4-E R1' in preview.stderr, 'preview mode rejects RC version marker')
final = run(sys.executable, str(builder), '--mode', 'final', '--output-dir', tempfile.gettempdir(), expect=1)
check('final mode requires the exact 1.0.0 version' in final.stderr, 'final mode rejects RC version marker')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M4-F release candidate checks passed.')
