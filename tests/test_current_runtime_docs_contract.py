#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
README = (ROOT / 'README.md').read_text(encoding='utf-8')
BUILDER = (ROOT / 'tools/build_release_package.py').read_text(encoding='utf-8')
VERIFY = (ROOT / 'tools/verify_release_package.py').read_text(encoding='utf-8')
PUBLIC_HTACCESS = (ROOT / 'public/.htaccess').read_text(encoding='utf-8')
DEPLOY = (ROOT / 'docs/deployment-checklist.md').read_text(encoding='utf-8')

checks: list[bool] = []

def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

check("'CONTRIBUTING.md'" in BUILDER and "'CONTRIBUTING.md'" in VERIFY,
      'Runtime package includes and requires README-linked CONTRIBUTING.md')
check('ErrorDocument 404 /error.php' in PUBLIC_HTACCESS
      and 'ErrorDocument 500 /error.php' in PUBLIC_HTACCESS
      and '/public/error.php' not in PUBLIC_HTACCESS,
      'public/.htaccess error documents match public/ DocumentRoot')
check('Runtime ZIP' in DEPLOY and 'Complete Source' in DEPLOY,
      'deployment checklist distinguishes Runtime ZIP and Complete Source checks')
check('var/cache/' in DEPLOY,
      'deployment checklist verifies the Current private cache root')

# Runtime builder includes every file below docs/, so local docs links remain available.
check("for path in sorted(docs.rglob('*'))" in BUILDER,
      'Runtime package includes the complete docs tree')

# Root-level markdown links from README must exist and be explicitly included in Runtime.
root_links = {
    target.split('#', 1)[0]
    for target in re.findall(r'\[[^\]]+\]\(([^)]+)\)', README)
    if target.endswith('.md') and '/' not in target and not target.startswith('http')
}
missing_source = sorted(target for target in root_links if not (ROOT / target).is_file())
check(not missing_source, 'README root-level Markdown links exist in source')
missing_runtime = sorted(target for target in root_links if f"'{target}'" not in BUILDER)
check(not missing_runtime, 'README root-level Markdown links are included in Runtime package')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
