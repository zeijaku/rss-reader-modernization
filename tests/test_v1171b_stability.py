#!/usr/bin/env python3
from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--overlay-only', action='store_true', help='skip unchanged baseline dependency files when validating an overlay package')
args = parser.parse_args()

checks: list[tuple[str, bool]] = []


def check(name: str, condition: bool) -> None:
    checks.append((name, bool(condition)))


def text(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')

version = text('app/version.php')
calendar = text('public/js/calendar.js')
camera_watchdog = text('public/js/camera-video-watchdog.js')
mail_watchdog = text('public/js/mail-widget-watchdog.js')

version_match = re.search(r"const APP_VERSION = '(\d+)\.(\d+)\.(\d+)(?:-[^']+)?';", version)
version_tuple = tuple(int(part) for part in version_match.groups()) if version_match else (0, 0, 0)
check('visible APP_VERSION keeps V1.17.1-B-or-later behavior', version_tuple >= (1, 17, 1))
revision_match = re.search(r"const APP_ASSET_REVISION = '([^']+)';", version)
active_revision = revision_match.group(1) if revision_match else ''
revision_prefix = re.match(r'^(\d+)\.(\d+)\.(\d+)', active_revision)
revision_tuple = tuple(int(part) for part in revision_prefix.groups()) if revision_prefix else (0, 0, 0)
check('V1.17.1-B-or-later asset revision is active', revision_tuple >= (1, 17, 1))

for path in [
    './css/mail-widget.css',
    './css/camera-video.css',
    './css/camera-video-playback.css',
    './css/camera-video-streaming.css',
]:
    asset = path + '?v=' + active_revision
    check(f'calendar loader stages style {asset}', f"loadStyle('{asset}'," in calendar)

for path in [
    './js/app-notice.js',
    './js/mail-widget-watchdog.js',
    './js/camera-video-watchdog.js',
    './js/mail-widget.js',
    './js/camera-video.js',
    './js/camera-video-playback.js',
    './js/camera-video-streaming.js',
]:
    asset = path + '?v=' + active_revision
    check(f'calendar loader stages script {asset}', f"loadScript('{asset}');" in calendar)

mail_watchdog_pos = calendar.find('./js/mail-widget-watchdog.js?v=' + active_revision)
mail_feature_pos = calendar.find('./js/mail-widget.js?v=' + active_revision)
camera_watchdog_pos = calendar.find('./js/camera-video-watchdog.js?v=' + active_revision)
camera_feature_pos = calendar.find('./js/camera-video.js?v=' + active_revision)
check('Mail watchdog loads before Mail feature startup', 0 <= mail_watchdog_pos < mail_feature_pos)
check('Camera watchdog loads before Camera feature startup', 0 <= camera_watchdog_pos < camera_feature_pos)

check('Snapshot watchdog is bounded at 12 seconds', 'SNAPSHOT_TIMEOUT_MS = 12000' in camera_watchdog)
check('Snapshot timeout releases the existing in-flight guard', "$card.data('camera-video-snapshot-loading', false)" in camera_watchdog)
check('Snapshot timeout re-enables the existing refresh button', "$button.prop('disabled', false)" in camera_watchdog)
check('Snapshot timeout exposes a retryable message', '今すぐ更新で再試行できます' in camera_watchdog)
check('Video metadata watchdog is bounded', 'VIDEO_TIMEOUT_MS = 15000' in camera_watchdog)
check('Video timeout adds a user retry control', 'camera-video-video-retry' in camera_watchdog and '再読み込み' in camera_watchdog)
check('Video retry reuses the browser media load path', 'video.load();' in camera_watchdog)
check('MJPEG watchdog is bounded', 'MJPEG_TIMEOUT_MS = 12000' in camera_watchdog)
check('MJPEG timeout keeps the stream alive and points to reconnect', '映像が表示されない場合は再接続してください' in camera_watchdog)
check('MJPEG reconnect restarts watchdog timing', "'.camera-video-mjpeg-reconnect'" in camera_watchdog and 'startMjpegTimer($card, image)' in camera_watchdog)
check('Camera watchdog cleans timers on removed cards/unload', 'cleanup(node)' in camera_watchdog and 'beforeunload.cameraVideoWatchdog' in camera_watchdog)

check('Mail watchdog timeout is longer than feature 12s XHR', 'BUSY_TIMEOUT_MS = 13500' in mail_watchdog)
check('Mail watchdog observes completed Mail API requests', 'ajaxComplete.mailWidgetWatchdog' in mail_watchdog and "action.indexOf('mail.')" in mail_watchdog)
check('Mail card recovery clears aria-busy', "$card.attr('aria-busy', 'false')" in mail_watchdog)
check('Mail card recovery stops refresh spinner', "removeClass('fa-spin')" in mail_watchdog)
check('Mail card recovery replaces a stuck loading state', "addClass('mail-error')" in mail_watchdog)
check('Mail body recovery exits stuck loading state', "attr('data-mail-body-state', 'error')" in mail_watchdog)
check('Mail body recovery re-enables its toggle', "$toggle.prop('disabled', false)" in mail_watchdog)
check('Mail watchdog cleans busy timers on removal/unload', 'clearBusyTimer($node)' in mail_watchdog and 'beforeunload.mailWidgetWatchdog' in mail_watchdog)

if not args.overlay_only:
    dependency_files = {
        'public/js/camera-video.js': [
            'camera-video-snapshot-loading',
            'camera-video-refresh-trigger',
            'data-camera-render-type',
        ],
        'public/js/camera-video-playback.js': [
            'camera-video-file-player',
            'camera-video-playback-status',
        ],
        'public/js/camera-video-streaming.js': [
            'camera-video-mjpeg-image',
            'camera-video-mjpeg-reconnect',
            'camera-video-streaming-status',
        ],
        'public/js/mail-widget.js': [
            'data-dashboard-widget-type',
            'mail-widget-refresh',
            'mail-loading',
            'aria-busy',
            'mail-message-body',
            'mail-message-toggle',
        ],
    }
    for path, needles in dependency_files.items():
        source_path = ROOT / path
        check(f'baseline dependency exists: {path}', source_path.is_file())
        if not source_path.is_file():
            continue
        source = source_path.read_text(encoding='utf-8')
        for needle in needles:
            check(f'{path} retains watchdog contract: {needle}', needle in source)
else:
    check('overlay-only validation explicitly selected', True)

failed = [name for name, ok in checks if not ok]
for name, ok in checks:
    print(f"[{'PASS' if ok else 'FAIL'}] {name}")

print(f'RESULT: PASS {len(checks) - len(failed)} / FAIL {len(failed)} / SKIP 0')
if failed:
    sys.exit(1)
