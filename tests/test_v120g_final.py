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
readme = text('README.md')
changelog = text('CHANGELOG.md')
notes = text('RELEASE_NOTES.md')
clock_css = text('public/css/clock-timer.css')
mini_js = text('public/js/mini-game.js')
mini_css = text('public/css/mini-game.css')
mini_php = text('app/mini_game.php')
modals = text('app/view/dashboard_modals.php')
allrss_php = text('app/all_rss_recent.php')
allrss_api = text('app/api/all_rss_recent.php')
allrss_js = text('public/js/all-rss-recent.js')
api = text('public/api_v1.php')
calendar = text('public/js/calendar.js')
streaming = text('public/js/camera-video-streaming.js')
rb = text('tools/build_release_package.py')
rv = text('tools/verify_release_package.py')
cb = text('tools/build_complete_package.py')
cv = text('tools/verify_complete_package.py')
ci = text('.github/workflows/ci.yml')
release_workflow = text('.github/workflows/v1.20.0-release.yml')

# Final version / publication boundary.
check('APP_VERSION is exact V1.20.0', "const APP_VERSION = '1.20.0';" in version)
check('visible label is exact V1.20.0', "const APP_VERSION_LABEL = 'RSS Reader Modernization 1.20.0';" in version)
check('asset revision is exact V1.20.0', "const APP_ASSET_REVISION = '1.20.0';" in version)
check('README promotes V1.20.0 to stable release', '**Stable release:** `RSS Reader Modernization 1.20.0`' in readme and 'Release tag: `v1.20.0`' in readme)
check('README no longer exposes active RC', '**Current release candidate:**' not in readme)
check('CHANGELOG contains final V1.20.0 entry', '## RSS Reader Modernization 1.20.0 — 2026-08-23' in changelog)
check('CHANGELOG keeps RC1 history', '## RSS Reader Modernization 1.20.0-RC1 — 2026-08-23' in changelog)
check('release notes target final V1.20.0', notes.startswith('# RSS Reader Modernization 1.20.0 Release Notes'))
check('final release notes contain no RC non-release warning', '正式Releaseではありません' not in notes)
check('final release notes disclose verification limits', '## Verification limits' in notes)

# Dynamic asset cache revision.
for asset in [
    './css/mail-widget.css', './css/camera-video.css', './css/camera-video-playback.css', './css/camera-video-streaming.css', './css/x-widget.css',
    './js/app-notice.js', './js/widget-card-refresh.js', './js/information-widget-watchdog.js', './js/mail-widget-watchdog.js',
    './js/camera-video-watchdog.js', './js/mail-widget.js', './js/camera-video.js', './js/camera-video-playback.js',
    './js/camera-video-streaming.js', './js/x-widget.js', './js/widget-settings-no-reload.js',
]:
    check(f'lazy asset uses V1.20 final revision: {asset}', asset + '?v=1.20.0' in calendar)
check('RC1 lazy asset revision is removed from calendar loader', '1.20.0-rc1' not in calendar)
check('Camera streaming fallback CSS uses V1.20 final revision', './css/camera-video-streaming.css?v=1.20.0' in streaming)
check('hls.js SRI digest remains verified', 'sha384-5E8B0pTlZZJMabWpC0fyYf6OUpe15jJij34BqBAh4NXoHAlLNOjCPRrwtOXOQFAn' in streaming)

# V1.20-B compact header contract.
check('Card Header Compact R3 marker is retained', 'V1.20-B R3' in clock_css)
check('RSS table header is fixed to 40px', '#main-content .dashboard-widget.feed-card .feed-table > thead' in clock_css and 'height: 40px !important;' in clock_css)
check('RSS header compact rule stays scoped to thead', 'article rows keep their existing 44px touch targets' in clock_css)

