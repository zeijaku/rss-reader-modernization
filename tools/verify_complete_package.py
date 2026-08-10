#!/usr/bin/env python3
from __future__ import annotations

import argparse
import hashlib
from pathlib import Path, PurePosixPath
import re
import sys
import zipfile

VERSION = '1.12.0'
TOP = f'rss-reader-modernization-{VERSION}-complete'
FORBIDDEN_EXACT = {'config/local.php', '.env', 'rss.sql', 'rss.zip'}
FORBIDDEN_SUFFIXES = ('.sqlite', '.sqlite3', '.db', '.dump', '.bak', '.backup', '.log', '.pid', '.zip')
RUNTIME_DIRS = ('var/session', 'var/log', 'var/cache/feed', 'var/db-migration', 'var/security/login-throttle', 'var/m4f-evidence')
REQUIRED = {
    '.github/workflows/ci.yml', '.gitignore', 'README.md', 'CHANGELOG.md', 'RELEASE_NOTES.md',
    'app/version.php', 'database/schema.sql', 'tests/run.sh', 'tools/build_release_package.py',
    'tools/build_complete_package.py', 'SOURCE_BUILD.txt', 'SOURCE_MANIFEST.sha256',
}


def check(ok: bool, message: str) -> None:
    print(('PASS' if ok else 'FAIL') + ': ' + message)
    if not ok:
        raise AssertionError(message)


def main() -> int:
    parser = argparse.ArgumentParser(description='Verify complete Version 1.12.0 source package.')
    parser.add_argument('zip_path', type=Path)
    parser.add_argument('sha256_sidecar', type=Path)
    args = parser.parse_args()
    zip_path = args.zip_path.resolve()
    sidecar = args.sha256_sidecar.resolve()
    check(zip_path.is_file(), 'complete ZIP exists')
    check(sidecar.is_file(), 'complete ZIP SHA-256 sidecar exists')
    side = sidecar.read_text(encoding='ascii').strip().split('  ', 1)
    check(len(side) == 2 and re.fullmatch(r'[0-9a-f]{64}', side[0]) is not None, 'sidecar format is valid')
    check(side[1] == zip_path.name, 'sidecar names complete ZIP')
    check(side[0] == hashlib.sha256(zip_path.read_bytes()).hexdigest(), 'complete ZIP SHA-256 matches')
    with zipfile.ZipFile(zip_path) as archive:
        check(archive.testzip() is None, 'complete ZIP passes CRC verification')
        names = [i.filename for i in archive.infolist() if not i.is_dir()]
        check(len(names) == len(set(names)), 'complete ZIP has no duplicate paths')
        check(all('\\' not in n and not PurePosixPath(n).is_absolute() and '..' not in PurePosixPath(n).parts for n in names), 'complete ZIP has no unsafe path')
        check({PurePosixPath(n).parts[0] for n in names} == {TOP}, 'complete ZIP top-level directory is exact')
        relative = {'/'.join(PurePosixPath(n).parts[1:]): n for n in names}
        check(REQUIRED <= set(relative), 'complete ZIP contains repository, tests and source files')
        check(not any(rel in FORBIDDEN_EXACT or rel.lower().endswith(FORBIDDEN_SUFFIXES) for rel in relative), 'complete ZIP excludes private/database/archive files')
        generated = [rel for rel in relative for runtime in RUNTIME_DIRS if rel.startswith(runtime + '/') and PurePosixPath(rel).name != '.gitkeep']
        check(not generated, 'complete ZIP excludes generated runtime data')
        build = archive.read(relative['SOURCE_BUILD.txt']).decode('utf-8')
        check('application_version=1.12.0' in build and 'intended_tag=v1.12.0' in build, 'source build metadata targets Version 1.12.0')
        version = archive.read(relative['app/version.php']).decode('utf-8')
        check("APP_VERSION = '1.12.0'" in version and "APP_VERSION_LABEL = 'RSS Reader Modernization 1.12.0'" in version, 'complete ZIP has exact release marker')
        manifest_text = archive.read(relative['SOURCE_MANIFEST.sha256']).decode('utf-8')
        manifest: dict[str, str] = {}
        for line in manifest_text.splitlines():
            digest, rel = line.split('  ', 1)
            check(re.fullmatch(r'[0-9a-f]{64}', digest) is not None and rel not in manifest, f'manifest entry is valid: {rel}')
            manifest[rel] = digest
        check(set(manifest) == set(relative) - {'SOURCE_MANIFEST.sha256'}, 'source manifest covers all payload files')
        for rel, digest in sorted(manifest.items()):
            check(hashlib.sha256(archive.read(relative[rel])).hexdigest() == digest, f'source manifest matches: {rel}')
    print(f'Complete package verification passed: {zip_path.name}')
    return 0


if __name__ == '__main__':
    try:
        sys.exit(main())
    except (AssertionError, OSError, ValueError, zipfile.BadZipFile) as exc:
        print(f'ERROR: {exc}', file=sys.stderr)
        sys.exit(1)
