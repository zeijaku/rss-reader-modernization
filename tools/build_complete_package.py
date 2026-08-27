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
SEMVER = re.compile(r'[0-9]+\.[0-9]+\.[0-9]+')
FORBIDDEN_EXACT = {'config/local.php', '.env', 'rss.sql', 'rss.zip'}
FORBIDDEN_SUFFIXES = ('.sqlite', '.sqlite3', '.db', '.dump', '.bak', '.backup', '.log', '.pid', '.zip')
EXCLUDED_TOP = {'.git', 'dist', '.idea', '.vscode'}
RUNTIME_DIRS = (
    'var/session', 'var/log', 'var/cache', 'var/db-migration',
    'var/security/login-throttle', 'var/m4f-evidence',
)


def fail(message: str) -> None:
    raise SystemExit('ERROR: ' + message)


def sha256(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def validate_release(value: str) -> str:
    if not SEMVER.fullmatch(value):
        fail('--release must be a final semantic version such as X.Y.Z')
    return value


def read_version() -> tuple[str, str, str]:
    text = (ROOT / 'app/version.php').read_text(encoding='utf-8')
    values: dict[str, str] = {}
    for name in ('APP_VERSION', 'APP_VERSION_LABEL', 'APP_ASSET_REVISION'):
        match = re.search(rf"{name}\s*=\s*'([^']+)'", text)
        if not match:
            fail(f'app/version.php does not contain readable {name}')
        values[name] = match.group(1)
    return values['APP_VERSION'], values['APP_VERSION_LABEL'], values['APP_ASSET_REVISION']


def is_generated_runtime_file(rel: str) -> bool:
    posix = PurePosixPath(rel)
    return any(
        rel.startswith(runtime + '/') and posix.name != '.gitkeep'
        for runtime in RUNTIME_DIRS
    )


def collect(release: str) -> tuple[str, dict[str, bytes]]:
    version, label, asset_revision = read_version()
    if version != release:
        fail(f'complete package requires APP_VERSION={release}')
    if label != f'RSS Reader Modernization {release}':
        fail('complete package label does not match requested release')
    if asset_revision != release:
        fail('complete package requires APP_ASSET_REVISION to match requested release')

    artifact = f'rss-reader-modernization-{release}-complete'
    payload: dict[str, bytes] = {}
    for path in sorted(ROOT.rglob('*')):
        if not path.is_file() or path.is_symlink():
            continue
        rel = path.relative_to(ROOT).as_posix()
        posix = PurePosixPath(rel)
        lower = rel.lower()
        if posix.parts[0] in EXCLUDED_TOP:
            continue
        if rel in {'SOURCE_BUILD.txt', 'SOURCE_MANIFEST.sha256'}:
            continue
        if '__pycache__' in posix.parts or lower.endswith(('.pyc', '.pyo')):
            continue
        if is_generated_runtime_file(rel):
            continue
        if rel in FORBIDDEN_EXACT or lower.endswith(FORBIDDEN_SUFFIXES):
            fail(f'forbidden file in complete package: {rel}')
        if posix.is_absolute() or '..' in posix.parts or '\\' in rel:
            fail(f'unsafe path: {rel}')
        payload[rel] = path.read_bytes()

    generated = [rel for rel in payload if is_generated_runtime_file(rel)]
    if generated:
        fail('generated runtime files entered complete package: ' + ', '.join(generated[:5]))

    required = {
        '.github/workflows/ci.yml',
        '.github/workflows/release.yml',
        'tests/run.sh',
        'app/version.php',
        'database/schema.sql',
    }
    if not required <= set(payload):
        fail('complete package is missing current workflow/source/test files')

    build = '\n'.join([
        'package_type=complete-source',
        f'application_version={release}',
        f'application_label=RSS Reader Modernization {release}',
        f'application_asset_revision={release}',
        f'intended_release={release}',
        f'intended_tag=v{release}',
        'package_status=FINAL',
        'publishable=yes',
        'runtime_data=excluded',
        'manifest=SOURCE_MANIFEST.sha256',
        '',
    ]).encode('utf-8')
    payload['SOURCE_BUILD.txt'] = build
    manifest = '\n'.join(f'{sha256(data)}  {rel}' for rel, data in sorted(payload.items())) + '\n'
    payload['SOURCE_MANIFEST.sha256'] = manifest.encode('utf-8')
    return artifact, payload


def info(name: str) -> zipfile.ZipInfo:
    result = zipfile.ZipInfo(name, FIXED_TIME)
    result.compress_type = zipfile.ZIP_DEFLATED
    result.create_system = 3
    result.external_attr = (stat.S_IFREG | 0o644) << 16
    return result


def main() -> int:
    parser = argparse.ArgumentParser(description='Build deterministic complete RSS Reader source package.')
    parser.add_argument('--release', required=True, help='Intended final version, for example X.Y.Z')
    parser.add_argument('--output-dir', type=Path, default=ROOT / 'dist')
    args = parser.parse_args()
    release = validate_release(args.release)
    output = args.output_dir.resolve()
    output.mkdir(parents=True, exist_ok=True)
    artifact, payload = collect(release)
    zip_path = output / f'{artifact}.zip'
    sidecar = output / f'{artifact}.zip.sha256'
    if zip_path.exists():
        zip_path.unlink()
    with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for rel, data in sorted(payload.items()):
            archive.writestr(info(f'{artifact}/{rel}'), data)
    digest = hashlib.sha256(zip_path.read_bytes()).hexdigest()
    sidecar.write_text(f'{digest}  {zip_path.name}\n', encoding='ascii', newline='\n')
    print(f'Created: {zip_path}')
    print(f'SHA-256: {digest}')
    print(f'Files: {len(payload)}')
    return 0


if __name__ == '__main__':
    sys.exit(main())
