#!/usr/bin/env python3
from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

required = [
    ROOT / 'README.md',
    ROOT / 'CHANGELOG.md',
    ROOT / 'docs' / 'legacy-analysis.md',
    ROOT / 'docs' / 'modernization.md',
    ROOT / 'docs' / 'security.md',
    ROOT / 'docs' / 'change-map.md',
    ROOT / 'docs' / 'roadmap.md',
    ROOT / 'docs' / 'initial-commit-gate.md',
    ROOT / 'docs' / 'm1-c-implementation.md',
    ROOT / 'docs' / 'test-report-m1-c.md',
    ROOT / 'docs' / 'm1-d-implementation.md',
    ROOT / 'docs' / 'test-report-m1-d.md',
    ROOT / 'docs' / 'm1-e-implementation.md',
    ROOT / 'docs' / 'test-report-m1-e.md',
    ROOT / 'docs' / 'm1-f-implementation.md',
    ROOT / 'docs' / 'test-report-m1-f.md',
    ROOT / 'docs' / 'm1-g-implementation.md',
    ROOT / 'docs' / 'test-report-m1-g.md',
]

for path in required:
    assert path.is_file(), f'missing required documentation: {path.relative_to(ROOT)}'
    assert path.stat().st_size > 200, f'document unexpectedly small: {path.relative_to(ROOT)}'

readme = (ROOT / 'README.md').read_text(encoding='utf-8')
version = (ROOT / 'app' / 'version.php').read_text(encoding='utf-8')
gate = (ROOT / 'docs' / 'initial-commit-gate.md').read_text(encoding='utf-8')
change_map = (ROOT / 'docs' / 'change-map.md').read_text(encoding='utf-8')

assert 'Secure Baseline SB-15 / R3' in readme
assert 'RSS Engine M1-G / R1' in readme
assert re.search(r"APP_VERSION = '(?:1\.[1-9][0-9]*\.\d+(?:-dev\.[1-9][0-9]*)?)'", version)
assert re.search(r"APP_VERSION_LABEL = '(?:RSS Reader Modernization V1\.1-[A-Z] / R[1-9][0-9]*|RSS Reader Modernization 1\.[1-9][0-9]*\.\d+(?:-dev\.[1-9][0-9]*)?)'", version)
assert 'Secure Baseline SB-15 / R3' in readme
assert 'PASS — Secure Baseline' in gate
assert 'DB_TABLE_PREFIX' in readme and '@table_prefix' in readme
assert 'APP_DEBUG=false' in readme
assert 'Source / RSS Engine' in readme

# R3 finalization: application/schema defaults must agree on the intended baseline values.
func = (ROOT / 'app' / 'common' / 'common_func.php').read_text(encoding='utf-8')
schema = (ROOT / 'database' / 'schema.sql').read_text(encoding='utf-8')
assert "'conf_style_tabname2' => 'Maint'" in func
assert "'conf_style_tabname4' => 'Observe'" in func
for url in [
    'https://map.google.com/',
    'https://mail.google.com/',
    'https://www.google.com/',
    'https://www.google.com/imghp',
]:
    assert url in func, f'missing PHP default URL: {url}'
    assert f"DEFAULT ''{url}''" in schema, f'missing schema default URL: {url}'
assert "DEFAULT ''Maint''" in schema
assert "DEFAULT ''Observe''" in schema

legacy_hashes = (ROOT / 'docs' / 'legacy' / 'source-sha256.txt').read_text(encoding='utf-8')
assert '/mnt/data/' not in legacy_hashes
assert 'rss.zip' in legacy_hashes and 'api_v1.php' in legacy_hashes and 'rss.sql' in legacy_hashes

security = (ROOT / 'docs' / 'security.md').read_text(encoding='utf-8')
assert 'APP_HASH_KEY' in readme and '運用開始後' in readme and 'バックアップ' in readme
assert 'APP_HASH_KEY' in security and '運用開始後' in security and 'バックアップ' in security
assert 'SEC-001' in change_map and 'RSS-004' in change_map and 'DATA-005' in change_map

gitignore = (ROOT / '.gitignore').read_text(encoding='utf-8')
assert '/CHECKLIST_FOR_USER.md' in gitignore
assert '/UPDATED_FILES_SB*.md' in gitignore
for repository_doc in ['README.md', 'CHANGELOG.md', 'docs/legacy-analysis.md', 'docs/modernization.md', 'docs/security.md']:
    assert repository_doc not in gitignore, f'repository documentation accidentally ignored: {repository_doc}'

# README must not claim the deferred Engine/Frontend work is already complete.
assert 'Feed itemのサーバーキャッシュなし' not in readme
assert 'APP_FEED_CACHE_TTL_SECONDS' in readme and 'ETag / Last-Modified' in readme and 'APP_FEED_RETRY_ENABLED' in readme and 'APP_FEED_RETRY_ENABLED' in readme
assert 'jQuery' in readme and 'Frontend' in readme

# Check local Markdown links in the SB-15 primary docs.
md_link = re.compile(r'\[[^\]]+\]\(([^)]+)\)')
for doc in required:
    text = doc.read_text(encoding='utf-8')
    for target in md_link.findall(text):
        if target.startswith(('http://', 'https://', '#', 'mailto:')):
            continue
        clean = target.split('#', 1)[0]
        if not clean:
            continue
        resolved = (doc.parent / clean).resolve()
        assert resolved.exists(), f'broken local link in {doc.relative_to(ROOT)}: {target}'

# Documentation must not accidentally embed high-signal secret formats.
scan_docs = required + [ROOT / 'CHECKLIST_FOR_USER.md', ROOT / 'UPDATED_FILES_SB15.md']
secret_patterns = [
    re.compile(r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----'),
    re.compile(r'\bAKIA[0-9A-Z]{16}\b'),
    re.compile(r'\bsk-[A-Za-z0-9_-]{20,}\b'),
]
for doc in scan_docs:
    text = doc.read_text(encoding='utf-8')
    for pattern in secret_patterns:
        assert not pattern.search(text), f'potential secret in {doc.relative_to(ROOT)}'

print('PASS: SB-15 required documentation and Initial Commit gate')
print('PASS: SB-15 local Markdown links')
print('PASS: SB-15 documentation secret-pattern scan')
