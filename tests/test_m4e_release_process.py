#!/usr/bin/env python3
from __future__ import annotations
from pathlib import Path
import ast, sys
ROOT=Path(__file__).resolve().parents[1]; checks=[]
def check(ok,msg): checks.append(bool(ok)); print(('PASS' if ok else 'FAIL')+': '+msg)
builder=(ROOT/'tools/build_release_package.py').read_text(encoding='utf-8'); verifier=(ROOT/'tools/verify_release_package.py').read_text(encoding='utf-8'); tag=(ROOT/'docs/tag-and-github-release.md').read_text(encoding='utf-8'); package=(ROOT/'docs/release-package.md').read_text(encoding='utf-8'); checklist=(ROOT/'CHECKLIST_FOR_USER.md').read_text(encoding='utf-8')
check(bool(ast.parse(builder)),'release builder syntax parses'); check(bool(ast.parse(verifier)),'release verifier syntax parses')
for term in ["choices=('preview', 'rc', 'final')",'FIXED_TIME','compresslevel=9',"'config/local.php'","'.env'","'rss.sql'","'rss.zip'",'RELEASE_BUILD.txt','RELEASE_MANIFEST.sha256','publishable']:
 check(term in builder,f'builder retains release safety contract: {term}')
check("version != 'M4-E R1'" in builder,'preview mode requires exact M4-E marker')
check("version != INTENDED_RELEASE" in builder,'final mode requires intended version')
check("label != 'RSS Reader Modernization 1.0.0'" in builder,'final mode requires exact label')
check('path.is_symlink()' in builder,'builder rejects symlinks')
check('runtime directory contains generated files' in builder,'builder rejects generated runtime data')
check('hashlib.sha256(zip_path.read_bytes())' in builder,'builder creates ZIP SHA-256')
for term in ['archive.testzip()','no duplicate entries','no parent traversal path','release manifest file set matches ZIP payload','release metadata matches APP_VERSION','high-signal secret pattern','final package is marked publishable']:
 check(term in verifier,f'verifier retains validation: {term}')
for term in ['git pull --ff-only','git status --short','git tag -a v1.0.0','git show --no-patch --decorate v1.0.0','git push origin v1.0.0','rss-reader-modernization-1.0.0.zip.sha256','Draft a new release','公開済みTagを別Commitへ黙って移動しません','v1.0.1']:
 check(term in tag,f'tag guide contains safety step: {term}')
check('git push --tags' in tag and '使用しません' in tag,'guide rejects bulk tag push')
check('git reset --hard' not in tag,'tag procedure avoids destructive reset')
check('git tag -f' not in tag and '--force' not in tag,'tag procedure avoids force-moving tag')
check('同じSourceから同じmodeで2回BuildしたZIPは同じSHA-256' in package,'package docs define reproducibility')
check('Release ZIPへ含めない' in package,'package docs define exclusions')
check('`final` mode' in package and '完全一致しない限り実行できません' in package,'package docs define final guard')
check(checklist.find('git commit -m "M4-G: release version 1.0.0"')<checklist.find('git tag -a v1.0.0'),'checklist commits before tagging')
check(checklist.find('Push後の確認')<checklist.find('Annotated Tag'),'checklist verifies GitHub after push before tag')
check('git push --tags' in checklist and '使用しません' in checklist,'checklist rejects bulk tag push')
if not all(checks): sys.exit(1)
print(f'All {len(checks)} M4-E historical process checks passed.')
