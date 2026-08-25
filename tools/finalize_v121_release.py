#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
RELEASE = '1.21.0'
TAG = 'v1.21.0'
DATE = '2026-08-25'


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def write(path: str, content: str) -> None:
    target = ROOT / path
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content, encoding='utf-8', newline='\n')


def replace(path: str, replacements: list[tuple[str, str]]) -> None:
    content = read(path)
    original = content
    for old, new in replacements:
        if old not in content:
            raise SystemExit(f'ERROR: expected text not found in {path}: {old!r}')
        content = content.replace(old, new)
    if content != original:
        write(path, content)


write('app/version.php', """<?php

declare(strict_types=1);

/**
 * Visible release marker for deployment verification.
 * Update these values for every distributed checkpoint/build.
 */
const APP_VERSION = '1.21.0';
const APP_VERSION_LABEL = 'RSS Reader Modernization 1.21.0';

/**
 * Cache key for public assets.
 * The formal Version 1.21.0 release uses the same final key across the
 * Drawer category, visual, and Smartphone layers.
 */
const APP_ASSET_REVISION = '1.21.0';
""")

replace('public/js/calendar.js', [
    ('?v=1.20.1', '?v=1.21.0'),
    ("./js/drawer-categories.js?v=1.21-c3", "./js/drawer-categories.js?v=1.21.0"),
    ('// V1.21-C: reload the existing Drawer organizer under the current\n    // checkpoint key. It stages both the B visual layer and C mobile layer.',
     '// V1.21: load the finalized Drawer organizer under the formal release\n    // cache key. It stages both the visual and Smartphone layers.'),
])

replace('public/js/drawer-categories.js', [
    ('./css/drawer-v121b.css?v=1.21-b1', './css/drawer-v121b.css?v=1.21.0'),
    ('./css/drawer-v121c.css?v=1.21-c3', './css/drawer-v121c.css?v=1.21.0'),
])

# Keep the existing release tooling and its safety rules; only roll its exact
# release identity forward to Version 1.21.0.
for tool in (
    'tools/build_release_package.py',
    'tools/verify_release_package.py',
    'tools/build_complete_package.py',
    'tools/verify_complete_package.py',
):
    content = read(tool)
    if '1.20.1' not in content:
        raise SystemExit(f'ERROR: expected 1.20.1 release identity not found in {tool}')
    write(tool, content.replace('1.20.1', '1.21.0'))

# The C checkpoint test becomes a compatibility test for the formal cache key.
c_test = read('tests/test_v121c_mobile_touch.py')
c_test = c_test.replace("const APP_ASSET_REVISION = '1.21-c3';", "const APP_ASSET_REVISION = '1.21.0';")
c_test = c_test.replace("loadScript('./js/drawer-categories.js?v=1.21-c3');", "loadScript('./js/drawer-categories.js?v=1.21.0');")
c_test = c_test.replace("./css/drawer-v121c.css?v=1.21-c3", "./css/drawer-v121c.css?v=1.21.0")
c_test = c_test.replace("./css/drawer-v121b.css?v=1.21-b1", "./css/drawer-v121b.css?v=1.21.0")
write('tests/test_v121c_mobile_touch.py', c_test)

