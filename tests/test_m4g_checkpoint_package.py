#!/usr/bin/env python3
from __future__ import annotations
from pathlib import Path, PurePosixPath
import hashlib, re, sys, tempfile, zipfile
checks=0
def check(ok,msg):
 global checks; checks+=1; print(('PASS' if ok else 'FAIL')+': '+msg)
 if not ok: raise AssertionError(msg)
if len(sys.argv)!=2: raise SystemExit('Usage: python tests/test_m4g_checkpoint_package.py <checkpoint.zip>')
zip_path=Path(sys.argv[1]).resolve(); check(zip_path.is_file(),'M4-G checkpoint ZIP exists')
with zipfile.ZipFile(zip_path) as archive:
 check(archive.testzip() is None,'checkpoint ZIP CRC passes')
 infos=[i for i in archive.infolist() if not i.is_dir()]; names=[i.filename for i in infos]
 check(len(names)==len(set(names)),'checkpoint ZIP has no duplicate entries')
 check(all('\\' not in n for n in names),'checkpoint ZIP paths use forward slashes')
 check(all(not PurePosixPath(n).is_absolute() for n in names),'checkpoint ZIP has no absolute path')
 check(all('..' not in PurePosixPath(n).parts for n in names),'checkpoint ZIP has no parent traversal path')
 check({PurePosixPath(n).parts[0] for n in names}=={'rss-reader-modernization-m4-g-r1'},'checkpoint top-level directory is exact')
 rels=['/'.join(PurePosixPath(n).parts[1:]) for n in names]
 check(not any(r.lower().endswith('.zip') for r in rels),'checkpoint ZIP contains no nested ZIP')
 forbidden={'config/local.php','.env','rss.sql','rss.zip'}
 check(not any(r in forbidden for r in rels),'checkpoint excludes forbidden named files')
 check(not any(r.lower().endswith(('.sqlite','.sqlite3','.db','.dump','.bak','.backup','.log','.pid')) for r in rels),'checkpoint excludes runtime/database extensions')
 with tempfile.TemporaryDirectory(prefix='rss-m4g-checkpoint-') as tmp:
  archive.extractall(tmp); project=Path(tmp)/'rss-reader-modernization-m4-g-r1'
  manifest=project/'docs/package-manifest-m4-g-r1.txt'; check(manifest.is_file(),'M4-G checkpoint manifest exists')
  expected={}
  for line in manifest.read_text(encoding='utf-8').splitlines():
   if not line.strip(): continue
   digest,rel=line.split('  ',1); check(bool(re.fullmatch(r'[0-9a-f]{64}',digest)),f'manifest digest format is valid: {rel}'); expected[rel]=digest
  actual={p.relative_to(project).as_posix():p for p in project.rglob('*') if p.is_file()}
  check(set(actual)==set(expected)|{'docs/package-manifest-m4-g-r1.txt'},'checkpoint manifest file set matches ZIP')
  for rel,digest in expected.items(): check(hashlib.sha256(actual[rel].read_bytes()).hexdigest()==digest,f'checkpoint manifest SHA-256 matches: {rel}')
  for rel in ['RELEASE_NOTES.md','tools/build_release_package.py','tools/verify_release_package.py','docs/m4-g-implementation.md','docs/m4-g-files.md','docs/test-report-m4-g.md','tests/test_m4g_final_release.py','tests/test_m4g_documentation.py','tests/test_m4g_release_process.py','tests/test_m4g_checkpoint_package.py','LICENSE','THIRD_PARTY_NOTICES.md','var/m4f-evidence/.gitkeep']:
   check((project/rel).is_file(),f'M4-G checkpoint includes: {rel}')
  version=(project/'app/version.php').read_text(encoding='utf-8')
  check("APP_VERSION = '1.0.0'" in version,'extracted checkpoint has final version')
  check("APP_VERSION_LABEL = 'RSS Reader Modernization 1.0.0'" in version,'extracted checkpoint has final label')
  check(not any(project.glob('rss-reader-modernization-1.0.0*.zip')),'checkpoint does not embed final release ZIP')
  evidence=[p for p in (project/'var/m4f-evidence').iterdir() if p.is_file() and p.name!='.gitkeep']; check(not evidence,'checkpoint excludes private evidence')
  pats=[re.compile(r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----'),re.compile(r'\bAKIA[0-9A-Z]{16}\b'),re.compile(r'\bsk-[A-Za-z0-9_-]{20,}\b')]
  hits=[]
  for dirname in ['.github','app','public','config','database','tools','docs']:
   for p in (project/dirname).rglob('*'):
    if not p.is_file(): continue
    try: text=p.read_text(encoding='utf-8')
    except (UnicodeDecodeError,OSError): continue
    if any(x.search(text) for x in pats): hits.append(p.relative_to(project).as_posix())
  check(not hits,'checkpoint contains no high-signal secret pattern')
  for runtime in ['var/session','var/log','var/cache/feed','var/db-migration','var/security/login-throttle']:
   d=project/runtime; generated=[p for p in d.iterdir() if p.is_file() and p.name!='.gitkeep'] if d.exists() else []; check(not generated,f'checkpoint runtime directory is clean: {runtime}')
print(f'All {checks} M4-G checkpoint package checks passed.')
