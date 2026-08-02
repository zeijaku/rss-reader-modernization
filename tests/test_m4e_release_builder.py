#!/usr/bin/env python3
from __future__ import annotations
import ast, hashlib, subprocess, sys, tempfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]; checks=[]
def check(ok,msg): checks.append(bool(ok)); print(('PASS' if ok else 'FAIL')+': '+msg)
def run(*args,expect=0):
 r=subprocess.run(args,cwd=ROOT,text=True,capture_output=True); check(r.returncode==expect,f'command exit is {expect}: {" ".join(args)}'); return r
builder=ROOT/'tools/build_release_package.py'; verifier=ROOT/'tools/verify_release_package.py'
check(builder.is_file(),'M4-E release builder remains available'); check(verifier.is_file(),'M4-E verifier remains available')
source=builder.read_text(encoding='utf-8'); check(bool(ast.parse(source)),'builder syntax parses'); check(bool(ast.parse(verifier.read_text(encoding='utf-8'))),'verifier syntax parses')
for term in ["choices=('preview', 'rc', 'final')",'FIXED_TIME','RELEASE_BUILD.txt','RELEASE_MANIFEST.sha256','publishable',"'config/local.php'","'.env'"]:
 check(term in source,f'M4-E builder contract remains: {term}')
with tempfile.TemporaryDirectory(prefix='rss-m4e-final-a-') as a, tempfile.TemporaryDirectory(prefix='rss-m4e-final-b-') as b:
 run(sys.executable,str(builder),'--mode','final','--output-dir',a); run(sys.executable,str(builder),'--mode','final','--output-dir',b)
 za=Path(a)/'rss-reader-modernization-1.0.0.zip'; zb=Path(b)/'rss-reader-modernization-1.0.0.zip'; side=Path(a)/'rss-reader-modernization-1.0.0.zip.sha256'
 check(za.is_file() and zb.is_file() and side.is_file(),'builder produces final artifacts after M4-E')
 check(hashlib.sha256(za.read_bytes()).hexdigest()==hashlib.sha256(zb.read_bytes()).hexdigest(),'M4-E deterministic build contract remains valid')
 verified=run(sys.executable,str(verifier),str(za),str(side)); check('release package checks passed' in verified.stdout,'standalone verifier accepts final package')
preview=run(sys.executable,str(builder),'--mode','preview','--output-dir',tempfile.gettempdir(),expect=1); check('preview mode requires M4-E R1' in preview.stderr,'preview mode rejects final marker')
rc=run(sys.executable,str(builder),'--mode','rc','--output-dir',tempfile.gettempdir(),expect=1); check('rc mode requires APP_VERSION' in rc.stderr,'RC mode rejects final marker')
if not all(checks): sys.exit(1)
print(f'All {len(checks)} M4-E historical builder checks passed.')
