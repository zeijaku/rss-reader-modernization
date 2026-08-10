from __future__ import annotations
from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]
FILES=[
    ROOT/'README.md', ROOT/'RELEASE_NOTES.md', ROOT/'APPLY_NOTE.md', ROOT/'CHECKLIST_FOR_USER.md',
    ROOT/'docs/README.md', ROOT/'docs/versioning.md', ROOT/'docs/roadmap.md', ROOT/'docs/update.md',
    ROOT/'docs/release-package.md', ROOT/'docs/tag-and-github-release.md', ROOT/'docs/github-v1-8-powershell.md',
    ROOT/'docs/v1-8-b-implementation.md', ROOT/'docs/v1-8-c-implementation.md', ROOT/'docs/v1-8-c-r2-fix.md',
    ROOT/'docs/v1-8-d-implementation.md', ROOT/'docs/v1-8-e-implementation.md', ROOT/'docs/v1-8-release-implementation.md',
    ROOT/'docs/v1-8-release-files.md', ROOT/'docs/test-report-v1-8-release.md', ROOT/'docs/release-gate-v1.8.0.md',
    ROOT/'docs/release-artifact-inventory-v1.8.0.md', ROOT/'docs/github-release-notes-v1.8.0.md',
]
failed=[]; checked=0
for document in FILES:
    if not document.is_file():
        failed.append(f'missing: {document.relative_to(ROOT)}'); continue
    text=document.read_text(encoding='utf-8')
    for target in re.findall(r'\[[^\]]*\]\(([^)]+)\)', text):
        if target.startswith(('http://','https://','mailto:','#')): continue
        path_text=target.split('#',1)[0]
        if not path_text: continue
        checked += 1
        resolved=(document.parent/path_text).resolve()
        ok=resolved.exists() and (resolved==ROOT or ROOT in resolved.parents)
        print(('PASS' if ok else 'FAIL')+f': {document.relative_to(ROOT)} -> {target}')
        if not ok: failed.append(f'{document.relative_to(ROOT)} -> {target}')
if failed: raise SystemExit(f'{len(failed)}/{checked} active documentation links failed: '+', '.join(failed[:5]))
print(f'All {checked} active documentation links passed.')