write('tests/test_v121e_final.py', r'''#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
checks = []


def text(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def check(condition: bool, message: str) -> None:
    ok = bool(condition)
    checks.append(ok)
    print(('PASS' if ok else 'FAIL') + ': ' + message)


version = text('app/version.php')
calendar = text('public/js/calendar.js')
drawer = text('public/js/drawer-categories.js')
mobile_css = text('public/css/drawer-v121c.css')
visual_css = text('public/css/drawer-v121b.css')
dashboard_js = text('public/js/dashboard.js')
utility_js = text('public/js/utility-widgets.js')
readme = text('README.md')
changelog = text('CHANGELOG.md')
notes = text('RELEASE_NOTES.md')
apply_note = text('APPLY_NOTE_V1_21_0.md')
release_doc = text('docs/v1-21-0-final-release.md')

check("const APP_VERSION = '1.21.0';" in version, 'Formal APP_VERSION is 1.21.0')
check("const APP_VERSION_LABEL = 'RSS Reader Modernization 1.21.0';" in version, 'Formal release label is 1.21.0')
check("const APP_ASSET_REVISION = '1.21.0';" in version, 'Formal asset revision is 1.21.0')
check('**Stable release:** RSS Reader Modernization 1.21.0' in readme, 'README names Version 1.21.0 as stable')
check('Release tag: `v1.21.0`' in readme, 'README names the final tag')
check('## 1.21.0 - 2026-08-25' in changelog, 'CHANGELOG contains Version 1.21.0 entry')
check('# RSS Reader Modernization 1.21.0' in notes, 'Release Notes target Version 1.21.0')
check('v1.21.0' in apply_note and 'DB Migrationはありません' in apply_note, 'Production apply note records tag and no DB migration')
check('v1.21.0' in release_doc and 'V1.20.1' in release_doc, 'Final release document records baseline and target')

check("loadScript('./js/drawer-categories.js?v=1.21.0');" in calendar, 'Dashboard loader uses formal Drawer cache key')
check('./css/drawer-v121b.css?v=1.21.0' in drawer, 'Drawer visual layer uses formal cache key')
check('./css/drawer-v121c.css?v=1.21.0' in drawer, 'Drawer Smartphone layer uses formal cache key')
check('1.21-c3' not in version + calendar + drawer, 'Checkpoint C cache key is absent from formal runtime loader')
check('1.21-b1' not in drawer, 'Checkpoint B cache key is absent from formal runtime loader')

expected_order = ["'display'", "'feed'", "'productivity'", "'information'", "'media'", "'game'", "'settings'", "'user-links'", "'account'"]
pos = [drawer.find(item, drawer.find('var sectionOrder')) for item in expected_order]
check(all(value >= 0 for value in pos) and pos == sorted(pos), 'Final Drawer section order remains stable')
check("'productivity': ['#registerTaskWidget', '#registerCalendarWidget', '#registerMemo', '#registerClock', '#registerMailWidget']" in drawer, 'Mail remains in PRODUCTIVITY')
check("'media': ['#registerCameraVideo']" in drawer, 'Camera / Video remains in MEDIA')
check("mobileOnly: true" in drawer and "'user-links'" in drawer, 'Configured user links remain Smartphone-only')
check('window.setTimeout(organizeDrawer, 0)' in drawer, 'Dynamic Mail / Camera insertion remains compatible')

check('background-color: #f6f7f9' in visual_css, 'Drawer keeps the restrained light-gray surface')
check('border-left-color: #0d6efd' in visual_css, 'Current item keeps the single blue left indicator')
check('.drawer-section-title' in visual_css and 'background-color: #eef2f6' in visual_css, 'Section headers keep neutral visual hierarchy')
check('#drawerMenu .widget-catalog-toggle' in mobile_css and 'padding-right: 12px !important' in mobile_css, 'Smartphone Widget Catalog chevron remains inset')
check('min-height: 44px' in mobile_css, 'Smartphone touch targets remain at least 44px')
check('fa-chevron-right widget-catalog-chevron' in utility_js, 'Accordion chevron source remains the existing Widget Catalog implementation')

check('bootstrap.Offcanvas.getOrCreateInstance(drawerElement)' in dashboard_js, 'Bootstrap 5 Offcanvas remains the Drawer controller')
check("hidden.bs.offcanvas" in dashboard_js and 'bootstrap.Modal.getOrCreateInstance(nextModal.element).show' in dashboard_js, 'Drawer-to-Modal transition still waits for Offcanvas hidden')
check('bootstrap.Offcanvas' not in drawer, 'Drawer organizer does not replace Bootstrap behavior')

for tool in (
    'tools/build_release_package.py',
    'tools/verify_release_package.py',
    'tools/build_complete_package.py',
    'tools/verify_complete_package.py',
):
    body = text(tool)
    check('1.21.0' in body and '1.20.1' not in body, f'{tool} targets Version 1.21.0')

migration_names = [p.name for p in (ROOT / 'database').rglob('*.sql') if 'v1_21' in p.name.lower() or 'v121' in p.name.lower()]
check(migration_names == [], 'Version 1.21 adds no database migration')
check(not (ROOT / 'config/local.php').exists(), 'Repository does not contain config/local.php')
check('File Upload' in release_doc and 'Imgur' in release_doc and 'Grid' in release_doc, 'Deferred features remain documented outside Version 1.21 scope')

passed = sum(checks)
failed = len(checks) - passed
print(f'RESULT: PASS {passed} / FAIL {failed}')
sys.exit(1 if failed else 0)
''')

