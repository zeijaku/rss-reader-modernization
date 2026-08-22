#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
checks: list[tuple[str, bool]] = []


def check(name: str, condition: bool) -> None:
    checks.append((name, bool(condition)))


def text(rel: str) -> str:
    return (ROOT / rel).read_text(encoding='utf-8')

version = text('app/version.php')
calendar = text('public/js/calendar.js')
streaming = text('public/js/camera-video-streaming.js')
readme = text('README.md')
changelog = text('CHANGELOG.md')
notes = text('RELEASE_NOTES.md')
rb = text('tools/build_release_package.py')
rv = text('tools/verify_release_package.py')
cb = text('tools/build_complete_package.py')
cv = text('tools/verify_complete_package.py')
ci = text('.github/workflows/ci.yml')
release_workflow = text('.github/workflows/v1.19.0-release.yml')

check('APP_VERSION is exact final 1.19.0', "const APP_VERSION = '1.19.0';" in version)
check('visible label is exact final 1.19.0', "const APP_VERSION_LABEL = 'RSS Reader Modernization 1.19.0';" in version)
check('final asset revision is 1.19.0', "const APP_ASSET_REVISION = '1.19.0';" in version)

assets = [
    './css/mail-widget.css', './css/camera-video.css', './css/camera-video-playback.css', './css/camera-video-streaming.css', './css/x-widget.css',
    './js/app-notice.js', './js/widget-card-refresh.js', './js/information-widget-watchdog.js', './js/mail-widget-watchdog.js',
    './js/camera-video-watchdog.js', './js/mail-widget.js', './js/camera-video.js', './js/camera-video-playback.js',
    './js/camera-video-streaming.js', './js/x-widget.js', './js/widget-settings-no-reload.js',
]
for asset in assets:
    check(f'final lazy asset revision: {asset}', asset + '?v=1.19.0' in calendar)
check('Camera streaming fallback CSS uses final revision', './css/camera-video-streaming.css?v=1.19.0' in streaming)
check('hls.js SRI digest remains browser-computed value', 'sha384-5E8B0pTlZZJMabWpC0fyYf6OUpe15jJij34BqBAh4NXoHAlLNOjCPRrwtOXOQFAn' in streaming)

check('README promotes V1.19.0 to stable release', '**Stable release:** `RSS Reader Modernization 1.19.0`' in readme and 'Release tag: `v1.19.0`' in readme)
check('README no longer exposes an active RC', '**Current release candidate:**' not in readme)
check('CHANGELOG has final V1.19.0 entry', '## RSS Reader Modernization 1.19.0 — 2026-08-22' in changelog)
check('release notes target final V1.19.0', notes.startswith('# RSS Reader Modernization 1.19.0 Release Notes'))
check('final release notes have no RC non-release warning', '正式Releaseではありません' not in notes)
check('final release notes disclose verification limits', '## Verification limits' in notes)

check('runtime builder final contract targets 1.19.0/v1.19.0', "INTENDED_RELEASE = '1.19.0'" in rb and "INTENDED_TAG = 'v1.19.0'" in rb and "if mode == 'final'" in rb)
check('runtime verifier recognizes final package state', "metadata.get('package_status') == 'FINAL'" in rv and "metadata.get('publishable') == 'yes'" in rv)
check('complete builder emits final publishable source', "VERSION = '1.19.0'" in cb and 'package_status=FINAL' in cb and 'publishable=yes' in cb)
check('complete verifier requires final source', "VERSION = '1.19.0'" in cv and 'source build metadata targets final Version 1.19.0' in cv)

check('CI runs V1.19 final gate', 'bash tests/run-v119f.sh' in ci)
check('V1.19 release workflow is read-only', 'permissions:\n  contents: read' in release_workflow and 'contents: write' not in release_workflow)
check('V1.19 release workflow builds final packages', '--mode final' in release_workflow and 'rss-reader-modernization-1.19.0.zip' in release_workflow and 'rss-reader-modernization-1.19.0-complete.zip' in release_workflow)

for marker in [
    '.v118-publish-pr-note', '.v118-publish-pr-instructions', '.v118-publish-pr-marker',
    '.v118-publish-do-not-merge', '.v118-publish-trigger-2', '.v118-publish-final-marker',
]:
    check(f'temporary V1.18 publication marker is absent: {marker}', not (ROOT / '.github' / marker).exists())

check('V1.19 final release documentation exists', (ROOT / 'docs/v1-19-f-final-release.md').is_file())
check('V1.19 final production checklist exists', (ROOT / 'docs/v1-19-f-production-checklist.md').is_file())
check('V1.19 final updated-files record exists', (ROOT / 'docs/v1-19-f-updated-files.md').is_file())
check('V1.19 adds no database migration', not any(re.search(r'1[_\.-]19', p.name, re.I) for p in (ROOT / 'database/migrations').glob('*')) if (ROOT / 'database/migrations').is_dir() else True)

failed = [name for name, ok in checks if not ok]
for name, ok in checks:
    print(f"[{'PASS' if ok else 'FAIL'}] {name}")
print(f"RESULT: PASS {len(checks)-len(failed)} / FAIL {len(failed)} / SKIP 0")
raise SystemExit(1 if failed else 0)
