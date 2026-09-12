#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def text(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


utility_css = text('public/css/utility-widgets.css')
bootstrap_css = text('public/css/bootstrap-5.3.8.min.css')
index_php = text('public/index.php')
modals_php = text('app/view/dashboard_modals.php')

fix_block = re.search(
    r'/\* V1\.34\.2-A:.*?@media\s*\(max-width:\s*575\.98px\).*?\n\}',
    utility_css,
    re.S,
)
fix_text = fix_block.group(0) if fix_block else ''

check(fix_block is not None, 'smartphone modal opacity override exists at 575.98px and below')
check(
    '.modal .modal-content' in fix_text
    and '--bs-modal-bg: var(--bs-body-bg, #fff);' in fix_text
    and 'background-color: var(--bs-body-bg, #fff);' in fix_text,
    'smartphone modal content has an explicit opaque theme-aware surface',
)
check(
    '.modal .modal-body' in fix_text and '.modal .modal-footer' in fix_text,
    'smartphone modal body and footer explicitly share the opaque surface',
)
check(
    '--bs-modal-footer-bg: var(--bs-body-bg, #fff);' in fix_text,
    'smartphone modal footer variable uses the same opaque theme-aware surface',
)
check(
    '!important' not in fix_text,
    'the modal fix does not force precedence with !important',
)
check(
    '--bs-modal-bg:' in bootstrap_css
    and '--bs-modal-footer-bg:' in bootstrap_css
    and 'background-color:var(--bs-modal-bg)' in bootstrap_css
    and 'background-color:var(--bs-modal-footer-bg)' in bootstrap_css,
    'Bootstrap modal variables and surfaces remain intact',
)

theme_pos = index_php.find("resolve_theme_stylesheet($ui['conf_style'] ?? null)")
dashboard_pos = index_php.find("app_asset_url('css/dashboard.css')")
utility_pos = index_php.find("app_asset_url('css/utility-widgets.css')")
check(
    -1 not in (theme_pos, dashboard_pos, utility_pos) and theme_pos < dashboard_pos < utility_pos,
    'utility-widgets.css loads after the selected Bootstrap theme and dashboard.css',
)

check(
    modals_php.count('class="modal ') >= 8,
    'Dashboard keeps multiple Bootstrap modal instances covered by the shared modal selectors',
)
check(
    re.search(r'<div class="modal-content">\s*<form\b', modals_php) is not None,
    'multiline form-wrapped Dashboard modal structure remains present',
)
check(
    '<div class="modal-content"><form' in modals_php,
    'compact one-line form-wrapped Dashboard modal structure remains covered',
)
check(
    utility_css.find('/* V1.34.2-A:') > utility_css.find('@media (pointer: coarse)'),
    'V1.34.2-A override remains a late Dashboard override after existing utility rules',
)

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
