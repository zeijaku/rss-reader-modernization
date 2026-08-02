#!/usr/bin/env python3
from __future__ import annotations
from pathlib import Path
import re, sys
ROOT=Path(__file__).resolve().parents[1]; checks=[]
def check(ok,msg): checks.append(bool(ok)); print(('PASS' if ok else 'FAIL')+': '+msg)
required={
 'RELEASE_NOTES.md':['Main changes','package_status=FINAL','publishable=yes','Verification limits'],
 'docs/release-package.md':['deterministic','RELEASE_MANIFEST.sha256','preview','rc','final','allowlist','M4-G'],
 'docs/tag-and-github-release.md':['Annotated Tag','git push origin v1.0.0','git push --tags','v1.0.1','Force push'],
 'docs/m4-e-implementation.md':['Application機能','publishable=no','M4-F','M4-G','GitHub hosted CI'],
 'docs/release-gate-v1.0.0.md':['Release ZIP / Notes / SHA-256','Final Version boundary','PASS','Real environment evidence','DISCLOSED'],
 'docs/release-artifact-inventory-v1.0.0.md':['Runtime Release ZIP','Checkpoint ZIP','tests/','RELEASE_BUILD.txt','RELEASE_MANIFEST.sha256','M4-G Final Release'],
}
texts={}
for rel,terms in required.items():
 p=ROOT/rel; check(p.is_file() and p.stat().st_size>200,f'M4-E/final release document exists: {rel}')
 text=p.read_text(encoding='utf-8'); texts[rel]=text
 for term in terms: check(term.lower() in text.lower(),f'release document contains: {rel} -> {term}')
readme=(ROOT/'README.md').read_text(encoding='utf-8'); change=(ROOT/'CHANGELOG.md').read_text(encoding='utf-8'); road=(ROOT/'docs/roadmap.md').read_text(encoding='utf-8'); versioning=(ROOT/'docs/versioning.md').read_text(encoding='utf-8'); version=(ROOT/'app/version.php').read_text(encoding='utf-8')
check('**Current release:** `RSS Reader Modernization 1.0.0`' in readme,'README progressed beyond M4-E to final')
check('| M4-E | 配布ZIP・Release Notes・SHA-256・Tag手順 | 完了 |' in readme,'README marks M4-E complete')
check('Version 1.0.0 release package' in readme,'README presents final package flow')
check('- [x] M4-E 配布ZIP・Release Notes・SHA-256・Tag / Release手順' in road,'Roadmap marks M4-E complete')
check('## Release M4-E / R1 — 2026-08-02' in change,'CHANGELOG retains M4-E entry')
check(change.find('## RSS Reader Modernization 1.0.0 —')<change.find('## RSS Reader Modernization 1.0.0-RC1')<change.find('## Release M4-E / R1'),'final, RC, M4-E history order is valid')
check("APP_VERSION = '1.0.0'" in version,'current APP_VERSION is final')
check("APP_VERSION_LABEL = 'RSS Reader Modernization 1.0.0'" in version,'current APP_VERSION_LABEL is final')
check('Current: `RSS Reader Modernization 1.0.0`' in versioning,'Version policy current marker is final')
check('未実施項目を架空のPASSへ変更していません' in texts['docs/release-gate-v1.0.0.md'],'final gate preserves evidence honesty')
check('M4-E Preview' in texts['docs/release-artifact-inventory-v1.0.0.md'],'M4-E preview history remains documented')
link_re=re.compile(r'\[[^\]]+\]\(([^)]+)\)')
for rel in ['README.md','RELEASE_NOTES.md','docs/README.md','docs/release-package.md','docs/tag-and-github-release.md','docs/m4-e-implementation.md','docs/release-gate-v1.0.0.md','docs/release-artifact-inventory-v1.0.0.md']:
 p=ROOT/rel
 for target in link_re.findall(p.read_text(encoding='utf-8')):
  if target.startswith(('http://','https://','#','mailto:')): continue
  clean=target.split('#',1)[0]
  if clean: check((p.parent/clean).resolve().exists(),f'local Markdown link resolves: {rel} -> {target}')
joined='\n'.join(texts.values())
check(not re.search(r'\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b',joined,re.I),'release docs invent no contact email')
for pattern in [r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----',r'\bAKIA[0-9A-Z]{16}\b',r'\bsk-[A-Za-z0-9_-]{20,}\b']:
 check(not re.search(pattern,joined),f'release docs contain no secret pattern: {pattern}')
if not all(checks): sys.exit(1)
print(f'All {len(checks)} M4-E historical documentation checks passed.')
