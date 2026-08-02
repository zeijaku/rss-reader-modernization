#!/usr/bin/env python3
from pathlib import Path
import json,re,sys
ROOT=Path(__file__).resolve().parents[1]; checks=[]
def check(ok,msg): checks.append(bool(ok)); print(('PASS' if ok else 'FAIL')+': '+msg)
required={
 'docs/m4-f-validation.md':['1.0.0-rc1','RELEASE_CANDIDATE','publishable=no','MySQL','RSS 2.0','RSS 1.0','Atom','Backup','Restore','Rollback','HOLD'],
 'docs/m4-f-implementation.md':['Release Candidate','Application機能','DB schema','publishable=no','M4-G'],
 'docs/test-report-m4-f.md':['PASS 4927','Real environment evidence HOLD','Version 1.0.0 / v1.0.0'],
}
for rel,terms in required.items():
 p=ROOT/rel; check(p.is_file(),f'M4-F document remains: {rel}'); text=p.read_text(encoding='utf-8')
 for term in terms: check(term.lower() in text.lower(),f'M4-F history contains: {rel} -> {term}')
validation=json.loads((ROOT/'docs/m4-f-validation-template.json').read_text(encoding='utf-8'))
check(validation.get('overall_status')=='HOLD','committed validation template remains HOLD')
check(all(x.get('status')=='PENDING' for x in validation.get('checks',[])),'validation template contains no fabricated PASS')
notes=(ROOT/'RELEASE_NOTES.md').read_text(encoding='utf-8')
check('Verification limits' in notes,'final notes preserve M4-F limitations')
check('正式Releaseではありません' not in notes,'final notes removed RC warning')
version=(ROOT/'app/version.php').read_text(encoding='utf-8')
check("APP_VERSION = '1.0.0'" in version,'current version is final')
if not all(checks): sys.exit(1)
print(f'All {len(checks)} M4-F historical documentation checks passed.')
