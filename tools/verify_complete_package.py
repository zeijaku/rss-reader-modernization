#!/usr/bin/env python3
from __future__ import annotations

import argparse
import hashlib
from pathlib import Path, PurePosixPath
import re
import sys
import zipfile

SEMVER = re.compile(r'[0-9]+\.[0-9]+\.[0-9]+')
FORBIDDEN_EXACT = {'config/local.php', '.env', 'rss.sql', 'rss.zip'}
FORBIDDEN_SUFFIXES = ('.sqlite', '.sqlite3', '.db', '.dump', '.bak', '.backup', '.log', '.pid', '.zip')
RUNTIME_DIRS = (
    'var/session', 'var/log', 'var/cache', 'var/db-migration',
    'var/security/login-throttle', 'var/m4f-evidence',
)
REQUIRED = {
    '.github/workflows/ci.yml',
    '.github/workflows/release.yml',
    '.gitignore',
    'README.md',
    'CHANGELOG.md',
    'RELEASE_NOTES.md',
    'app/version.php',
    'database/schema.sql',
    'tests/run.sh',
    'tools/build_release_package.py',
    'tools/build_complete_package.py',
    'SOURCE_BUILD.txt',
    'SOURCE_MANIFEST.sha256',
}


def check(ok: bool, message: str) -> None:
    print(('PASS' if ok else 'FAIL') + ': ' + message)
    if not ok:
        raise AssertionError(message)


def validate_release(value: str) -> str:
    check(SEMVER.fullmatch(value) is not None, 'requested release version is valid semantic version')
    return value


def main() -> int:
    parser = argparse.ArgumentParser(description='Verify complete RSS Reader source package.')
    parser.add_argument('--release', required=True, help='Expected final version, for example X.Y.Z')
    parser.add_argument('zip_path', type=Path)
    parser.add_argument('sha256_sidecar', type=Path)
    args = parser.parse_args()
    release = validate_release(args.release)
    top = f'rss-reader-modernization-{release}-complete'
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
        check(
            all(
                '\\' not in name
                and not PurePosixPath(name).is_absolute()
                and '..' not in PurePosixPath(name).parts
                for name in names
            ),
            'complete ZIP has no unsafe path',
        )
        check({PurePosixPath(name).parts[0] for name in names} == {top}, 'complete ZIP top-level directory is exact')
        relative = {'/'.join(PurePosixPath(name).parts[1:]): name for name in names}
        check(REQUIRED <= set(relative), 'complete ZIP contains current workflows, repository, tests and source files')
        check(
            not any(rel in FORBIDDEN_EXACT or rel.lower().endswith(FORBIDDEN_SUFFIXES) for rel in relative),
            'complete ZIP excludes private/database/archive files',
        )
        generated = [
            rel
            for rel in relative
            for runtime in RUNTIME_DIRS
            if rel.startswith(runtime + '/') and PurePosixPath(rel).name != '.gitkeep'
        ]
        check(not generated, 'complete ZIP excludes generated runtime data')

        build = archive.read(relative['SOURCE_BUILD.txt']).decode('utf-8')
        metadata = dict(line.split('=', 1) for line in build.splitlines() if '=' in line)
        check(metadata.get('application_version') == release, 'source metadata application version matches requested release')
        check(metadata.get('application_label') == f'RSS Reader Modernization {release}', 'source metadata application label matches requested release')
        check(metadata.get('application_asset_revision') == release, 'source metadata asset revision matches requested release')
        check(metadata.get('intended_release') == release, 'source metadata intended release matches requested release')
        check(metadata.get('intended_tag') == f'v{release}', 'source metadata intended tag matches requested release')
        check(metadata.get('package_status') == 'FINAL', 'complete package status is FINAL')
        check(metadata.get('publishable') == 'yes', 'complete package is marked publishable')

        version_text = archive.read(relative['app/version.php']).decode('utf-8')
        for name, expected in (
            ('APP_VERSION', release),
            ('APP_VERSION_LABEL', f'RSS Reader Modernization {release}'),
            ('APP_ASSET_REVISION', release),
        ):
            match = re.search(rf"{name}\s*=\s*'([^']+)'", version_text)
            check(match is not None, f'complete ZIP contains readable {name}')
            check(match.group(1) == expected, f'complete ZIP {name} matches requested release')

        manifest_text = archive.read(relative['SOURCE_MANIFEST.sha256']).decode('utf-8')
        manifest: dict[str, str] = {}
        for line in manifest_text.splitlines():
            if not line.strip():
                continue
            digest, rel = line.split('  ', 1)
            check(
                re.fullmatch(r'[0-9a-f]{64}', digest) is not None and rel not in manifest,
                f'manifest entry is valid: {rel}',
            )
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
