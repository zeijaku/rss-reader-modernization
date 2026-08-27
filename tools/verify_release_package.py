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
FORBIDDEN_SUFFIXES = (
    '.sqlite', '.sqlite3', '.db', '.dump', '.bak', '.backup', '.log', '.pid', '.zip'
)
REQUIRED = {
    '.htaccess', 'README.md', 'CHANGELOG.md', 'LICENSE', 'THIRD_PARTY_NOTICES.md',
    'RELEASE_NOTES.md', 'SECURITY.md', 'app/version.php', 'public/index.php',
    'public/api_v1.php', 'config/local.php.example', 'config/.env.example',
    'database/schema.sql', 'tools/healthcheck.php', 'tools/db_sb13.php',
    'docs/installation.md', 'docs/update.md', 'docs/configuration.md',
    'docs/backup-and-restore.md', 'docs/rollback.md', 'docs/deployment-checklist.md',
    'docs/release-package.md', 'docs/tag-and-github-release.md',
    'RELEASE_BUILD.txt', 'RELEASE_MANIFEST.sha256',
}

checks = 0


def check(condition: bool, message: str) -> None:
    global checks
    checks += 1
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        raise AssertionError(message)


def validate_release(value: str) -> str:
    check(SEMVER.fullmatch(value) is not None, 'requested release version is valid semantic version')
    return value


def parse_sidecar(path: Path) -> tuple[str, str]:
    parts = path.read_text(encoding='ascii').strip().split('  ', 1)
    check(len(parts) == 2, 'SHA-256 sidecar has digest and filename')
    check(bool(re.fullmatch(r'[0-9a-f]{64}', parts[0])), 'SHA-256 sidecar digest format is valid')
    return parts[0], parts[1]


