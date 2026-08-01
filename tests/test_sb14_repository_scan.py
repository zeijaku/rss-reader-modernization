from __future__ import annotations
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]

def check(cond: bool, msg: str) -> None:
    print(('PASS' if cond else 'FAIL') + ': ' + msg)
    if not cond:
        raise AssertionError(msg)

# Real runtime/private files must never be in the distributable/repository tree.
for rel in ['config/local.php', '.env', 'rss.sql', 'rss.zip']:
    check(not (ROOT / rel).exists(), f'private/Legacy artifact is absent: {rel}')

for runtime_dir in ['var/session', 'var/log', 'var/security/login-throttle', 'var/db-migration', 'var/cache/feed']:
    path = ROOT / runtime_dir
    if not path.exists():
        continue
    files = [p for p in path.rglob('*') if p.is_file() and p.name != '.gitkeep']
    check(files == [], f'{runtime_dir} contains no runtime data')

forbidden_names = []
for path in ROOT.rglob('*'):
    if not path.is_file():
        continue
    rel = path.relative_to(ROOT).as_posix()
    low = path.name.lower()
    if rel.startswith('database/'):
        # Curated schema/audit/migration/fixture SQL is intentional.
        continue
    if low.endswith(('.sql', '.sqlite', '.sqlite3', '.db', '.bak', '.dump')):
        forbidden_names.append(rel)
    if low in {'access.log', 'error.log'} or low.startswith('sess_'):
        forbidden_names.append(rel)
check(forbidden_names == [], 'no database dump/log/session artifact exists outside curated database directory')

# High-signal credential patterns. Examples/placeholders are allowed; actual local.php is not.
patterns = [
    re.compile(r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----'),
    re.compile(r'\bAKIA[0-9A-Z]{16}\b'),
    re.compile(r'\bsk-[A-Za-z0-9_-]{20,}\b'),
]
leaks = []
for path in ROOT.rglob('*'):
    if not path.is_file() or path.suffix.lower() in {'.png', '.jpg', '.jpeg', '.gif', '.woff', '.woff2', '.ttf', '.ico', '.zip'}:
        continue
    try:
        text = path.read_text(encoding='utf-8')
    except UnicodeDecodeError:
        continue
    for pattern in patterns:
        if pattern.search(text):
            leaks.append(path.relative_to(ROOT).as_posix())
            break
check(leaks == [], 'no high-signal private key/cloud API credential pattern is present')

# Examples must remain visibly non-production.
for rel in ['config/local.php.example', 'config/.env.example']:
    text = (ROOT / rel).read_text(encoding='utf-8')
    check('change-this' in text or 'example' in text.lower() or 'replace' in text.lower(), f'{rel} uses explicit example/replace-me values')

fixture = (ROOT / 'database/fixtures/sample.sql').read_text(encoding='utf-8')
check('example' in fixture.lower(), 'database fixture is visibly synthetic/example data')
check('production' not in fixture.lower() or 'not production' in fixture.lower() or 'fake' in fixture.lower(), 'database fixture does not masquerade as production data')

print('All SB-14 repository leak checks passed.')
