from pathlib import Path
import sys

root = Path(__file__).resolve().parents[1]
base_js = (root / 'public/js/camera-video.js').read_text(encoding='utf-8')
playback_js = (root / 'public/js/camera-video-playback.js').read_text(encoding='utf-8')
playback_css = (root / 'public/css/camera-video-playback.css').read_text(encoding='utf-8')
calendar_js = (root / 'public/js/calendar.js').read_text(encoding='utf-8')

checks = {
    'Playback stays isolated from Snapshot module': 'camera-video-playback.js' not in base_js and 'camera-video-playback.js?v=1.17-d' in calendar_js,
    'YouTube URL parser accepts explicit known hosts only': all(host in playback_js for host in ['www.youtube.com', 'm.youtube.com', 'youtu.be', 'www.youtube-nocookie.com']),
    'YouTube URL parser supports watch/live/embed/shorts': all(token in playback_js for token in ["'/watch'", "['live', 'embed', 'shorts']", "searchParams.get('v')"]),
    'YouTube Video ID is restricted to eleven safe characters': '/^[A-Za-z0-9_-]{11}$/' in playback_js,
    'YouTube iframe src is generated from Video ID': "'https://www.youtube.com/embed/'" in playback_js and 'encodeURIComponent(videoId)' in playback_js,
    'YouTube does not auto play': 'autoplay=1' not in playback_js and "autoplay: 'autoplay'" not in playback_js,
    'YouTube keeps standard controls and fullscreen': "allowfullscreen: ''" in playback_js and 'YouTube標準Player' in playback_js,
    'YouTube keeps a referrer for current embedding requirements': "referrerpolicy: 'strict-origin-when-cross-origin'" in playback_js,
    'Direct Video uses native controls': "document.createElement('video')" in playback_js and "controls: 'controls'" in playback_js,
    'Direct Video is mobile inline capable': "playsinline: 'playsinline'" in playback_js,
    'Direct Video avoids eager full download': "preload: 'metadata'" in playback_js,
    'Direct Video has browser error fallbacks': all(text in playback_js for text in ['NetworkまたはMedia URL', 'Codec', '動画形式またはCodec']),
    'MPEG support is presented as browser dependent': 'MPEG等はBrowserやCodecによって再生出来ない場合があります' in playback_js,
    'Playback adds no server-side proxy or extra API request': 'apiRequest(' not in playback_js and '$.ajax' not in playback_js and 'fetch(' not in playback_js,
    'Playback is observed after asynchronous widget insertion': 'MutationObserver' in playback_js and 'camera-video-card[data-camera-render-type="youtube"]' in playback_js,
    'Player viewport keeps YouTube minimum height': 'min-height: 200px;' in playback_css,
    'Player keeps 16:9 presentation': 'aspect-ratio: 16 / 9;' in playback_css,
    'Height 2 expands playback': '[data-widget-height="2"] .camera-video-youtube-frame' in playback_css,
    'No npm dependency is introduced for playback': not (root / 'package.json').exists(),
}

failed = [name for name, passed in checks.items() if not passed]
for name, passed in checks.items():
    print(('PASS' if passed else 'FAIL') + ': ' + name)
if failed:
    sys.exit(1)
print(f'PASS: all {len(checks)} V1.17-D YouTube / Video checks passed')
