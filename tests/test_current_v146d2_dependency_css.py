#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import hashlib
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def text(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


streaming = text('public/js/camera-video-streaming.js')
calendar = text('public/js/calendar.js')
calendar_polish = text('public/css/calendar-polish.css')
drawer = text('public/js/drawer-categories.js')
drawer_css = text('public/css/drawer-catalog.css')
deps = text('docs/dependencies.md')
notices = text('THIRD_PARTY_NOTICES.md')
license_text = text('licenses/hls.js-1.7.3-Apache-2.0.txt')
version = text('app/version.php')
hls_path = ROOT / 'public/js/hls-1.7.3.min.js'
hash_record = text('docs/hls.js-1.7.3-sha256.txt').strip()

hls_bytes = hls_path.read_bytes()
hls_sha256 = hashlib.sha256(hls_bytes).hexdigest()

check(len(hls_bytes) == 619692, 'vendored hls.js size matches official v1.7.3 release artifact')
check(hls_sha256 == 'a12e7ee1cd64a69dcdb314157e45dafcba705bfb0b1440b7935cb265d374423e',
      'vendored hls.js SHA-256 matches the verified v1.7.3 release artifact')
check(hash_record == hls_sha256 + '  public/js/hls-1.7.3.min.js',
      'recorded hls.js artifact SHA-256 matches the vendored file')

check("HLS_LIBRARY_VERSION = '1.7.3'" in streaming, 'HLS loader pins v1.7.3')
check("HLS_LIBRARY_PATH = './js/hls-1.7.3.min.js'" in streaming, 'HLS loader points to the local vendored asset')
check('script.src = assetUrl(HLS_LIBRARY_PATH)' in streaming,
      'HLS loader propagates the application asset revision to the local library')
check('cdn.jsdelivr.net' not in streaming and 'https://cdn.' not in streaming,
      'HLS runtime loader has no executable JavaScript CDN dependency')
check('HLS_LIBRARY_INTEGRITY' not in streaming and 'script.integrity' not in streaming
      and 'script.crossOrigin' not in streaming and 'script.referrerPolicy' not in streaming,
      'obsolete cross-origin SRI loader attributes are removed after same-origin vendoring')
check("document.createElement('script')" in streaming and 'hlsLibraryPromise' in streaming,
      'hls.js remains lazy-loaded instead of becoming a page-wide static dependency')
check("String(window.Hls.version || '') === HLS_LIBRARY_VERSION" in streaming
      and "reject(new Error('Unexpected hls.js version.'))" in streaming,
      'loader keeps an exact runtime-version guard')
check('hlsLibraryPromise = null; throw error;' in streaming,
      'failed lazy load clears the cached promise so a later retry remains possible')

for token in [
    'Hls.isSupported()', 'new Hls({enableWorker: true})',
    'Hls.Events.MANIFEST_PARSED', 'Hls.Events.ERROR',
    'Hls.ErrorTypes.NETWORK_ERROR', 'Hls.ErrorTypes.MEDIA_ERROR',
    'hls.loadSource(mediaUrl)', 'hls.attachMedia(video)',
    'hls.startLoad()', 'hls.recoverMediaError()', 'hls.destroy()',
]:
    check(token in streaming, 'existing HLS playback/recovery contract remains: ' + token)

check('autoplay' not in streaming.lower(), 'HLS playback still does not force autoplay')
check('application/vnd.apple.mpegurl' in streaming and 'nativeHlsSupported' in streaming,
      'Native HLS fallback remains available')
check('api_v1.php' not in streaming and 'app_safe_http_fetch' not in streaming,
      'HLS media remains Browser-direct and is not proxied through the application server')

check('| hls.js | 1.7.3 |' in deps and 'public/js/hls-1.7.3.min.js' in deps,
      'dependency inventory documents local hls.js 1.7.3')
check('| hls.js | 1.7.3 | Apache-2.0 |' in notices
      and 'licenses/hls.js-1.7.3-Apache-2.0.txt' in notices,
      'third-party notice matches hls.js 1.7.3 and its license copy')
check('Licensed under the Apache License, Version 2.0' in license_text
      and 'Copyright (c) 2017 Dailymotion' in license_text,
      'upstream hls.js Apache-2.0 license text is retained')

check("assetUrl('./css/calendar-polish.css')" in calendar,
      'Calendar loads the consolidated polish stylesheet through the current asset revision')
check('calendar-polish-r3.css' not in calendar and 'data-calendar-polish-r3-style' not in calendar,
      'Calendar no longer requests the former R3 patch stylesheet')
check(not (ROOT / 'public/css/calendar-polish-r3.css').exists(),
      'obsolete Calendar R3 patch stylesheet is removed from Current source')
check('.calendar-upcoming-item[hidden]' in calendar_polish
      and '.calendar-upcoming-toggle-wrap' in calendar_polish
      and '[data-calendar-height-held="1"]' in calendar_polish,
      'Calendar compact-upcoming and height-hold selectors remain after consolidation')

check('injectVisualStyles' not in drawer and 'injectMobileStyles' not in drawer,
      'Drawer no longer injects separate version-named visual/mobile stylesheets')
check('drawer-v121b.css' not in drawer and 'drawer-v121c.css' not in drawer,
      'Drawer runtime has no references to the former version-named CSS files')
check("link.href = './css/drawer-catalog.css'" in drawer and 'assetRevision' in drawer,
      'Drawer loads one consolidated stylesheet with the active asset revision')
check(not (ROOT / 'public/css/drawer-v121b.css').exists()
      and not (ROOT / 'public/css/drawer-v121c.css').exists(),
      'obsolete version-named Drawer patch stylesheets are removed from Current source')
b_pos = drawer_css.find('/* V1.21-B:')
c_pos = drawer_css.find('/* V1.21-C:')
catalog_pos = drawer_css.find('/* Scoped Drawer layout.')
check(0 <= b_pos < c_pos < catalog_pos,
      'Drawer cascade order remains visual layer -> mobile/touch layer -> catalog layer')
check('min-height: 44px' in drawer_css and 'env(safe-area-inset-top)' in drawer_css
      and '#drawerMenu .widget-catalog-toggle' in drawer_css,
      'Drawer touch targets, safe-area handling and catalog layout remain present')

app_version = re.search(r"const APP_VERSION = '([^']+)';", version)
asset_revision = re.search(r"const APP_ASSET_REVISION = '([^']+)';", version)
check(bool(app_version and app_version.group(1) == '1.46.0'),
      'visible APP_VERSION is finalized at 1.46.0')
check(bool(asset_revision and asset_revision.group(1) == '1.46.0'),
      'formal V1.46 release uses the immutable 1.46.0 asset revision')
check(not (ROOT / 'licenses/hls.js-1.6.16-Apache-2.0.txt').exists(),
      'obsolete hls.js 1.6.16 license copy is removed')
check(not (ROOT / 'licenses/fontawesome-5.3.1-LICENSE.txt').exists(),
      'obsolete Font Awesome 5.3.1 license copy is removed')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
sys.exit(1 if failed else 0)