# V1.20-C RSS Typing contract.
typing_marker = '/* V1.20-C RSS Typing Game */'
wire_marker = '/* V1.20-D R7 Wire Defense: missile reload, damage palette, curved packet routes */'
typing_start = mini_js.find(typing_marker)
wire_start = mini_js.find(wire_marker)
typing = mini_js[typing_start:wire_start] if typing_start >= 0 and wire_start > typing_start else ''
check('RSS Typing implementation is retained', bool(typing) and 'window.RssTypingGame' in typing)
check('RSS Typing only initializes normal RSS cards', ".feed-card[data-feed-content-id]:not(.search-feed-card)" in typing)
check('RSS Typing supports IME composition events', 'compositionstart' in typing and 'compositionend' in typing)
check('RSS Typing supports Escape exit', "event.key === 'Escape'" in typing or "key === 'Escape'" in typing)
check('RSS Typing performs no network request', 'fetch(' not in typing and 'XMLHttpRequest' not in typing and '$.ajax' not in typing and 'api_v1.php' not in typing)
check('RSS Typing uses browser storage fallback', 'localStorage' in typing and 'sessionStorage' in typing and "storageMode = 'memory'" in typing)
check('RSS Typing has no audio API', 'AudioContext' not in typing and 'new Audio(' not in typing)
check('RSS Typing CSS is retained', '.rss-typing-panel' in mini_css and '.rss-typing-input' in mini_css)

# V1.20-D Wire Defense contract.
wire = mini_js[wire_start:] if wire_start >= 0 else ''
check('Wire Defense is an allowed Game subtype', "return ['icon_quest', 'lights_out', 'wire_defense'];" in mini_php)
check('Wire Defense register/change modal options exist', modals.count('option value="wire_defense"') == 2)
check('Wire Defense Drawer preset integration is retained', 'data-game-preset="wire_defense"' in mini_js and '#widgetCatalog-game' in mini_js)
check('Wire Defense R7 implementation is retained', bool(wire) and 'MISSILE_RELOAD_MS = 1000' in wire and 'window.RssWireDefense' in wire)
check('Wire Defense trajectory split is 50/30/20', "if (roll < 0.5) return 'straight';" in wire and "if (roll < 0.8) return 'curve';" in wire and "return 'wave';" in wire)
check('Wire Defense uses animation frame and visibility/pagehide cleanup', 'requestAnimationFrame' in wire and 'cancelAnimationFrame' in wire and 'visibilitychange' in wire and 'pagehide' in wire)
check('Wire Defense game loop uses no interval/timeout', 'setInterval(' not in wire and 'setTimeout(' not in wire)
check('Wire Defense performs no network request', 'fetch(' not in wire and 'XMLHttpRequest' not in wire and '$.ajax' not in wire)
check('Wire Defense has no audio API', 'AudioContext' not in wire and 'new Audio(' not in wire)
check('Wire Defense CSS is retained', '.wire-defense-canvas' in mini_css and '.wire-defense-controls' in mini_css and '.wire-defense-status' in mini_css)

