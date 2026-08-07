#!/usr/bin/env python3
from __future__ import annotations

import argparse
import hashlib
from pathlib import Path, PurePosixPath
import stat
import sys
import zipfile

ROOT = Path(__file__).resolve().parents[1]
VERSION = '1.7.0'
ARTIFACT = f'rss-reader-modernization-{VERSION}-complete'
FIXED_TIME = (1980, 1, 1, 0, 0, 0)
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


def collect() -> dict[str, bytes]:
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
        if rel in FORBIDDEN_EXACT or lower.endswith(FORBIDDEN_SUFFIXES):
            fail(f'forbidden file in complete package: {rel}')
        if posix.is_absolute() or '..' in posix.parts or '\\' in rel:
            fail(f'unsafe path: {rel}')
        for runtime in RUNTIME_DIRS:
            if rel.startswith(runtime + '/') and posix.name != '.gitkeep':
                fail(f'generated runtime file found: {rel}')
        payload[rel] = path.read_bytes()
    required = {'.github/workflows/ci.yml', 'tests/run.sh', 'app/version.php', 'database/schema.sql'}
    if not required <= set(payload):
        fail('complete package is missing source/test/repository files')
    build = '\n'.join([
        'package_type=complete-source',
        f'application_version={VERSION}',
        f'application_label=RSS Reader Modernization {VERSION}',
        f'intended_tag=v{VERSION}',
        'runtime_data=excluded',
        'manifest=SOURCE_MANIFEST.sha256',
        '',
    ]).encode('utf-8')
    payload['SOURCE_BUILD.txt'] = build
    manifest = '\n'.join(f'{sha256(data)}  {rel}' for rel, data in sorted(payload.items())) + '\n'
    payload['SOURCE_MANIFEST.sha256'] = manifest.encode('utf-8')
    return payload


def info(name: str) -> zipfile.ZipInfo:
    result = zipfile.ZipInfo(name, FIXED_TIME)
    result.compress_type = zipfile.ZIP_DEFLATED
    result.create_system = 3
    result.external_attr = (stat.S_IFREG | 0o644) << 16
    return result


def main() -> int:
    parser = argparse.ArgumentParser(description='Build deterministic complete Version 1.7.0 source package.')
    parser.add_argument('--output-dir', type=Path, default=ROOT / 'dist')
    args = parser.parse_args()
    output = args.output_dir.resolve()
    output.mkdir(parents=True, exist_ok=True)
    payload = collect()
    zip_path = output / f'{ARTIFACT}.zip'
    sidecar = output / f'{ARTIFACT}.zip.sha256'
    if zip_path.exists():
        zip_path.unlink()
    with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for rel, data in sorted(payload.items()):
            archive.writestr(info(f'{ARTIFACT}/{rel}'), data)
    digest = hashlib.sha256(zip_path.read_bytes()).hexdigest()
    sidecar.write_text(f'{digest}  {zip_path.name}\n', encoding='ascii', newline='\n')
    print(f'Created: {zip_path}')
    print(f'SHA-256: {digest}')
    print(f'Files: {len(payload)}')
    return 0


if __name__ == '__main__':
    sys.exit(main())