def main() -> int:
    parser = argparse.ArgumentParser(description='Verify RSS Reader release package.')
    parser.add_argument('--release', required=True, help='Expected final version, for example X.Y.Z')
    parser.add_argument('zip_path', type=Path)
    parser.add_argument('sha256_sidecar', type=Path, nargs='?')
    args = parser.parse_args()
    release = validate_release(args.release)
    intended_tag = f'v{release}'
    zip_path = args.zip_path.resolve()
    check(zip_path.is_file(), 'release ZIP exists')

    actual_zip_digest = hashlib.sha256(zip_path.read_bytes()).hexdigest()
    if args.sha256_sidecar:
        sidecar = args.sha256_sidecar.resolve()
        check(sidecar.is_file(), 'SHA-256 sidecar exists')
        expected_digest, expected_name = parse_sidecar(sidecar)
        check(expected_name == zip_path.name, 'SHA-256 sidecar names the release ZIP')
        check(expected_digest == actual_zip_digest, 'release ZIP SHA-256 matches sidecar')

    with zipfile.ZipFile(zip_path) as archive:
        check(archive.testzip() is None, 'ZIP compressed data passes CRC verification')
        infos = [info for info in archive.infolist() if not info.is_dir()]
        names = [info.filename for info in infos]
        check(len(names) == len(set(names)), 'ZIP contains no duplicate entries')
        check(all('\\' not in name for name in names), 'ZIP paths use forward slashes')
        check(all(not PurePosixPath(name).is_absolute() for name in names), 'ZIP contains no absolute path')
        check(all('..' not in PurePosixPath(name).parts for name in names), 'ZIP contains no parent traversal path')
        top = {PurePosixPath(name).parts[0] for name in names}
        check(len(top) == 1, 'ZIP contains one top-level directory')
        top_name = next(iter(top))
        relative = {'/'.join(PurePosixPath(name).parts[1:]): name for name in names}
        check(REQUIRED <= set(relative), 'release package contains all required files')
        check(not any(rel in FORBIDDEN_EXACT for rel in relative), 'release package excludes private and legacy exact files')
        check(not any(rel.lower().endswith(FORBIDDEN_SUFFIXES) for rel in relative), 'release package excludes runtime/database/archive extensions')
        check(not any('/tests/' in '/' + rel or rel.startswith('tests/') for rel in relative), 'runtime release package excludes test suite')
        check(not any(rel.startswith('.github/') for rel in relative), 'runtime release package excludes GitHub metadata')
        evidence_payload = [
            rel for rel in relative
            if rel.startswith('var/m4f-evidence/') and PurePosixPath(rel).name != '.gitkeep'
        ]
        check(not evidence_payload, 'release package excludes private M4-F evidence files')

        build = archive.read(relative['RELEASE_BUILD.txt']).decode('utf-8')
        metadata = dict(line.split('=', 1) for line in build.splitlines() if '=' in line)
        check(metadata.get('intended_release') == release, f'release build metadata targets {release}')
        check(metadata.get('intended_tag') == intended_tag, f'release build metadata targets {intended_tag}')
        check(metadata.get('package_status') in {'PREVIEW', 'RELEASE_CANDIDATE', 'FINAL'}, 'release build status is recognized')
        check(metadata.get('publishable') in {'yes', 'no'}, 'release build publishable flag is explicit')
        check(metadata.get('validation_scope') == 'automated-regression-and-package', 'release build validation scope is explicit')
        check(metadata.get('manual_evidence') == 'not-recorded-in-distribution', 'release build manual evidence status is explicit')

        status = metadata.get('package_status')
        app_version = metadata.get('application_version', '')
        if status == 'PREVIEW':
            expected_top = f'rss-reader-modernization-{release}-preview'
        elif status == 'RELEASE_CANDIDATE':
            expected_top = f'rss-reader-modernization-{app_version}'
        else:
            expected_top = f'rss-reader-modernization-{release}'
        check(top_name == expected_top, 'release package top-level directory matches package status/version')

        manifest_text = archive.read(relative['RELEASE_MANIFEST.sha256']).decode('utf-8')
        manifest: dict[str, str] = {}
        for line in manifest_text.splitlines():
            if not line.strip():
                continue
            digest, rel = line.split('  ', 1)
            check(bool(re.fullmatch(r'[0-9a-f]{64}', digest)), f'manifest digest format is valid: {rel}')
            check(rel not in manifest, f'manifest path is unique: {rel}')
            manifest[rel] = digest
        expected_manifest_set = set(relative) - {'RELEASE_MANIFEST.sha256'}
        check(set(manifest) == expected_manifest_set, 'release manifest file set matches ZIP payload')
        for rel, digest in sorted(manifest.items()):
            actual = hashlib.sha256(archive.read(relative[rel])).hexdigest()
            check(actual == digest, f'release manifest SHA-256 matches: {rel}')

        version_text = archive.read(relative['app/version.php']).decode('utf-8')
        values: dict[str, str] = {}
        for name in ('APP_VERSION', 'APP_VERSION_LABEL', 'APP_ASSET_REVISION'):
            match = re.search(rf"{name}\s*=\s*'([^']+)'", version_text)
            check(match is not None, f'release package contains readable {name}')
            values[name] = match.group(1)
        check(values['APP_VERSION'] == metadata.get('application_version'), 'release metadata matches APP_VERSION')
        check(values['APP_VERSION_LABEL'] == metadata.get('application_label'), 'release metadata matches APP_VERSION_LABEL')
        check(values['APP_ASSET_REVISION'] == metadata.get('application_asset_revision'), 'release metadata matches APP_ASSET_REVISION')

        notes = archive.read(relative['RELEASE_NOTES.md']).decode('utf-8')
        if status == 'PREVIEW':
            check(metadata.get('publishable') == 'no', 'preview package is not publishable')
            check(
                re.fullmatch(re.escape(release) + r'-dev\.[1-9][0-9]*', values['APP_VERSION']) is not None,
                'preview application version format is valid',
            )
            check('正式Releaseではありません' in notes, 'preview release notes contain non-release warning')
        if status == 'RELEASE_CANDIDATE':
            check(metadata.get('publishable') == 'no', 'release candidate package is not final-publishable')
            check(
                re.fullmatch(re.escape(release) + r'-rc[1-9][0-9]*', values['APP_VERSION']) is not None,
                'release candidate version format is valid',
            )
            check('正式Releaseではありません' in notes, 'release candidate notes contain non-release warning')
        if status == 'FINAL':
            check(metadata.get('publishable') == 'yes', 'final package is marked publishable')
            check(values['APP_VERSION'] == release, 'final package APP_VERSION matches requested release')
            check(values['APP_VERSION_LABEL'] == f'RSS Reader Modernization {release}', 'final package label matches requested release')
            check(values['APP_ASSET_REVISION'] == release, 'final package asset revision matches requested release')
            check('正式Releaseではありません' not in notes, 'final release notes contain no RC non-release warning')
            check('Verification limits' in notes, 'final release notes disclose verification limits')

        secret_patterns = [
            re.compile(r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----'),
            re.compile(r'\bAKIA[0-9A-Z]{16}\b'),
            re.compile(r'\bsk-[A-Za-z0-9_-]{20,}\b'),
        ]
        hits: list[str] = []
        for rel, full in relative.items():
            try:
                text = archive.read(full).decode('utf-8')
            except UnicodeDecodeError:
                continue
            if any(pattern.search(text) for pattern in secret_patterns):
                hits.append(rel)
        check(not hits, 'release package contains no high-signal secret pattern')

    print(f'All {checks} release package checks passed for {zip_path.name}.')
    return 0


if __name__ == '__main__':
    try:
        sys.exit(main())
    except (AssertionError, OSError, ValueError, zipfile.BadZipFile) as exc:
        print(f'ERROR: {exc}', file=sys.stderr)
        sys.exit(1)
