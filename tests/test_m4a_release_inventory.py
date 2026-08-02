#!/usr/bin/env python3
from pathlib import Path
import re
import hashlib
ROOT=Path(__file__).resolve().parents[1]
checks=0

def check(ok,msg):
 global checks; checks+=1; print(('PASS' if ok else 'FAIL')+': '+msg)
 if not ok: raise AssertionError(msg)

required=['LICENSE','THIRD_PARTY_NOTICES.md','licenses/bootstrap-MIT.txt','licenses/bootswatch-MIT.txt','licenses/jquery-MIT.txt','licenses/popper-MIT.txt','licenses/jquery-drawer-MIT.txt','licenses/iscroll-MIT.txt','licenses/fontawesome-5.3.1-LICENSE.txt','docs/release-artifact-inventory-v1.0.0.md']
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

expected_blobs = {
    'LICENSE': '165a78d1e16d3af7db9abf78ce9fc491e98956ba',
    'THIRD_PARTY_NOTICES.md': 'd388cf7885f39376d86e918c30bb5deddce713dd',
    'licenses/bootstrap-MIT.txt': '86f4b8ca04f8d10574f189b8f73bdf0d575675e3',
    'licenses/bootswatch-MIT.txt': '088583efb93850862e323a3c04e2a71e20a541a4',
    'licenses/jquery-MIT.txt': 'e4e5e00ef0a465471f0c453f0d39055e4f222134',
    'licenses/popper-MIT.txt': 'bd16042ca5e03a3d064a52bf2da4b280c8b362d9',
    'licenses/jquery-drawer-MIT.txt': '10730d79c627b62e56f0b3a72dc60fa47203079d',
    'licenses/iscroll-MIT.txt': 'b91cec0999a235b39d4ba8c5dd816a24c4f5bf7c',
    'licenses/fontawesome-5.3.1-LICENSE.txt': '386e4005064d6410acc64b6f2f9a7f723e0beed7',
}
for rel, expected in expected_blobs.items():
    data = (ROOT / rel).read_bytes()
    actual = hashlib.sha1(f'blob {len(data)}\0'.encode('ascii') + data).hexdigest()
    check(actual == expected, f'GitHub main blob matches: {rel}')

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
check('jQuery | 3.3.1' in notice and 'Font Awesome Free | 5.3.1' in notice,'known Third-party notice mismatch remains observable')
check('Release Blocker' in impl and 'jQuery 3.3.1' in impl and 'Font Awesome 5.3.1' in impl,'known mismatch is not hidden and is assigned to M4-B')
print(f'All {checks} M4-A inventory checks passed.')
