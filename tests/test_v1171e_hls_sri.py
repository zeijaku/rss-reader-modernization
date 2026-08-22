#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
streaming = (ROOT / 'public/js/camera-video-streaming.js').read_text(encoding='utf-8')
calendar = (ROOT / 'public/js/calendar.js').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
revision_match = re.search(r"const APP_ASSET_REVISION = '([^']+)';", version)
active_revision = revision_match.group(1) if revision_match else ''

checks = {
    'hls.js stays pinned to 1.6.16': "HLS_LIBRARY_VERSION = '1.6.16'" in streaming and 'hls.js@1.6.16/dist/hls.min.js' in streaming,
    'rejected SRI from V1.17.0 is removed': 'sha384-iZBI1/lW9u8FcBjxuQ8nPTsU7TXhZNtzkV8H3gQHSTgz+VYQoKWqGlBHqhO84alJ' not in streaming,
    'browser-computed SHA-384 is installed': 'sha384-5E8B0pTlZZJMabWpC0fyYf6OUpe15jJij34BqBAh4NXoHAlLNOjCPRrwtOXOQFAn' in streaming,
    'anonymous CORS remains enabled for SRI': "script.crossOrigin = 'anonymous'" in streaming,
    'stable loader refreshes streaming module': './js/camera-video-streaming.js?v=' + active_revision in calendar,
    'streaming fallback stylesheet uses stable cache key': './css/camera-video-streaming.css?v=' + active_revision in streaming,
}

failed = [name for name, ok in checks.items() if not ok]
for name, ok in checks.items():
    print(('PASS' if ok else 'FAIL') + ': ' + name)
print(f"RESULT: PASS {len(checks)-len(failed)} / FAIL {len(failed)} / SKIP 0")
sys.exit(1 if failed else 0)