write('tests/run-v121e.sh', '''#!/usr/bin/env sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

printf '%s\\n' '== V1.21-E final release contract =='
python3 "$ROOT/tests/test_v121e_final.py"

printf '%s\\n' '== V1.21-C Smartphone / Touch compatibility =='
python3 "$ROOT/tests/test_v121c_mobile_touch.py"

printf '%s\\n' '== V1.21-B visual compatibility =='
python3 "$ROOT/tests/test_v121b_drawer_visual.py"

printf '%s\\n' '== V1.21-A structure compatibility =='
python3 "$ROOT/tests/test_v121a_drawer_categories.py"

printf '%s\\n' '== Existing Drawer / Offcanvas compatibility =='
python3 "$ROOT/tests/test_v13b_drawer_structure.py"

printf '%s\\n' '== Final changed PHP syntax =='
php -l "$ROOT/app/version.php"
php -l "$ROOT/public/settings.php"

printf '%s\\n' '== Final changed JavaScript syntax =='
node --check "$ROOT/public/js/calendar.js"
node --check "$ROOT/public/js/drawer-categories.js"

printf '%s\\n' 'V1.21-E final checks passed.'
''')

readme = read('README.md')
readme = readme.replace('**Stable release:** RSS Reader Modernization 1.20.1', '**Stable release:** RSS Reader Modernization 1.21.0', 1)
readme = readme.replace('- Release tag: `v1.20.1`', '- Release tag: `v1.21.0`', 1)
marker = '- Release tag: `v1.21.0`\n'
summary = '''- Version 1.21.0 reorganizes the Drawer into clear functional categories, improves visual hierarchy, and refines Smartphone / Touch behavior without replacing Bootstrap 5 Offcanvas.\n- Version 1.21.0 requires no database migration and no `config/local.php` changes.\n'''
if summary not in readme:
    readme = readme.replace(marker, marker + summary, 1)
write('README.md', readme)

changelog = read('CHANGELOG.md')
entry = '''## 1.21.0 - 2026-08-25

### Drawer / Navigation
- Reorganized the Drawer into DISPLAY, FEED, PRODUCTIVITY, INFORMATION, MEDIA, GAME, SETTINGS, USER LINKS, and ACCOUNT without rebuilding existing actions.
- Kept Mail in PRODUCTIVITY and Camera / Video in MEDIA while preserving their existing dynamic insertion and feature implementations.
- Preserved configured user links for Smartphone while keeping the existing PC Navbar presentation.

### Visual hierarchy
- Added a restrained light-gray Drawer surface, clearer section headers, compact icon tiles, and more visible hover / focus states.
- Kept a single blue Current indicator and removed the similar blue section-header marker after Production review.
- Kept Logout in a restrained Danger treatment.

### Smartphone / Touch
- Kept 44px touch targets, improved Drawer scrolling and dynamic viewport / safe-area handling, and prevented long labels from causing horizontal overflow.
- Kept tall Modals within the Smartphone viewport without changing the existing Offcanvas-to-Modal lifecycle.
- Moved the RSS / Information Widget Catalog accordion chevron slightly inward from the right edge for easier touch operation.

### Compatibility / scope
- Bootstrap 5 Offcanvas and the existing jQuery-assisted behavior remain in place; no unrelated JavaScript modernization was introduced.
- No database schema or migration changes are required for Version 1.21.0.
- No `config/local.php` changes are required.
- File Upload / File Library / Image Viewer, Imgur Widget, and whole-grid Height 2 alignment remain deferred.

### Verification
- V1.21-A/B/C focused and compatibility tests were completed during development.
- Version 1.21.0 finalization runs the full current regression suite, compatibility gates, source secret scan, package verification, and clean-room package checks before release publication.

'''
if '## 1.21.0 - 2026-08-25' not in changelog:
    changelog = changelog.replace('# Changelog\n\n', '# Changelog\n\n' + entry, 1)
