#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
JS = (ROOT / 'public/js/connection-monitor.js').read_text(encoding='utf-8')
PROBE = (ROOT / 'public/connection_probe.php').read_text(encoding='utf-8')
DOC = (ROOT / 'docs/V1.18-F_DESIGN_DECISION.md').read_text(encoding='utf-8')

checks = []

def check(name, condition):
    checks.append((name, bool(condition)))

# Scope / direct request target.
check('same-origin probe URL exists', "url: './connection_probe.php'" in JS)
check('route label remains Browser to RSS Reader', 'Browser → RSS Reader' in JS or 'Browser -> RSS Reader' in JS)
check('probe uses lightweight GET', "method: 'GET'" in JS)
check('probe interval remains 5 seconds', 'probeIntervalMs = 5000' in JS)
check('probe timeout remains 4 seconds', 'probeTimeoutMs = 4000' in JS)
check('pending overlap guard remains', 'probePending' in JS)
check('hidden-tab pause remains', 'document.hidden' in JS and 'visibilitychange' in JS)

# No arbitrary/external probe controls or targets.
check('no arbitrary probe_url input', 'probe_url' not in JS.lower())
check('no target_url input', 'target_url' not in JS.lower())
check('no endpoint URL config input', 'endpoint_url' not in JS.lower())
check('no Google fixed target', 'google.com' not in JS.lower())
check('no Cloudflare fixed target', 'cloudflare.com' not in JS.lower())
check('no Fast.com target', 'fast.com' not in JS.lower())
check('no public DNS IP target 8.8.8.8', '8.8.8.8' not in JS)
check('no public DNS IP target 1.1.1.1', '1.1.1.1' not in JS)

# SVG namespace is the only literal http(s) URL allowed in the JS.
urls = re.findall(r"https?://[^'\"\s)]+", JS, flags=re.I)
allowed = [u for u in urls if u.startswith('http://www.w3.org/2000/svg')]
check('no external HTTP URL literal in monitor JS', len(urls) == len(allowed))

# No speed/throughput implementation.
lower_js = JS.lower()
check('no speedtest implementation keyword', 'speedtest' not in lower_js)
check('no throughput implementation keyword', 'throughput' not in lower_js)
check('no download-size control', 'download_size' not in lower_js and 'downloadsize' not in lower_js)
check('no blob payload generation', 'new blob(' not in lower_js)
check('no arraybuffer payload generation', 'arraybuffer' not in lower_js)
check('no manual speed test UI', 'speed test' not in lower_js and 'speed test' not in JS)

# No client/local address discovery.
check('no RTCPeerConnection local-IP discovery', 'rtcpeerconnection' not in lower_js)
check('no enumerateDevices discovery', 'enumeratedevices' not in lower_js)
check('no getUserMedia discovery', 'getusermedia' not in lower_js)

# Probe endpoint remains isolated/lightweight.
lower_probe = PROBE.lower()
check('probe endpoint GET only', "request_method" in lower_probe and "'get'" in lower_probe)
check('probe endpoint returns 204', 'http_response_code(204)' in PROBE)
check('probe endpoint no bootstrap require', 'bootstrap.php' not in lower_probe and 'require_once' not in lower_probe and 'include_once' not in lower_probe)
check('probe endpoint no DB access', 'conn_db' not in lower_probe and 'pdo' not in lower_probe)
check('probe endpoint no session', 'session_start' not in lower_probe and 'app_session' not in lower_probe)
check('probe endpoint no outbound URL', not re.search(r'https?://', PROBE, flags=re.I))
check('probe endpoint no-store', 'no-store' in lower_probe)

# Decision is explicitly documented.
lower_doc = DOC.lower()
check('F doc rejects external probe for V1.18', 'will **not** add an external internet reachability probe' in lower_doc)
check('F doc rejects speed test for V1.18', 'or a speed/throughput test' in lower_doc)
check('F doc keeps same-origin scope', 'same-origin' in lower_doc)
check('F doc defers full regression to G', 'v1.18-g' in lower_doc or 'release gate' in lower_doc)

failed = [name for name, ok in checks if not ok]
for name, ok in checks:
    print(('PASS' if ok else 'FAIL') + ': ' + name)
print(f'\nPASS {len(checks)-len(failed)} / FAIL {len(failed)} / TOTAL {len(checks)}')
if failed:
    sys.exit(1)
