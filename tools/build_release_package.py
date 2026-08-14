#!/usr/bin/env python3
from __future__ import annotations

import argparse
import hashlib
from pathlib import Path, PurePosixPath
import re
import stat
import sys
import zipfile

ROOT = Path(__file__).resolve().parents[1]
FIXED_TIME = (1980, 1, 1, 0, 0, 0)
INTENDED_RELEASE = '1.14.0'
INTENDED_TAG = 'v1.14.0'

ROOT_FILES = (
    '.htaccess',
    'README.md',
    'CHANGELOG.md',
    'LICENSE',
    'THIRD_PARTY_NOTICES.md',
    'RELEASE_NOTES.md',
    'SECURITY.md',
)
DIRECTORIES = ('app', 'public', 'config', 'database', 'licenses', 'tools', 'var')
DOC_FILES = (
    'docs/installation.md',
    'docs/update.md',
    'docs/configuration.md',
    'docs/backup-and-restore.md',
    'docs/rollback.md',
    'docs/deployment-checklist.md',
    'docs/security.md',
    'docs/dependencies.md',
    'docs/release-package.md',
    'docs/tag-and-github-release.md',
    'docs/versioning.md',
)
FORBIDDEN_EXACT = {'config/local.php', '.env', 'rss.sql', 'rss.zip'}
FORBIDDEN_SUFFIXES = (
    '.sqlite', '.sqlite3', '.db', '.dump', '.bak', '.backup', '.log', '.pid', '.zip'
)
RUNTIME_DIRS = (
    'var/session',
    'var/log',
    'var/cache/feed',
    'var/db-migration',
    'var/security/login-throttle',
    'var/m4f-evidence',
)


def fail(message: str) -> None:
    raise SystemExit('ERROR: ' + message)


def sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def read_version() -> tuple[str, str]:
    text = (ROOT / 'app/version.php').read_text(encoding='utf-8')
    version_match = re.search(r"APP_VERSION\s*=\s*'([^']+)'", text)
    label_match = re.search(r"APP_VERSION_LABEL\s*=\s*'([^']+)'", text)
    if not version_match or not label_match:
        fail('app/version.php does not contain readable version constants')
    return version_match.group(1), label_match.group(1)


def validate_mode(mode: str, version: str, label: str) -> tuple[str, str, str]:
    if mode == 'preview':
        if not re.fullmatch(r'1\.14\.0-dev\.[1-9][0-9]*', version):
            fail('preview mode requires APP_VERSION such as 1.14.0-dev.9')
        if label != f'RSS Reader Modernization {version}':
            fail('preview mode label does not match APP_VERSION')
        return 'PREVIEW', 'no', 'rss-reader-modernization-1.14.0-preview'
    if mode == 'rc':
        if not re.fullmatch(r'1\.14\.0-rc[1-9][0-9]*', version):
            fail('rc mode requires APP_VERSION such as 1.14.0-rc1')
        if label != f'RSS Reader Modernization {version.upper()}':
            fail('rc mode label does not match APP_VERSION')
        return 'RELEASE_CANDIDATE', 'no', f'rss-reader-modernization-{version}'
    if mode == 'final':
        if version != INTENDED_RELEASE or label != 'RSS Reader Modernization 1.14.0':
            fail('final mode requires the exact 1.14.0 version and label')
        return 'FINAL', 'yes', 'rss-reader-modernization-1.14.0'
    fail('unsupported mode')