write('CHANGELOG.md', changelog)

write('RELEASE_NOTES.md', '''# RSS Reader Modernization 1.21.0

Version 1.21.0 is the Drawer / Navigation organization and readability release based on the formal Version 1.20.1 baseline.

## Highlights

- Drawer categories are now organized as DISPLAY, FEED, PRODUCTIVITY, INFORMATION, MEDIA, GAME, SETTINGS, USER LINKS, and ACCOUNT where applicable.
- Existing Mail and Camera / Video functions remain intact while being presented in PRODUCTIVITY and MEDIA.
- Drawer visual hierarchy is clearer without category-by-category rainbow coloring.
- Current state remains distinct, Logout retains Danger styling, and keyboard focus remains visible.
- Smartphone Drawer scrolling, safe-area handling, 44px touch targets, Modal fit, and long-label behavior are refined.
- RSS / Information Widget Catalog accordion chevrons are moved slightly inward on Smartphone for easier operation.

## Compatibility

- Bootstrap 5 Offcanvas remains the Drawer implementation.
- Existing jQuery support remains in place.
- No database migration is required for Version 1.21.0.
- No `config/local.php` change is required.
- Existing authentication, CSRF, SSRF, XSS, PDO, Session, and secret-handling protections are not intentionally changed by this release.

## Deferred from Version 1.21

- File Upload / File Library / Image Viewer
- Imgur Random / Gallery Widget
- Whole-dashboard Grid alignment for Height 2 Widgets

## Release assets

- Runtime ZIP: `rss-reader-modernization-1.21.0.zip`
- Runtime SHA-256: `rss-reader-modernization-1.21.0.zip.sha256`
- Complete Source ZIP: `rss-reader-modernization-1.21.0-complete.zip`
- Complete Source SHA-256: `rss-reader-modernization-1.21.0-complete.zip.sha256`

## Production update

Back up the current application, extract the Runtime ZIP, preserve the Production `config/local.php` and runtime data, overwrite the application files, and perform one hard reload. See `APPLY_NOTE_V1_21_0.md` and `docs/v1-21-0-production-checklist.md` for the verification points.
''')

write('APPLY_NOTE_V1_21_0.md', '''# RSS Reader Modernization V1.21.0 Production Apply Note

対象Tag: `v1.21.0`
Baseline: 正式版 V1.20.1

## 適用

1. 現在のApplicationとProduction設定をBackupしてください。
2. `rss-reader-modernization-1.21.0.zip` を展開し、Directory構造を維持したままApplicationへ上書きしてください。
3. Productionの `config/local.php` と `var/` 配下のRuntime dataは置き換えないでください。
4. V1.21.0用のDB Migrationはありません。SQL実行は不要です。
5. `config/local.php` の設定追加・変更は不要です。
6. 適用後はBrowserで一度Hard Reloadしてください。

## Production確認

- DrawerがDISPLAY / FEED / PRODUCTIVITY / INFORMATION / MEDIA / GAME / SETTINGS / ACCOUNTの順で表示されること。
- 設定済みUser LinkはSmartphoneでUSER LINKSへ、PCでは従来どおりNavbarへ表示されること。
- Current項目だけが左側のBlue indicatorを持ち、Section Headerと混同しないこと。
- RSS / Search Feed / Task / Calendar / Memo / Clock / Mail / Links / Weather / Camera / Video / Gameの追加導線が動作すること。
- SmartphoneのRSS / Information Accordionの `>` が右端に寄りすぎず操作しやすいこと。
- DrawerからModalを開く際、Offcanvasが閉じてからModalが表示されること。
- SmartphoneでDrawerを下端までScroll出来ること、横Scrollが発生しないこと、Touch領域が狭くなっていないこと。
- Account SettingsとLogoutが従来どおり動作すること。

問題があればV1.20.1のBackupへRollbackしてください。
''')

