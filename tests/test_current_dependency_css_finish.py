#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import hashlib
import re

ROOT = Path(__file__).resolve().parents[1]
PUBLIC = ROOT / 'public'
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


def text(rel: str) -> str:
    return (ROOT / rel).read_text(encoding='utf-8')


# hls.js 1.7.3: exact official release artifact, local lazy loading, and fallback contract.
hls_path = PUBLIC / 'js/hls-1.7.3.min.js'
hls_bytes = hls_path.read_bytes() if hls_path.is_file() else b''
streaming = text('public/js/camera-video-streaming.js')
calendar = text('public/js/calendar.js')
index = text('public/index.php')
deps = text('docs/dependencies.md')
notices = text('THIRD_PARTY_NOTICES.md')
license_path = ROOT / 'licenses/hls.js-1.7.3-Apache-2.0.txt'
license_bytes = license_path.read_bytes() if license_path.is_file() else b''

check(hls_path.is_file(), 'vendored hls.js 1.7.3 runtime file exists')
check(len(hls_bytes) == 619692, 'vendored hls.js artifact size matches official v1.7.3 release')
check(
    hashlib.sha256(hls_bytes).hexdigest() == 'a12e7ee1cd64a69dcdb314157e45dafcba705bfb0b1440b7935cb265d374423e',
    'vendored hls.js SHA-256 matches official v1.7.3 release artifact',
)
check(b'1.7.3' in hls_bytes, 'vendored hls.js artifact reports the expected version')
check("HLS_LIBRARY_VERSION = '1.7.3'" in streaming, 'HLS loader expects hls.js 1.7.3')
check("assetUrl('./js/hls-1.7.3.min.js')" in streaming, 'HLS loader uses the local versioned asset URL')
check('cdn.jsdelivr.net' not in streaming and 'unpkg.com' not in streaming, 'HLS executable runtime has no CDN URL')
check('HLS_LIBRARY_INTEGRITY' not in streaming and 'script.integrity' not in streaming,
      'obsolete cross-origin SRI loader contract is removed')
check("document.createElement('script')" in streaming and "data-camera-hls-library" in streaming,
      'hls.js remains lazy-loaded rather than becoming a page-global dependency')
check('hls-1.7.3.min.js' not in index, 'Dashboard does not eagerly load hls.js')
check('Hls.isSupported()' in streaming, 'MSE hls.js support detection is retained')
check('hls.loadSource(mediaUrl)' in streaming and 'hls.attachMedia(video)' in streaming,
      'existing HLS source/media attachment path is retained')
check('networkRecovery < 1' in streaming and 'hls.startLoad()' in streaming,
      'fatal network recovery remains bounded to one retry')
check('mediaRecovery < 1' in streaming and 'hls.recoverMediaError()' in streaming,
      'fatal media recovery remains bounded to one retry')
check('destroyHls' in streaming and 'hls.destroy()' in streaming, 'HLS teardown path is retained')
check('nativeHlsSupported' in streaming and 'application/vnd.apple.mpegurl' in streaming,
      'Native HLS fallback remains available')
check('autoplay' not in streaming.lower(), 'HLS playback still does not force autoplay')
check("loadScript(assetUrl('./js/camera-video-streaming.js'));" in calendar, 'Calendar loader still revision-loads Camera/HLS module')

check(license_path.is_file(), 'hls.js 1.7.3 Apache-2.0 license copy exists')
check(
    hashlib.sha256(license_bytes).hexdigest() == 'ca8773cf798c7ed997d4dd7c8e23c348699f8d5b7462636694cc14de6cda12db',
    'hls.js 1.7.3 license copy matches upstream tag',
)
check(b'Apache License' in license_bytes, 'hls.js license copy contains Apache License notice')
check(not (ROOT / 'licenses/hls.js-1.6.16-Apache-2.0.txt').exists(),
      'superseded hls.js 1.6.16 license copy is removed from current inventory')
check('| hls.js | 1.7.3 |' in deps and 'public/js/hls-1.7.3.min.js' in deps,
      'dependency inventory documents local hls.js 1.7.3')
check('| hls.js | 1.7.3 | Apache-2.0 |' in notices and
      'licenses/hls.js-1.7.3-Apache-2.0.txt' in notices,
      'third-party notice documents hls.js 1.7.3 and its license copy')
check('pinned jsDelivr runtime URL' not in notices and 'Subresource Integrity' not in notices,
      'current hls.js notice no longer describes the removed CDN/SRI runtime path')

# Drawer CSS: preserve cascade while eliminating historical phase-named runtime files.
drawer_js = text('public/js/drawer-categories.js')
drawer_css_path = PUBLIC / 'css/drawer.css'
drawer_css = drawer_css_path.read_text(encoding='utf-8') if drawer_css_path.is_file() else ''

check(drawer_css_path.is_file(), 'consolidated Drawer stylesheet exists')
for stale in ['drawer-v121b.css', 'drawer-v121c.css', 'drawer-catalog.css']:
    check(not (PUBLIC / 'css' / stale).exists(), f'superseded Drawer stylesheet is removed: {stale}')
    check(stale not in drawer_js, f'Drawer loader has no stale reference: {stale}')
check("./css/drawer.css" in drawer_js and "data-drawer-style" in drawer_js,
      'Drawer organizer loads one consolidated revision-aware stylesheet')
check('assetRevision' in drawer_js and "encodeURIComponent(assetRevision)" in drawer_js,
      'Drawer stylesheet uses the active application asset revision')
check('background-color: #f6f7f9' in drawer_css and '.drawer-item-current' in drawer_css,
      'Drawer visual hierarchy/current-item rules are retained')
check('@media (max-width: 575.98px)' in drawer_css and 'safe-area-inset-bottom' in drawer_css,
      'Drawer Smartphone/safe-area rules are retained')
check('#drawerMenu .widget-catalog-grid' in drawer_css and 'grid-template-columns' in drawer_css,
      'Drawer Widget Catalog grid rules are retained')
check('@media (prefers-reduced-motion: reduce)' in drawer_css,
      'Drawer reduced-motion rule is retained')

# Calendar CSS: retain R3 behavior while removing one historical cascade layer.
calendar_css_path = PUBLIC / 'css/calendar-polish.css'
calendar_css = calendar_css_path.read_text(encoding='utf-8') if calendar_css_path.is_file() else ''

check(calendar_css_path.is_file(), 'consolidated Calendar polish stylesheet exists')
check(not (PUBLIC / 'css/calendar-polish-r3.css').exists(),
      'superseded Calendar polish R3 stylesheet is removed')
check("assetUrl('./css/calendar-polish.css')" in calendar, 'Calendar loads the consolidated polish stylesheet')
check('calendar-polish-r3.css' not in calendar, 'Calendar loader has no stale R3 stylesheet request')
check('.calendar-upcoming-item[hidden]' in calendar_css and 'display: none !important;' in calendar_css,
      'R3 collapsed-upcoming layout behavior is retained after consolidation')
check('.calendar-days[data-calendar-height-held="1"]' in calendar_css,
      'R3 month-switch height stabilization rule is retained')
check("assetUrl('./css/calendar-deadline.css')" in calendar,
      'Calendar deadline CSS remains isolated rather than being over-consolidated')

# D2 remains a no-build-chain finishing change.
check(not (ROOT / 'package.json').exists(), 'D2 does not introduce an npm runtime/build dependency')
check(not (ROOT / 'node_modules').exists(), 'D2 does not vendor node_modules')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
