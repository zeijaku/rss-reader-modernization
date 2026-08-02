#!/usr/bin/env python3
from pathlib import Path
import re
import hashlib
ROOT=Path(__file__).resolve().parents[1]
checks=0

def check(ok,msg):
 global checks; checks+=1; print(('PASS' if ok else 'FAIL')+': '+msg)
 if not ok: raise AssertionError(msg)

required=['LICENSE','THIRD_PARTY_NOTICES.md','licenses/bootstrap-MIT.txt','licenses/bootswatch-MIT.txt','licenses/jquery-MIT.txt','licenses/popper-MIT.txt','licenses/jquery-drawer-MIT.txt','licenses/iscroll-MIT.txt','licenses/fontawesome-6.7.2-LICENSE.txt','docs/release-artifact-inventory-v1.0.0.md']
for rel in required: check((ROOT/rel).is_file() and (ROOT/rel).stat().st_size>100,f'public/release file exists: {rel}')
check(not (ROOT/'config/local.php').exists(),'private local config is excluded')
check(not (ROOT/'.env').exists(),'.env is excluded')
for runtime in ['var/session','var/log','var/cache/feed','var/db-migration']:
 files=[p.name for p in (ROOT/runtime).iterdir() if p.is_file()]
 check(files in ([],['.gitkeep']),f'Runtime directory is clean: {runtime}')
for p in ROOT.rglob('*'):
 if p.is_file():
  rel=p.relative_to(ROOT).as_posix().lower()
  check('..' not in p.relative_to(ROOT).parts,f'safe relative path: {rel}')
  check(not rel.endswith(('.sqlite','.sqlite3','.db','.dump','.bak','.backup','.log','.pid')),f'forbidden runtime/data extension absent: {rel}')
  check(not rel.endswith('.zip'),f'nested ZIP absent: {rel}')

# M4-A restored the public files; M4-B may legitimately update their content.
check('M4-A' in (ROOT/'docs/m4-a-implementation.md').read_text(encoding='utf-8'), 'M4-A restoration record remains')

scan_roots = [ROOT / name for name in ['app', 'public', 'config', 'database', 'tools']]
secret_patterns = [
    re.compile(r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----'),
    re.compile(r'\bAKIA[0-9A-Z]{16}\b'),
    re.compile(r'\bsk-[A-Za-z0-9_-]{20,}\b'),
]
secret_hits = []
for base in scan_roots:
    for path in base.rglob('*'):
        if not path.is_file():
            continue
        try:
            text = path.read_text(encoding='utf-8')
        except (UnicodeDecodeError, OSError):
            continue
        if any(pattern.search(text) for pattern in secret_patterns):
            secret_hits.append(path.relative_to(ROOT).as_posix())
check(not secret_hits, 'application/package source contains no high-signal secret pattern')

notice=(ROOT/'THIRD_PARTY_NOTICES.md').read_text(encoding='utf-8')
impl=(ROOT/'docs/m4-a-implementation.md').read_text(encoding='utf-8')
check('jQuery | 3.7.1' in notice and 'Font Awesome Free | 6.7.2' in notice,'M4-A blocker has been resolved by M4-B')
check('Release Blocker' in impl and 'jQuery 3.3.1' in impl and 'Font Awesome 5.3.1' in impl,'M4-A historical blocker remains documented')
print(f'All {checks} M4-A inventory checks passed.')
