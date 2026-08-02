#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path, PurePosixPath
import hashlib
import re
import sys
import tempfile
import zipfile

checks = 0


def check(condition: bool, message: str) -> None:
    global checks
    checks += 1
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        raise AssertionError(message)


if len(sys.argv) != 2:
    raise SystemExit('Usage: python tests/test_m4c_package.py <checkpoint.zip>')
zip_path = Path(sys.argv[1]).resolve()
check(zip_path.is_file(), 'checkpoint ZIP exists')

with zipfile.ZipFile(zip_path) as archive:
    bad = archive.testzip()
    check(bad is None, 'ZIP compressed data passes CRC verification')
    infos = archive.infolist()
    names = [info.filename for info in infos if not info.is_dir()]
    check(len(names) == len(set(names)), 'ZIP contains no duplicate file entries')
    check(all('\\' not in name for name in names), 'ZIP paths use forward slashes')
    check(all(not PurePosixPath(name).is_absolute() for name in names), 'ZIP contains no absolute path')
    check(all('..' not in PurePosixPath(name).parts for name in names), 'ZIP contains no parent traversal path')
    top = {PurePosixPath(name).parts[0] for name in names}
    check(len(top) == 1, 'ZIP contains one project top-level directory')
    lower = [name.lower() for name in names]
    check(not any(name.endswith('.zip') for name in lower), 'ZIP contains no nested ZIP')
    forbidden_names = {'config/local.php', '.env', 'rss.sql', 'rss.zip'}
    check(not any('/'.join(PurePosixPath(name).parts[1:]) in forbidden_names for name in names), 'ZIP excludes forbidden named files')
    forbidden_ext = ('.sqlite', '.sqlite3', '.db', '.dump', '.bak', '.backup', '.log', '.pid')
    check(not any(name.endswith(forbidden_ext) for name in lower), 'ZIP excludes runtime/database file extensions')

    with tempfile.TemporaryDirectory(prefix='rss-m4c-package-') as tmp:
        archive.extractall(tmp)
        project = Path(tmp) / next(iter(top))
        manifest = project / 'docs/package-manifest-m4-c-r1.txt'
        check(manifest.is_file(), 'M4-C package manifest exists after extraction')
        expected: dict[str, str] = {}
        for line in manifest.read_text(encoding='utf-8').splitlines():
            if not line.strip():
                continue
            digest, rel = line.split('  ', 1)
            expected[rel] = digest
        actual_files = {p.relative_to(project).as_posix(): p for p in project.rglob('*') if p.is_file()}
        check(set(actual_files) == set(expected) | {'docs/package-manifest-m4-c-r1.txt'}, 'Manifest file set matches extracted ZIP')
        for rel, digest in expected.items():
            actual = hashlib.sha256(actual_files[rel].read_bytes()).hexdigest()
            check(actual == digest, f'Manifest SHA-256 matches: {rel}')

        for rel in [
            'docs/installation.md', 'docs/update.md', 'docs/configuration.md', 'docs/backup-and-restore.md',
            'docs/rollback.md', 'docs/deployment-checklist.md', 'docs/m4-c-implementation.md',
            'docs/test-report-m4-c.md', 'LICENSE', 'THIRD_PARTY_NOTICES.md',
        ]:
            check((project / rel).is_file(), f'M4-C release/operations file is packaged: {rel}')

        version = (project / 'app/version.php').read_text(encoding='utf-8')
        check("APP_VERSION = 'M4-C R1'" in version, 'extracted package has M4-C version')
        check("APP_VERSION_LABEL = 'Release M4-C / R1'" in version, 'extracted package has M4-C label')

        secret_patterns = [
            re.compile(r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----'),
            re.compile(r'\bAKIA[0-9A-Z]{16}\b'),
            re.compile(r'\bsk-[A-Za-z0-9_-]{20,}\b'),
        ]
        secret_hits = []
        for dirname in ['app', 'public', 'config', 'database', 'tools']:
            for path in (project / dirname).rglob('*'):
                if not path.is_file():
                    continue
                try:
                    text = path.read_text(encoding='utf-8')
                except (UnicodeDecodeError, OSError):
                    continue
                if any(pattern.search(text) for pattern in secret_patterns):
                    secret_hits.append(path.relative_to(project).as_posix())
        check(not secret_hits, 'Extracted package contains no high-signal secret pattern')

        for runtime in ['var/session', 'var/log', 'var/cache/feed', 'var/db-migration', 'var/security/login-throttle']:
            directory = project / runtime
            generated = [p for p in directory.iterdir() if p.is_file() and p.name != '.gitkeep'] if directory.exists() else []
            check(not generated, f'Extracted runtime directory is clean: {runtime}')

print(f'All {checks} M4-C package checks passed.')
