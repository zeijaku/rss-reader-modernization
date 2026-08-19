from pathlib import Path
import sys

root = Path(__file__).resolve().parents[1]
js = (root / 'public/js/camera-video.js').read_text(encoding='utf-8')
css = (root / 'public/css/camera-video.css').read_text(encoding='utf-8')
php = (root / 'app/camera_video.php').read_text(encoding='utf-8')
calendar = (root / 'public/js/calendar.js').read_text(encoding='utf-8')

checks = {
    'Snapshot uses browser image loading': 'new window.Image()' in js and 'camera-video-snapshot-image' in js,
    'Snapshot cache busting is explicit': 'cacheBustSnapshotUrl' in js and "'_rss_snapshot'" in js,
    'Snapshot does not add server-side fetching': 'app_safe_http_fetch(' not in php and 'curl_' not in php,
    'Manual refresh control is present': 'camera-video-refresh-trigger' in js and '今すぐ更新' in js,
    'Automatic refresh uses a non-overlapping timer': 'window.setTimeout' in js and 'scheduleSnapshotRefresh' in js,
    'Configured refresh values remain available': all(value in js for value in ["['0','OFF']", "['10','10秒']", "['30','30秒']", "['60','1分']", "['300','5分']", "['600','10分']"]),
    'Initial loading and failure states are visible': '読み込み待ち' in js and '画像を読み込めませんでした' in js and 'camera-video-snapshot-spinner' in css,
    'Last successful update is displayed': 'camera-video-last-updated' in js and '最終更新:' in js,
    'Auto mode still distinguishes later renderer families': all(token in js for token in ['youtube.com', 'youtu.be', '.m3u8', 'mjpeg']),
    'Explicit Snapshot rendering remains source-type driven': "if (renderType === 'snapshot')" in js and 'buildSnapshotStage' in js,
    'Ambiguous Auto URLs are not required to fall back to Snapshot': "return 'unknown';" in js,
    'Core Snapshot module still avoids iframe rendering': "'<iframe>'" not in js and "createElement('iframe')" not in js,
    'Core Snapshot module still avoids video element rendering': "'<video>'" not in js and "createElement('video')" not in js,
    'Snapshot stage preserves responsive width': 'width: 100%;' in css and 'object-fit: contain;' in css,
    'Height 2 receives a larger Snapshot stage': '[data-widget-height="2"] .camera-video-snapshot-stage' in css,
    'Camera asset markers remain Version 1.17 cache-busted': './js/camera-video.js?v=1.17-' in calendar and './css/camera-video.css?v=1.17-' in js,
    'HTTP-on-HTTPS mixed-content risk is surfaced': "window.location.protocol === 'https:'" in js and 'HTTP画像' in js,
    'Background tabs skip automatic image refresh': 'document.hidden' in js,
}

failed = [name for name, passed in checks.items() if not passed]
for name, passed in checks.items():
    print(('PASS' if passed else 'FAIL') + ': ' + name)
if failed:
    sys.exit(1)
print(f'PASS: all {len(checks)} V1.17-C Snapshot Camera checks passed')
