#!/usr/bin/env python3
from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]
checks=0

def check(ok,msg):
 global checks; checks+=1; print(('PASS' if ok else 'FAIL')+': '+msg)
 if not ok: raise AssertionError(msg)

gate=(ROOT/'docs/release-gate-v1.0.0.md').read_text(encoding='utf-8')
plan=(ROOT/'docs/m4-plan.md').read_text(encoding='utf-8')
impl=(ROOT/'docs/m4-a-implementation.md').read_text(encoding='utf-8')
readme=(ROOT/'README.md').read_text(encoding='utf-8')
roadmap=(ROOT/'docs/roadmap.md').read_text(encoding='utf-8')
check('**Current checkpoint:** `Release M4-D / R1`' in readme,'README has progressed beyond M4-A')
check('| M4-A | Release基準・公開物・残課題の棚卸し | 完了 |' in readme,'README marks M4-A complete')
check('- [x] M4-A Release基準・公開物・残課題の棚卸し' in roadmap,'Roadmap marks M4-A complete')
for stage in 'ABCDEFG': check(f'M4-{stage}' in plan,f'M4-{stage} is present in formal plan')
for term in ['PASS','HOLD','FAIL','Third-party notice accuracy','Real environment / RC','Version / Tag / GitHub Release']:
 check(term in gate,f'Release gate defines {term}')
check('M3成果物が確認できない' in impl,'M3 is not falsely marked complete')
check('M4-D〜F' in impl,'M3-equivalent checks are assigned to M4-D through M4-F')
check('DB schema / Migration' in impl and '公開API contract' in impl,'unchanged DB/API scope is documented')
check('Bootstrap 5' in plan and 'npm' in plan and 'Composer' in plan,'out-of-scope modernization is documented')
# local markdown links in new M4 docs
for rel in ['README.md','docs/m4-a-implementation.md','docs/release-gate-v1.0.0.md','docs/release-artifact-inventory-v1.0.0.md','docs/m4-plan.md']:
 text=(ROOT/rel).read_text(encoding='utf-8')
 for target in re.findall(r'\[[^\]]+\]\(([^)]+)\)',text):
  if '://' in target or target.startswith('#'): continue
  base=(ROOT/rel).parent
  check((base/target.split('#')[0]).exists(),f'local Markdown link resolves: {rel} -> {target}')
print(f'All {checks} M4-A release gate checks passed.')
