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

mobile_blocks = re.findall(r'@media\s*\(max-width:\s*575\.98px\)\s*\{(.*?)\n\}', utility_css, re.S)
modal_rule = re.compile(
    r'\.modal\s*\{\s*--bs-modal-footer-bg:\s*var\(--bs-modal-bg\);\s*\}',
    re.S,
)
matched_mobile_rule = any(modal_rule.search(block) for block in mobile_blocks)

check(matched_mobile_rule, 'smartphone CSS maps the shared modal footer background to the active modal background')
check(
    utility_css.count('--bs-modal-footer-bg: var(--bs-modal-bg);') == 1,
    'the V1.34.2-A modal footer override is defined exactly once',
)
check(
    '!important' not in re.search(
        r'/\* V1\.34\.2-A:.*?@media\s*\(max-width:\s*575\.98px\).*?\n\}',
        utility_css,
        re.S,
    ).group(0),
    'the modal fix does not force precedence with !important',
)
check(
    '--bs-modal-bg:' in bootstrap_css
    and '--bs-modal-footer-bg:' in bootstrap_css
    and 'background-color:var(--bs-modal-bg)' in bootstrap_css
    and 'background-color:var(--bs-modal-footer-bg)' in bootstrap_css,
    'Bootstrap modal variables still provide the theme-aware content/footer surfaces used by the fix',
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
    'Dashboard keeps multiple Bootstrap modal instances covered by the shared .modal selector',
)
check(
    '<div class="modal-content">\n      <form' in modals_php
    or '<div class="modal-content">\n        <form' in modals_php,
    'form-wrapped Dashboard modal structure remains present',
)
check(
    re.search(r'<div class="modal-content">\s*<div class="modal-header">', modals_php) is not None,
    'direct modal-content/header structure remains present and is covered without depending on a form wrapper',
)
check(
    utility_css.find('/* V1.34.2-A:') > utility_css.find('@media (pointer: coarse)'),
    'V1.34.2-A override remains a late Dashboard override after existing utility rules',
)

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