# V1.20-E All RSS Recent contract.
check('All RSS Recent backend exists', (ROOT / 'app/all_rss_recent.php').is_file() and (ROOT / 'app/api/all_rss_recent.php').is_file())
check('All RSS Recent uses private sentinel and owned scope', "const ALL_RSS_RECENT_MODE = 'all_rss_recent';" in allrss_php and "'scope' => 'owned'" in allrss_php)
check('All RSS Recent result limit is bounded to 5/10/20/30', 'return [5, 10, 20, 30];' in allrss_php)
check('All RSS Recent reuses existing feed fetch/safe payload path', 'FeedFetchService::fromRuntimeConfiguration()' in allrss_php and 'FeedSource::fromValidatedValues(' in allrss_php and 'api_safe_feed_payload(' in allrss_php)
check('All RSS Recent does not bypass outbound fetch policy', 'curl_' not in allrss_php and 'file_get_contents(' not in allrss_php)
check('All RSS Recent API exposes only intended actions', all(action in allrss_api for action in ['widget.allrss.create', 'widget.allrss.update', 'widget.allrss.delete', 'widget.allrss.fetch']))
check('All RSS Recent API dispatch remains behind auth/CSRF/request-size/session release', api.find('app_session_start();') < api.find("str_starts_with($action, 'widget.allrss.')") and api.find('app_csrf_is_valid') < api.find("str_starts_with($action, 'widget.allrss.')") and api.find('APP_API_MAX_REQUEST_BYTES') < api.find("str_starts_with($action, 'widget.allrss.')") and api.find('app_session_release();') < api.find("str_starts_with($action, 'widget.allrss.')"))
check('All RSS Recent frontend only rewrites identified Search Feed fetches', "settings.data.action === 'widget.search.fetch' && ids[widgetId] === true" in allrss_js and "action: 'widget.allrss.fetch'" in allrss_js)
check('All RSS Recent frontend has dedicated add/change flows', '#registerAllRssRecent' in allrss_js and '#changeAllRssRecent' in allrss_js)
check('All RSS Recent frontend asset is included by dashboard modals', "app_asset_url('js/all-rss-recent.js')" in modals)
check('V1.20 adds no database migration', not any(re.search(r'1[_\.-]20', p.name, re.I) for p in (ROOT / 'database/migrations').glob('*')) if (ROOT / 'database/migrations').is_dir() else True)

# Package tooling / CI / release workflow / documentation.
check('Runtime builder targets intended V1.20.0/v1.20.0 final mode', "INTENDED_RELEASE = '1.20.0'" in rb and "INTENDED_TAG = 'v1.20.0'" in rb and "mode == 'final'" in rb)
check('Runtime verifier enforces final V1.20 metadata', "metadata.get('intended_release') == '1.20.0'" in rv and "metadata.get('publishable') == 'yes'" in rv and "final package has exact 1.20.0 version" in rv)
check('Complete builder targets V1.20.0 FINAL publishable source', "VERSION = '1.20.0'" in cb and 'package_status=FINAL' in cb and 'publishable=yes' in cb)
check('Complete verifier requires exact final V1.20.0 marker', "VERSION = '1.20.0'" in cv and "RSS Reader Modernization 1.20.0" in cv and 'publishable=yes' in cv)
check('CI runs V1.20-G final gate', 'bash tests/run-v120g.sh' in ci and 'bash tests/run-v120f.sh' not in ci)
check('CI keeps V1.19 compatibility without exact V1.19 final gate', 'bash tests/run-v119.sh' in ci and 'bash tests/run-v119c.sh' in ci and 'bash tests/run-v119d.sh' in ci and 'bash tests/run-v119f.sh' not in ci)
check('V1.20 release workflow builds and uploads final runtime/complete packages', 'V1.20.0 Release Gate' in release_workflow and 'bash tests/run-v120g.sh' in release_workflow and 'build_release_package.py --mode final' in release_workflow and 'rss-reader-modernization-1.20.0-complete.zip' in release_workflow)
for rel in [
    'docs/v1-20-g-final-release.md', 'docs/v1-20-g-production-checklist.md',
    'docs/v1-20-g-updated-files.md', 'APPLY_NOTE_V1_20_G.md',
    'CHECKLIST_FOR_USER_V1_20_G.md', 'UPDATED_FILES_V1_20_G.md',
    'docs/test-report-v1-20-g.md', 'V1_20_G_TEST_REPORT.md',
]:
    check(f'V1.20-G documentation exists: {rel}', (ROOT / rel).is_file())

failed = [name for name, ok in checks if not ok]
for name, ok in checks:
    print(f"[{'PASS' if ok else 'FAIL'}] {name}")
print(f'RESULT: PASS {len(checks) - len(failed)} / FAIL {len(failed)} / SKIP 0')
raise SystemExit(1 if failed else 0)
