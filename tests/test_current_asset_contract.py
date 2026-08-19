from __future__ import annotations

from pathlib import Path
import re
import sys
from urllib.parse import unquote

from dashboard_source_utils import dashboard_source

ROOT = Path(__file__).resolve().parents[1]
PUBLIC = ROOT / 'public'
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


dashboard = dashboard_source(ROOT)
login = (ROOT / 'app/common/common_login.php').read_text(encoding='utf-8')
common_func = (ROOT / 'app/common/common_func.php').read_text(encoding='utf-8')
markup = dashboard + '\n' + login

# Current contract: assets referenced by application markup must exist.
refs = set(re.findall(r"app_asset_url\('((?:css|js)/[^']+|favicon\.png)'", markup))
refs.update(
    ref.split('?', 1)[0].split('#', 1)[0]
    for ref in re.findall(r'(?:href|src)="\./((?:css|js)/[^\"]+|favicon\.png)"', markup)
    if '<?php' not in ref
)
check(bool(refs), 'application markup exposes local static asset references')
for ref in sorted(refs):
    check((PUBLIC / ref).is_file(), f'referenced asset exists: public/{ref}')

# Theme contract: every configured local Bootstrap theme resolves to a real file.
themes = set(re.findall(r"'bootstrap(?:-[a-z]+)?'\s*=>\s*'([^']+\.css)'", common_func))
check(bool(themes), 'configured theme stylesheet list is not empty')
for theme in sorted(themes):
    check((PUBLIC / 'css' / theme).is_file(), f'configured theme exists: public/css/{theme}')

# CSS contract: local url(...) dependencies must resolve, regardless of inventory size.
for css_path in sorted((PUBLIC / 'css').glob('*.css')):
    text = css_path.read_text(encoding='utf-8', errors='replace')
    for raw in re.findall(r'url\(([^)]+)\)', text):
        value = raw.strip().strip('"\'')
        if not value or value.startswith(('data:', 'http://', 'https://', '//', '#')):
            continue
        clean = unquote(value.split('?', 1)[0].split('#', 1)[0])
        target = (css_path.parent / clean).resolve()
        check(target.is_file(), f'local CSS dependency exists: {target.relative_to(ROOT)}')

# JavaScript modules may lazily load other local JS/CSS files. Verify those links,
# but do not freeze the complete directory inventory or file count.
for js_path in sorted((PUBLIC / 'js').glob('*.js')):
    text = js_path.read_text(encoding='utf-8', errors='replace')
    lazy_refs = set(re.findall(r"['\"]\./((?:js|css)/[A-Za-z0-9._/-]+)(?:\?[^'\"]*)?['\"]", text))
    for ref in sorted(lazy_refs):
        check((PUBLIC / ref).is_file(), f'lazy-loaded asset exists: public/{ref}')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