def collect_source_files() -> dict[str, Path]:
    files: dict[str, Path] = {}
    for rel in ROOT_FILES + DOC_FILES:
        path = ROOT / rel
        if not path.is_file():
            fail(f'required release file is missing: {rel}')
        files[rel] = path

    for dirname in DIRECTORIES:
        base = ROOT / dirname
        if not base.is_dir():
            fail(f'required release directory is missing: {dirname}')
        for path in sorted(base.rglob('*')):
            if not path.is_file():
                continue
            rel = path.relative_to(ROOT).as_posix()
            if path.is_symlink():
                fail(f'symlink is not allowed in release package: {rel}')
            files[rel] = path

    docs = ROOT / 'docs'
    if not docs.is_dir():
        fail('required release directory is missing: docs')
    for path in sorted(docs.rglob('*')):
        if not path.is_file():
            continue
        rel = path.relative_to(ROOT).as_posix()
        if path.name.startswith('package-manifest') and path.suffix == '.txt':
            continue
        if path.is_symlink():
            fail(f'symlink is not allowed in release package: {rel}')
        files[rel] = path

    for rel in sorted(files):
        posix = PurePosixPath(rel)
        lower = rel.lower()
        if posix.is_absolute() or '..' in posix.parts or '\\' in rel:
            fail(f'unsafe release path: {rel}')
        if rel in FORBIDDEN_EXACT:
            fail(f'private or legacy file is not allowed: {rel}')
        if lower.endswith(FORBIDDEN_SUFFIXES):
            fail(f'forbidden file extension in release package: {rel}')
        if '__pycache__' in posix.parts or lower.endswith(('.pyc', '.pyo')):
            fail(f'Python cache file is not allowed: {rel}')

    for runtime in RUNTIME_DIRS:
        generated = [
            rel for rel in files
            if rel.startswith(runtime + '/') and PurePosixPath(rel).name != '.gitkeep'
        ]
        if generated:
            fail(f'runtime directory contains generated files: {runtime}')

    return dict(sorted(files.items()))


def zip_info(name: str) -> zipfile.ZipInfo:
    info = zipfile.ZipInfo(name, FIXED_TIME)
    info.compress_type = zipfile.ZIP_DEFLATED
    info.create_system = 3
    info.external_attr = (stat.S_IFREG | 0o644) << 16
    return info


def build(mode: str, output_dir: Path) -> tuple[Path, Path]:
    version, label = read_version()
    status, publishable, artifact_stem = validate_mode(mode, version, label)
    source_files = collect_source_files()
    output_dir.mkdir(parents=True, exist_ok=True)
    zip_path = output_dir / f'{artifact_stem}.zip'
    sidecar_path = output_dir / f'{artifact_stem}.zip.sha256'
    top = artifact_stem

    build_text = '\n'.join([
        f'package_status={status}',
        f'application_version={version}',
        f'application_label={label}',
        f'intended_release={INTENDED_RELEASE}',
        f'intended_tag={INTENDED_TAG}',
        f'publishable={publishable}',
        'validation_scope=automated-regression-and-package',
        'manual_evidence=not-recorded-in-distribution',
        'manifest=RELEASE_MANIFEST.sha256',
        '',
    ]).encode('utf-8')

    payload: dict[str, bytes] = {
        rel: path.read_bytes() for rel, path in source_files.items()
    }
    payload['RELEASE_BUILD.txt'] = build_text
    manifest_lines = [
        f'{sha256_bytes(data)}  {rel}' for rel, data in sorted(payload.items())
    ]
    payload['RELEASE_MANIFEST.sha256'] = ('\n'.join(manifest_lines) + '\n').encode('utf-8')

    if zip_path.exists():
        zip_path.unlink()
    with zipfile.ZipFile(zip_path, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for rel, data in sorted(payload.items()):
            archive.writestr(zip_info(f'{top}/{rel}'), data)

    digest = hashlib.sha256(zip_path.read_bytes()).hexdigest()
    sidecar_path.write_text(f'{digest}  {zip_path.name}\n', encoding='ascii', newline='\n')
    print(f'Created: {zip_path}')
    print(f'SHA-256: {digest}')
    print(f'Files: {len(payload)}')
    print(f'Status: {status}')
    return zip_path, sidecar_path


def main() -> int:
    parser = argparse.ArgumentParser(description='Build deterministic RSS Reader release package.')
    parser.add_argument('--mode', choices=('preview', 'rc', 'final'), default='preview')
    parser.add_argument('--output-dir', type=Path, default=ROOT / 'dist')
    args = parser.parse_args()
    build(args.mode, args.output_dir.resolve())
    return 0


if __name__ == '__main__':
    sys.exit(main())
