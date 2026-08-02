#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
PUBLIC = ROOT / 'public'
checks = 0


def check(condition: bool, message: str) -> None:
    global checks
    checks += 1
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        raise AssertionError(message)


version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
readme = (ROOT / 'README.md').read_text(encoding='utf-8')
roadmap = (ROOT / 'docs/roadmap.md').read_text(encoding='utf-8')
versioning = (ROOT / 'docs/versioning.md').read_text(encoding='utf-8')
changelog = (ROOT / 'CHANGELOG.md').read_text(encoding='utf-8')
checklist = (ROOT / 'CHECKLIST_FOR_USER.md').read_text(encoding='utf-8')
index = (PUBLIC / 'index.php').read_text(encoding='utf-8')
login = (ROOT / 'app/common/common_login.php').read_text(encoding='utf-8')
dashboard_css = (PUBLIC / 'css/dashboard.css').read_text(encoding='utf-8')
dashboard_js = (PUBLIC / 'js/dashboard.js').read_text(encoding='utf-8')
api = (ROOT / 'app/api.php').read_text(encoding='utf-8')
common_func = (ROOT / 'app/common/common_func.php').read_text(encoding='utf-8')

check("APP_VERSION = 'M4-A R1'" in version, 'current application version has advanced beyond M2-G')
check("APP_VERSION_LABEL = 'Release M4-A / R1'" in version, 'current visible label has advanced beyond M2-G')
check('**Current checkpoint:** `Release M4-A / R1`' in readme, 'README current checkpoint advances without removing M2-G history')
check('| M2-G | 最終回帰・Documentation | 完了 |' in readme, 'README marks M2-G complete')
check('- [x] M2-G 最終回帰・Documentation' in roadmap, 'Roadmap marks M2-G complete')
check('M2 Frontend (M2-G complete)' in readme, 'README roadmap marks M2 complete')
check('Current: `Release M4-A / R1`' in versioning, 'Version policy current marker advances beyond M2-G')
check(checklist.startswith('# M4-A / R1'), 'User checklist targets current M4-A checkpoint')
check(changelog.find('## Release M4-A / R1') < changelog.find('## Frontend M2-G / R1') < changelog.find('## Frontend M2-F / R1'), 'M2-G changelog history remains ordered below M4-A')

for phase in 'ABCDEFG':
    impl = ROOT / 'docs' / f'm2-{phase.lower()}-implementation.md'
    report = ROOT / 'docs' / f'test-report-m2-{phase.lower()}.md'
    check(impl.is_file() and impl.stat().st_size > 400, f'M2-{phase} implementation document exists')
    check(report.is_file() and report.stat().st_size > 300, f'M2-{phase} test report exists')

check((ROOT / 'docs/m2-completion-summary.md').is_file(), 'M2 completion summary exists')
check('M2-A〜F' in (ROOT / 'docs/m2-g-implementation.md').read_text(encoding='utf-8'), 'M2-G implementation describes cross-phase regression')

expected_css = {
    'all.css', 'bootstrap-flatly.min.css', 'bootstrap-journal.min.css',
    'bootstrap-minty.min.css', 'bootstrap-sketchy.min.css',
    'bootstrap-slate.min.css', 'bootstrap-solar.min.css',
    'bootstrap-yeti.min.css', 'bootstrap.min.css', 'bootstrap.min.css.map',
    'dashboard.css', 'drawer.min.css',
}
expected_js = {
    'bootstrap.min.js', 'bootstrap.min.js.map', 'dashboard.js', 'drawer.min.js',
    'iscroll.js', 'jquery-3.7.1.min.js', 'popper.min.js',
}
expected_fonts = {
    'fa-brands-400.ttf', 'fa-brands-400.woff2',
    'fa-regular-400.ttf', 'fa-regular-400.woff2',
    'fa-solid-900.ttf', 'fa-solid-900.woff2',
    'fa-v4compatibility.ttf', 'fa-v4compatibility.woff2',
}
actual_css = {p.name for p in (PUBLIC / 'css').iterdir() if p.is_file()}
actual_js = {p.name for p in (PUBLIC / 'js').iterdir() if p.is_file()}
actual_fonts = {p.name for p in (PUBLIC / 'webfonts').iterdir() if p.is_file()}
check(actual_css == expected_css, 'final CSS allowlist is exact')
check(actual_js == expected_js, 'final JavaScript allowlist is exact')
check(actual_fonts == expected_fonts, 'final WebFont allowlist is exact')
check(len([p for p in PUBLIC.rglob('*') if p.is_file()]) == 32, 'public surface remains 32 files')

