#!/usr/bin/env python3
from pathlib import Path
import hashlib, json
ROOT=Path(__file__).resolve().parents[1]
checks=0

def check(ok,msg):
 global checks; checks+=1; print(('PASS' if ok else 'FAIL')+': '+msg)
 if not ok: raise AssertionError(msg)

data=json.loads((ROOT/'docs/m4-a-baseline.json').read_text(encoding='utf-8'))
check(data['checkpoint']=='Frontend M2-G / R1','M2-G baseline checkpoint is recorded')
check(data['github_main_commit']=='78211b7f57dbf0e50778da45e0d9b3167d0e592a','GitHub main baseline commit is exact')
check(data['source_zip_sha256']=='bdcd0c8eadbc00b014144aaa6ca4f9fbdb95c93409f32f36e8f49c1ff2b27a3d','source ZIP SHA-256 is exact')
for rel,expected in data['critical_file_sha256'].items():
 actual=hashlib.sha256((ROOT/rel).read_bytes()).hexdigest()
 check(actual==expected,f'M2-G critical file unchanged: {rel}')
version=(ROOT/'app/version.php').read_text(encoding='utf-8')
check("APP_VERSION = 'M4-C R1'" in version,'current application version has progressed to M4-C')
check("APP_VERSION_LABEL = 'Release M4-C / R1'" in version,'current visible label has progressed to M4-C')
print(f'All {checks} M4-A baseline checks passed.')
