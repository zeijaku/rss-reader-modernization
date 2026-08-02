#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


required = {
    'docs/installation.md': ['public/', 'APP_HASH_KEY', 'DB_TABLE_PREFIX', 'database/schema.sql', 'db_sb13.php verify'],
    'docs/update.md': ['git pull --ff-only', 'config/local.php', 'Backup', 'M4-B / R1', 'M4-C / R1'],
    'docs/configuration.md': ['Environment variable > config/local.php > safe default', '.env', 'APP_HTTP_MAX_BYTES'],
    'docs/backup-and-restore.md': ['mysqldump', '--single-transaction', 'Get-FileHash', 'Restore test', 'APP_HASH_KEY'],
    'docs/rollback.md': ['git revert', 'force push', 'M4-B / R1', 'Database Restore'],
    'docs/deployment-checklist.md': ['healthcheck.php', 'db_sb13.php verify', 'Manifest', 'M4-F'],
    'docs/m4-c-implementation.md': ['必須設定の追加はない', 'DB migration不要', 'M4-F'],
}
texts: dict[str, str] = {}
for rel, terms in required.items():
    path = ROOT / rel
    check(path.is_file() and path.stat().st_size > 700, f'operations document exists and is substantive: {rel}')
    text = path.read_text(encoding='utf-8')
    texts[rel] = text
    for term in terms:
        check(term in text, f'operations document contains required term: {rel} -> {term}')

installation = texts['docs/installation.md']
update = texts['docs/update.md']
backup = texts['docs/backup-and-restore.md']
rollback = texts['docs/rollback.md']
configuration = texts['docs/configuration.md']
check('既存Databaseへ `schema.sql` を再実行しない' in installation, 'installation warns against schema import over existing DB')
check('preflight' in installation and '--backup-confirmed' in installation and 'postflight' in installation, 'Legacy migration order includes backup gate')
check('healthcheck.php` はPHP拡張' in installation and 'DatabaseへLoginしません' in installation, 'healthcheck scope is accurate')
check('DB schema / Migration       変更なし' in update and 'Cache clearも不要' in update and '削除file                    なし' in update, 'M4-B to M4-C update impact is explicit')
check('Runtimeが対応していた設定' in update, 'example expansion is not described as a new runtime requirement')
check('0 byteでない' in backup and '別DatabaseへRestore test' in backup, 'backup verification includes size and restore drill')
check('Session、Login throttle、Feed cache' in backup and '永続Backup対象にしません' in backup, 'ephemeral runtime data is separated from durable backup')
check('git reset --hard' in rollback and '通常のRollback手順にしません' in rollback, 'unsafe history rewrite is explicitly rejected')
check('DB migrationを含まないReleaseでは、安易にDatabase dumpを戻しません' in rollback, 'code rollback avoids unnecessary DB restore')
check('dotenv libraryを使用せず' in configuration, 'dotenv behavior is accurately documented')

# No destructive command is presented inside an executable code fence.
all_docs = '\n'.join(texts.values())
fenced = re.findall(r'```[^\n]*\n(.*?)```', all_docs, flags=re.S | re.I)
unsafe = ['git reset --hard', 'git push --force', 'rm -rf', 'chmod 777', 'Remove-Item -Recurse']
for command in unsafe:
    check(not any(command.lower() in block.lower() for block in fenced), f'unsafe command is not offered as executable procedure: {command}')

# Local links across current release-facing documents.
link_re = re.compile(r'\[[^\]]+\]\(([^)]+)\)')
link_docs = [
    'README.md', 'docs/README.md', 'docs/installation.md', 'docs/update.md', 'docs/configuration.md',
    'docs/backup-and-restore.md', 'docs/rollback.md', 'docs/deployment-checklist.md',
    'docs/m4-c-implementation.md', 'docs/release-gate-v1.0.0.md', 'docs/roadmap.md',
]
for rel in link_docs:
    path = ROOT / rel
    text = path.read_text(encoding='utf-8')
    for target in link_re.findall(text):
        if target.startswith(('http://', 'https://', '#', 'mailto:')):
            continue
        clean = target.split('#', 1)[0]
        if not clean:
            continue
        check((path.parent / clean).resolve().exists(), f'local Markdown link resolves: {rel} -> {target}')

readme = (ROOT / 'README.md').read_text(encoding='utf-8')
change = (ROOT / 'CHANGELOG.md').read_text(encoding='utf-8')
roadmap = (ROOT / 'docs/roadmap.md').read_text(encoding='utf-8')
gate = (ROOT / 'docs/release-gate-v1.0.0.md').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
check('**Current checkpoint:** `Release M4-C / R1`' in readme, 'README checkpoint is M4-C')
check('| M4-C | 設置・更新・Backup・復旧手順 | 完了 |' in readme, 'README marks M4-C complete')
check('- [x] M4-C 新規設置・更新・設定・Backup・復旧手順' in roadmap, 'Roadmap marks M4-C complete')
check('## Release M4-C / R1 — 2026-08-02' in change, 'CHANGELOG contains M4-C entry')
check("APP_VERSION = 'M4-C R1'" in version and "APP_VERSION_LABEL = 'Release M4-C / R1'" in version, 'visible version is M4-C R1')
check('| Installation / Update / Recovery | PASS |' in gate, 'M4-C release gate is PASS')
check('| Real environment / RC | HOLD |' in gate, 'real environment confirmation remains HOLD')
check('M4-C完了はVersion 1.0.0 Release可を意味しない' in gate, 'M4-C does not claim final release readiness')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M4-C operations documentation checks passed.')
