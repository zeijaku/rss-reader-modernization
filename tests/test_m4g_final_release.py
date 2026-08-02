#!/usr/bin/env python3
from __future__ import annotations
import hashlib, re, subprocess, sys, tempfile, zipfile
from pathlib import Path, PurePosixPath
ROOT=Path(__file__).resolve().parents[1]; checks=[]
def check(ok,msg): checks.append(bool(ok)); print(('PASS' if ok else 'FAIL')+': '+msg)
def run(*args,expect=0):
 r=subprocess.run(args,cwd=ROOT,text=True,capture_output=True); check(r.returncode==expect,f'command exit is {expect}: {" ".join(args)}')
 if r.returncode!=expect: print(r.stdout); print(r.stderr,file=sys.stderr)
 return r
builder=ROOT/'tools/build_release_package.py'; verifier=ROOT/'tools/verify_release_package.py'
version=(ROOT/'app/version.php').read_text(encoding='utf-8')
check("APP_VERSION = '1.0.0'" in version,'APP_VERSION is exact 1.0.0')
check("APP_VERSION_LABEL = 'RSS Reader Modernization 1.0.0'" in version,'APP_VERSION_LABEL is exact 1.0.0')
with tempfile.TemporaryDirectory(prefix='rss-m4g-final-a-') as a, tempfile.TemporaryDirectory(prefix='rss-m4g-final-b-') as b:
 run(sys.executable,str(builder),'--mode','final','--output-dir',a)
 run(sys.executable,str(builder),'--mode','final','--output-dir',b)
 za=Path(a)/'rss-reader-modernization-1.0.0.zip'; zb=Path(b)/'rss-reader-modernization-1.0.0.zip'
 sa=Path(a)/'rss-reader-modernization-1.0.0.zip.sha256'; sb=Path(b)/'rss-reader-modernization-1.0.0.zip.sha256'
 for p in [za,zb,sa,sb]: check(p.is_file() and p.stat().st_size>50,f'final artifact exists: {p.name}')
 da=hashlib.sha256(za.read_bytes()).hexdigest(); db=hashlib.sha256(zb.read_bytes()).hexdigest()
 check(da==db,'two final builds are byte-for-byte deterministic')
 check(sa.read_text(encoding='ascii')==sb.read_text(encoding='ascii'),'two final sidecars are identical')
 v=run(sys.executable,str(verifier),str(za),str(sa)); check('release package checks passed' in v.stdout,'verifier accepts final package')
 with zipfile.ZipFile(za) as z:
  check(z.testzip() is None,'final ZIP CRC passes')
  names=[i.filename for i in z.infolist() if not i.is_dir()]
  check(len(names)==len(set(names)),'final ZIP has no duplicate entries')
  check(all('\\' not in n for n in names),'final ZIP paths use forward slashes')
  check(all('..' not in PurePosixPath(n).parts for n in names),'final ZIP has no traversal')
  tops={PurePosixPath(n).parts[0] for n in names}; check(tops=={'rss-reader-modernization-1.0.0'},'final top-level directory is exact')
  rels={'/'.join(PurePosixPath(n).parts[1:]):n for n in names}
  for rel in ['RELEASE_BUILD.txt','RELEASE_MANIFEST.sha256','RELEASE_NOTES.md','app/version.php','public/index.php','docs/m4-g-implementation.md','LICENSE','THIRD_PARTY_NOTICES.md']:
   check(rel in rels,f'final ZIP includes: {rel}')
  check(not any(x.startswith('tests/') for x in rels),'final ZIP excludes tests')
  check('CHECKLIST_FOR_USER.md' not in rels,'final ZIP excludes checkpoint checklist')
  check('.github/workflows/ci.yml' not in rels,'final ZIP excludes repository CI metadata')
  build=z.read(rels['RELEASE_BUILD.txt']).decode()
  for term in ['package_status=FINAL','application_version=1.0.0','publishable=yes','validation_scope=automated-regression-and-package','manual_evidence=not-recorded-in-distribution']:
   check(term in build,f'final metadata contains: {term}')
  notes=z.read(rels['RELEASE_NOTES.md']).decode()
  check('Verification limits' in notes,'final notes disclose verification limits')
  check('正式Releaseではありません' not in notes,'final notes contain no RC warning')
with tempfile.TemporaryDirectory(prefix='rss-m4g-wrong-mode-') as tmp:
 rc=run(sys.executable,str(builder),'--mode','rc','--output-dir',tmp,expect=1); check('rc mode requires APP_VERSION' in rc.stderr,'RC mode rejects final marker')
 preview=run(sys.executable,str(builder),'--mode','preview','--output-dir',tmp,expect=1); check('preview mode requires M4-E R1' in preview.stderr,'preview mode rejects final marker')
# private evidence blocks final build
e=ROOT/'var/m4f-evidence/m4-f-result.json'; e.write_text('{"status":"private"}\n',encoding='utf-8')
try:
 with tempfile.TemporaryDirectory(prefix='rss-m4g-private-') as tmp:
  blocked=run(sys.executable,str(builder),'--mode','final','--output-dir',tmp,expect=1)
  check('runtime directory contains generated files: var/m4f-evidence' in blocked.stderr,'final builder rejects private evidence')
finally: e.unlink(missing_ok=True)
if not all(checks): sys.exit(1)
print(f'All {len(checks)} M4-G final release checks passed.')
