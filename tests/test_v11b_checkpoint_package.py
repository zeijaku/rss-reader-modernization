#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import re
import sys
import tempfile
import zipfile
from pathlib import Path

if len(sys.argv) != 2:
    raise SystemExit('usage: test_v11b_checkpoint_package.py <checkpoint.zip>')

archive = Path(sys.argv[1]).resolve()
assert archive.is_file(), f'ZIP not found: {archive}'
root_name = 'rss-reader-modernization-v1.1-b-r1/'
required = {
    root_name + 'app/url_normalizer.php',
    root_name + 'app/version.php',
    root_name + 'tests/test_v11b_tracking_parameters.php',
    root_name + 'tests/test_v11b_architecture.py',
    root_name + 'docs/v1-1-b-implementation.md',
    root_name + 'docs/test-report-v1-1-b.md',
    root_name + 'CHECKLIST_FOR_USER.md',
}
forbidden_parts = {'config/local.php', 'rss.sql', 'rss.zip', '.env'}
secret_patterns = [
    re.compile(rb'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----'),
    re.compile(rb'\bAKIA[0-9A-Z]{16}\b'),
    re.compile(rb'\bsk-[A-Za-z0-9_-]{20,}\b'),
]

with zipfile.ZipFile(archive) as zf:
    names = zf.namelist()
    assert len(names) == len(set(names)), 'duplicate ZIP entry found'
    assert all(name.startswith(root_name) for name in names), 'unexpected ZIP root'
    assert all('..' not in Path(name).parts for name in names), 'unsafe parent path found'
    assert required.issubset(names), f'missing required entries: {sorted(required - set(names))}'
    for name in names:
        lower = name.lower().rstrip('/')
        assert not any(lower.endswith(part) for part in forbidden_parts), f'private/legacy file found: {name}'
        assert not (lower.endswith('.zip') and lower != archive.name.lower()), f'nested ZIP found: {name}'
        if name.endswith('/'):
            continue
        data = zf.read(name)
        for pattern in secret_patterns:
            assert not pattern.search(data), f'potential secret pattern found: {name}'

print(f'PASS: checkpoint ZIP structure ({len(names)} entries)')
print('PASS: required V1.1-B files')
print('PASS: unsafe/private/runtime artifact exclusions')
print('PASS: checkpoint secret-pattern scan')
print('SHA-256:', hashlib.sha256(archive.read_bytes()).hexdigest())
