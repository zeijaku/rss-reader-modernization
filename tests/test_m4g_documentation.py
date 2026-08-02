#!/usr/bin/env python3
from pathlib import Path
import json,re,sys
ROOT=Path(__file__).resolve().parents[1]; checks=[]
def check(ok,msg): checks.append(bool(ok)); print(('PASS' if ok else 'FAIL')+': '+msg)
required={
 'README.md':['**Current release:** `RSS Reader Modernization 1.0.0`','| M4-G | 最終Quality Gate・Version 1.0.0確定 | 完了 |','Verification limits'],
 'CHANGELOG.md':['## RSS Reader Modernization 1.0.0 — 2026-08-02','First stable release'],
 'RELEASE_NOTES.md':['Release date: 2026-08-02','package_status=FINAL','publishable=yes','Verification limits'],
 'docs/m4-g-implementation.md':['APP_VERSION=1.0.0','Application Runtime','実環境Evidence','v1.0.0'],
 'docs/release-gate-v1.0.0.md':['M4-G / Version 1.0.0','DISCLOSED','Real environment / RC','manual evidence'],
 'docs/versioning.md':['Current: `RSS Reader Modernization 1.0.0`','Git Tag: `v1.0.0`'],
}
for rel,terms in required.items():
 p=ROOT/rel; check(p.is_file() and p.stat().st_size>100,f'final document exists: {rel}'); text=p.read_text(encoding='utf-8')
 for term in terms: check(term.lower() in text.lower(),f'final document contains: {rel} -> {term}')
change=(ROOT/'CHANGELOG.md').read_text(encoding='utf-8')
check(change.find('## RSS Reader Modernization 1.0.0 —')<change.find('## RSS Reader Modernization 1.0.0-RC1'),'final changelog precedes RC1 history')
road=(ROOT/'docs/roadmap.md').read_text(encoding='utf-8')
check('- [x] M4-F Version 1.0.0候補版' in road,'roadmap closes M4-F with disclosed evidence state')
check('- [x] M4-G 最終Quality Gate・正式Release' in road,'roadmap closes M4-G')
validation=json.loads((ROOT/'docs/m4-f-validation-template.json').read_text(encoding='utf-8'))
check(validation.get('overall_status')=='HOLD','M4-F template remains HOLD')
check(all(x.get('status')=='PENDING' for x in validation.get('checks',[])),'M4-F template contains no fabricated PASS')
notes=(ROOT/'RELEASE_NOTES.md').read_text(encoding='utf-8')
for term in ['実MySQL','実Feed','実Browser','Restore','GitHub hosted CI']:
 check(term in notes,f'final notes disclose missing evidence: {term}')
link_re=re.compile(r'\[[^\]]+\]\(([^)]+)\)')
for rel in required:
 p=ROOT/rel
 for target in link_re.findall(p.read_text(encoding='utf-8')):
  if target.startswith(('http://','https://','#','mailto:')): continue
  clean=target.split('#',1)[0]
  if clean: check((p.parent/clean).resolve().exists(),f'local Markdown link resolves: {rel} -> {target}')
if not all(checks): sys.exit(1)
print(f'All {len(checks)} M4-G documentation checks passed.')