write('docs/v1-21-0-production-checklist.md', '''# Version 1.21.0 Production Checklist

## Before deployment

- Back up the current application and Production configuration.
- Confirm `config/local.php` remains outside the distributed package.
- No Version 1.21 database migration is required.

## Desktop

- Open and close the Bootstrap Offcanvas Drawer.
- Confirm category order and Current state.
- Confirm User Links remain in the Navbar rather than duplicated in the Drawer.
- Open representative RSS, Productivity, Information, Media, Game, and Account Modals.

## Smartphone

- Scroll the Drawer from top to ACCOUNT without horizontal overflow.
- Confirm touch targets remain comfortable and the Drawer close control works.
- Confirm configured User Links appear under USER LINKS.
- Confirm RSS / Information Widget Catalog accordion chevrons are inset from the right edge.
- Confirm Drawer actions close Offcanvas before opening the target Modal.
- Confirm long Modals remain inside the viewport and can scroll.

## Final

- Confirm Logout still uses POST + CSRF and works normally.
- Hard reload once after deployment to clear checkpoint asset caches.
- Record the deployed tag as `v1.21.0`.
''')

write('docs/v1-21-0-final-release.md', '''# RSS Reader Modernization Version 1.21.0 Final Release

Version 1.21.0 is finalized from the formal Version 1.20.1 baseline and the reviewed V1.21-A, V1.21-B, and V1.21-C changes.

## Final scope

- Drawer category / information architecture cleanup
- Restrained visual hierarchy and Current / Danger states
- Smartphone / Touch fit, scroll, safe-area, and accordion-chevron refinement
- Documentation, deterministic Runtime / Complete Source packaging, regression, compatibility, and secret checks

## Explicitly unchanged

- Bootstrap 5 Offcanvas remains the Drawer implementation.
- Existing jQuery-assisted behavior remains where it already works.
- Database schema and migrations are unchanged by Version 1.21.
- Production `config/local.php` contract is unchanged.

## Deferred

- File Upload / File Library / Image Viewer
- Imgur Random / Gallery Widget
- Whole-dashboard Grid alignment for Height 2 Widgets

## Release identity

The immutable Git tag `v1.21.0` is the authoritative source identity for the release. The GitHub Release attaches the Runtime ZIP, Complete Source ZIP, and their SHA-256 sidecars produced by the release gate from that exact commit.

The existing `v1.20.1` tag must never be moved or overwritten.
''')

write('docs/tag-and-github-release.md', '''# Tag and GitHub Release Procedure

## Current formal target

- Version: `1.21.0`
- Tag: `v1.21.0`
- Release branch: `release/v1.21.0-final`

## Safety rules

- Never move or overwrite `v1.20.1` or any existing formal release tag.
- Never force-update `v1.21.0` if it already exists.
- The final tag must point to the exact commit that passed the Version 1.21 release gate.
- Production `config/local.php`, runtime data, secrets, and legacy private archives must not be added to release assets.

## Gate

The Version 1.21 release workflow performs the final release contract, full current regression, historical compatibility gates, high-signal source secret scan, deterministic Runtime / Complete Source package verification, and clean-room package checks.

Only after the gate succeeds may `v1.21.0` and the GitHub Release be created.

## Formal assets

- `rss-reader-modernization-1.21.0.zip`
- `rss-reader-modernization-1.21.0.zip.sha256`
- `rss-reader-modernization-1.21.0-complete.zip`
- `rss-reader-modernization-1.21.0-complete.zip.sha256`

After publication, `main` should be fast-forwarded to the same exact commit as `v1.21.0`.
''')

print('Version 1.21.0 finalization files prepared.')
