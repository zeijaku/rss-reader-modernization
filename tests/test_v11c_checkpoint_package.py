#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import re
import sys
import zipfile
from pathlib import Path, PurePosixPath

if len(sys.argv) != 2:
    raise SystemExit('usage: test_v11c_checkpoint_package.py <overlay.zip>')

archive = Path(sys.argv[1]).resolve()
assert archive.is_file(), f'ZIP not found: {archive}'
root_name = 'rss-reader-modernization-v1.1-c-r1-overlay/'
required = {
    root_name + 'APPLY_NOTE.md',
    root_name + 'app/feed/feed_item_state.php',
    root_name + 'app/version.php',
    root_name + 'database/migrations/002_v1_1_feed_item_state.sql',
    root_name + 'database/audit/v1_1_c_preflight.sql',
    root_name + 'database/audit/v1_1_c_postflight.sql',
    root_name + 'tools/db_v11c.php',
    root_name + 'tests/test_v11c_feed_item_state.php',
    root_name + 'tests/test_v11c_architecture.py',
    root_name + 'tests/test_v11c_sql.py',
    root_name + 'tests/test_v11c_runner.py',
    root_name + 'docs/v1-1-c-implementation.md',
    root_name + 'docs/v1-1-c-migration.md',
    root_name + 'docs/test-report-v1-1-c.md',
    root_name + 'docs/v1-1-c-overlay-manifest.txt',
    root_name + 'CHECKLIST_FOR_USER.md',
}
forbidden_exact = {
    root_name + 'config/local.php',
    root_name + '.env',
    root_name + 'rss.sql',
    root_name + 'rss.zip',
}
secret_patterns = [
    re.compile(rb'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----'),
    re.compile(rb'\bAKIA[0-9A-Z]{16}\b'),
    re.compile(rb'\bsk-[A-Za-z0-9_-]{20,}\b'),
]

with zipfile.ZipFile(archive) as zf:
    infos = zf.infolist()
    names = [info.filename for info in infos]
    assert zf.testzip() is None, 'ZIP CRC failed'
    assert len(names) == len(set(names)), 'duplicate ZIP entry found'
    assert all(name.startswith(root_name) for name in names), 'unexpected ZIP root'
    for name in names:
        path = PurePosixPath(name)
        assert '\\' not in name, f'backslash ZIP path found: {name}'
        assert not path.is_absolute(), f'absolute ZIP path found: {name}'
        assert '..' not in path.parts, f'unsafe parent path found: {name}'
    assert required.issubset(names), f'missing required entries: {sorted(required - set(names))}'
    assert not forbidden_exact.intersection(names), f'private/legacy file found: {sorted(forbidden_exact.intersection(names))}'

    for info in infos:
        name = info.filename
        lower = name.lower().rstrip('/')
        assert not lower.endswith(('.sqlite', '.sqlite3', '.db', '.sql.gz', '.log', '.session')), f'runtime/private data found: {name}'
        assert not lower.endswith('.zip'), f'nested ZIP found: {name}'
        if info.is_dir():
            continue
        data = zf.read(name)
        for pattern in secret_patterns:
            assert not pattern.search(data), f'potential secret pattern found: {name}'

    manifest_name = root_name + 'docs/v1-1-c-overlay-manifest.txt'
    manifest = [line.strip() for line in zf.read(manifest_name).decode('utf-8').splitlines() if line.strip() and not line.startswith('#')]
    payload = sorted(name[len(root_name):] for name in names if not name.endswith('/') and name != manifest_name)
    assert sorted(manifest) == payload, 'overlay manifest does not match ZIP payload'

print(f'PASS: V1.1-C Overlay ZIP structure ({len(names)} entries)')
print('PASS: required Migration, Runtime, Test and Documentation files')
print('PASS: unsafe/private/runtime artifact exclusions')
print('PASS: Overlay manifest matches payload')
print('PASS: checkpoint secret-pattern scan')
print('SHA-256:', hashlib.sha256(archive.read_bytes()).hexdigest())
