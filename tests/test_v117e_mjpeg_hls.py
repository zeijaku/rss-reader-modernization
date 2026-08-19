from pathlib import Path
import sys

root = Path(__file__).resolve().parents[1]
streaming = (root / 'public/js/camera-video-streaming.js').read_text(encoding='utf-8')
css = (root / 'public/css/camera-video-streaming.css').read_text(encoding='utf-8')
calendar = (root / 'public/js/calendar.js').read_text(encoding='utf-8')
notices = (root / 'THIRD_PARTY_NOTICES.md').read_text(encoding='utf-8')
license_text = (root / 'licenses/hls.js-1.6.16-Apache-2.0.txt').read_text(encoding='utf-8')

checks = {
    'MJPEG uses an image element': " $('<img>')" in streaming and 'camera-video-mjpeg-image' in streaming,
    'MJPEG reconnect is available': 'camera-video-mjpeg-reconnect' in streaming and 'reconnectMjpeg' in streaming,
    'MJPEG is not treated as a normal video player': "renderType === 'mjpeg'" in streaming and '通常のVideo Playerの再生・シーク操作はありません' in streaming,
    'HLS uses browser native video controls': "document.createElement('video')" in streaming and "controls: 'controls'" in streaming and "playsinline: 'playsinline'" in streaming,
    'HLS autoplay remains disabled': 'autoplay' not in streaming.lower(),
    'hls.js is pinned to 1.6.16': "HLS_LIBRARY_VERSION = '1.6.16'" in streaming and 'hls.js@1.6.16/dist/hls.min.js' in streaming,
    'hls.js has corrected SRI and anonymous CORS mode': 'sha384-5E8B0pTLZZJMabWpCOfyYf60UpeI5jJij34BqBAh4NXoHALLNOjCPRrwtOX0QFAn' in streaming and "script.crossOrigin = 'anonymous'" in streaming,
    'rejected V1.17.0 SRI is removed': 'sha384-iZBI1/lW9u8FcBjxuQ8nPTsU7TXhZNtzkV8H3gQHSTgz+VYQoKWqGlBHqhO84alJ' not in streaming,
    'hls.js is lazy-loaded only by the streaming module': "document.createElement('script')" in streaming and 'data-camera-hls-library' in streaming,
    'hls.js support check precedes native fallback in HLS setup': streaming.find('Hls.isSupported()') < streaming.find("if (!useNativeHls($card, video, mediaUrl, $status))"),
    'HLS source is attached through hls.js API': 'hls.loadSource(mediaUrl)' in streaming and 'hls.attachMedia(video)' in streaming,
    'HLS fatal network and media recovery are bounded': 'networkRecovery < 1' in streaming and 'mediaRecovery < 1' in streaming and 'recoverMediaError()' in streaming,
    'Native HLS fallback is available': 'application/vnd.apple.mpegurl' in streaming and 'nativeHlsSupported' in streaming,
    'HLS CORS limitation is visible to the user': 'CORS' in streaming and 'PlaylistとSegment' in streaming,
    'Streaming module performs no server proxy request': 'api_v1.php' not in streaming and 'app_safe_http_fetch' not in streaming,
    'Streaming CSS remains responsive': 'object-fit: contain' in css and 'max-height: 70vh' in css,
    'Height 2 has a larger streaming area': '[data-widget-height="2"] .camera-video-streaming-stage' in css,
    'Calendar loader keeps the stable Version 1.17.1 streaming module': './js/camera-video-streaming.js?v=1.17.1' in calendar,
    'hls.js notice is documented': '| hls.js | 1.6.16 | Apache-2.0 |' in notices and 'Subresource Integrity' in notices,
    'hls.js upstream license copy is retained': 'Licensed under the Apache License, Version 2.0' in license_text and 'Copyright (c) 2017 Dailymotion' in license_text,
    'No npm runtime dependency is introduced': not (root / 'package.json').exists() and not (root / 'node_modules').exists(),
}

failed = [name for name, passed in checks.items() if not passed]
for name, passed in checks.items():
    print(('PASS' if passed else 'FAIL') + ': ' + name)
if failed:
    sys.exit(1)
print(f'PASS: all {len(checks)} V1.17-E MJPEG / HLS checks passed')
