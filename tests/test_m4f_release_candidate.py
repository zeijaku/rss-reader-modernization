#!/usr/bin/env python3
from pathlib import Path
import re, subprocess, sys, tempfile
ROOT=Path(__file__).resolve().parents[1]
checks=[]
def check(ok,msg): checks.append(bool(ok)); print(('PASS' if ok else 'FAIL')+': '+msg)
def run(*args,expect=0):
 r=subprocess.run(args,cwd=ROOT,text=True,capture_output=True); check(r.returncode==expect,f'command exit is {expect}: {" ".join(args)}'); return r
version=(ROOT/'app/version.php').read_text(encoding='utf-8')
builder=ROOT/'tools/build_release_package.py'
check("APP_VERSION = '1.0.0'" in version,'project progressed from RC1 to final 1.0.0')
for rel in ['docs/m4-f-validation.md','docs/m4-f-validation-template.json','docs/test-report-m4-f.md','tools/m4f_environment_probe.php','tools/m4f_evidence_gate.py']:
 check((ROOT/rel).is_file(),f'M4-F historical artifact remains: {rel}')
source=builder.read_text(encoding='utf-8')
for term in ["mode == 'rc'",'RELEASE_CANDIDATE',"\'RELEASE_CANDIDATE\', \'no\'",r'1\.0\.0-rc']:
 check(term in source,f'builder retains RC boundary: {term}')
with tempfile.TemporaryDirectory(prefix='rss-m4f-final-progression-') as tmp:
 rc=run(sys.executable,str(builder),'--mode','rc','--output-dir',tmp,expect=1)
 check('rc mode requires APP_VERSION' in rc.stderr,'RC mode rejects final version marker')
 preview=run(sys.executable,str(builder),'--mode','preview','--output-dir',tmp,expect=1)
 check('preview mode requires M4-E R1' in preview.stderr,'preview mode rejects final version marker')
if not all(checks): sys.exit(1)
print(f'All {len(checks)} M4-F historical RC checks passed.')