for removed in [
    PUBLIC / 'less', PUBLIC / 'scss', PUBLIC / 'metadata', PUBLIC / 'sprites',
    PUBLIC / 'js/jquery-3.3.1.min.js', PUBLIC / 'css/bootstrap.css',
]:
    check(not removed.exists(), f'removed Frontend asset remains absent: {removed.relative_to(ROOT)}')

jquery = (PUBLIC / 'js/jquery-3.7.1.min.js').read_text(encoding='utf-8')
all_css = (PUBLIC / 'css/all.css').read_text(encoding='utf-8')
bootstrap_js = (PUBLIC / 'js/bootstrap.min.js').read_text(encoding='utf-8')
bootstrap_css = (PUBLIC / 'css/bootstrap.min.css').read_text(encoding='utf-8')
drawer = (PUBLIC / 'js/drawer.min.js').read_text(encoding='utf-8')
iscroll = (PUBLIC / 'js/iscroll.js').read_text(encoding='utf-8')
check('jQuery v3.7.1' in jquery, 'jQuery 3.7.1 remains installed')
check('ajax' in jquery and 'slim' not in jquery[:120].lower(), 'jQuery full AJAX build remains installed')
check('Font Awesome Free 6.7.2' in all_css, 'Font Awesome Free 6.7.2 remains installed')
check('Bootstrap v4.1.3' in bootstrap_js and 'Bootstrap v4.1.3' in bootstrap_css, 'Bootstrap CSS and JS versions remain aligned')
check('jquery-drawer v3.2.2' in drawer, 'Drawer 3.2.2 remains installed')
check('iScroll v5.2.0-snapshot' in iscroll, 'iScroll version remains installed')

for theme in ['bootstrap.min.css', 'bootstrap-yeti.min.css', 'bootstrap-minty.min.css',
              'bootstrap-flatly.min.css', 'bootstrap-journal.min.css',
              'bootstrap-sketchy.min.css', 'bootstrap-solar.min.css',
              'bootstrap-slate.min.css']:
    check(theme in common_func, f'theme remains reachable: {theme}')

check('./js/jquery-3.7.1.min.js' in index and './js/jquery-3.7.1.min.js' in login, 'Dashboard and authentication screens use current jQuery')
check('jquery-3.3.1' not in index + login, 'old jQuery is not referenced')
check(index.find('jquery-3.7.1.min.js') < index.find('popper.min.js') < index.find('bootstrap.min.js'), 'Frontend dependency script order is retained')
check('<script' in index and 'src="https://' not in index, 'Dashboard uses no remote script dependency')
check('<script' in login and 'src="https://' not in login, 'Authentication screen uses no remote script dependency')

check("apiRequest('feed.fetch'" in dashboard_js, 'Feed fetch action remains explicit')
check("apiRequest('content.delete'" in dashboard_js, 'Feed delete action remains explicit')
check('csrf_token' in dashboard_js and 'csrf_token' in index, 'Frontend mutation path retains CSRF token')
check('.text(' in dashboard_js, 'safe text insertion remains in Dashboard JavaScript')
check('.html(' not in dashboard_js, 'Dashboard JavaScript avoids dynamic HTML insertion')
check('window.alert(' not in dashboard_js and 'alert(' not in dashboard_js, 'browser alert regression remains absent')
check("'feed.fetch'" in api and "'content.delete'" in api, 'API dispatcher retains Feed and delete actions')

check('col-12 col-md-6 col-lg-3' in index, '1 / 2 / 4 column responsive grid remains')
check('.feed-stock-column' in dashboard_css and 'width: 44px' in dashboard_css, 'Stock action column remains 44px')
check('min-height: 36px' in dashboard_css, 'compact Drawer desktop target remains')
check('@media (pointer: coarse)' in dashboard_css and 'min-height: 44px' in dashboard_css, 'Touch Drawer target remains 44px')
check('aria-live="polite"' in index and 'role="status"' in index, 'in-page status notification remains accessible')
check('aria-busy="true"' in index, 'Feed loading state retains aria-busy')
check('class="skip-link"' in index, 'shared entry point retains the skip link')
check('data-app-version' in index and 'APP_VERSION_LABEL' in index, 'Dashboard visible version marker remains')
check('data-app-version' in login and 'APP_VERSION_LABEL' in login, 'authentication visible version marker remains')

check(not (ROOT / 'package.json').exists(), 'M2 final release adds no npm package')
check(not (ROOT / 'node_modules').exists(), 'M2 final release contains no node_modules')
check(not (ROOT / 'config/local.php').exists(), 'private local configuration is excluded')
for runtime in ['var/session', 'var/log', 'var/cache/feed', 'var/db-migration']:
    files = [p.name for p in (ROOT / runtime).iterdir() if p.is_file()]
    check(files in ([], ['.gitkeep']), f'Runtime directory contains no generated data: {runtime}')

print(f'All {checks} M2-G final regression checks passed.')
