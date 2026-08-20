from pathlib import Path
import json
import re
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


def extract_js_function(source: str, name: str) -> str:
    marker = f'function {name}('
    start = source.find(marker)
    if start < 0:
        raise ValueError(f'function not found: {name}')
    brace = source.find('{', start)
    if brace < 0:
        raise ValueError(f'function brace not found: {name}')
    depth = 0
    for index in range(brace, len(source)):
        char = source[index]
        if char == '{':
            depth += 1
        elif char == '}':
            depth -= 1
            if depth == 0:
                return source[start:index + 1]
    raise ValueError(f'unclosed function: {name}')


bootstrap = (ROOT / 'app/bootstrap.php').read_text(encoding='utf-8')
api = (ROOT / 'app/api.php').read_text(encoding='utf-8')
widgets = (ROOT / 'app/dashboard_widget.php').read_text(encoding='utf-8')
information = (ROOT / 'app/information_widget.php').read_text(encoding='utf-8')
module = (ROOT / 'app/health_probe.php').read_text(encoding='utf-8')
probe = (ROOT / 'public/connection_probe.php').read_text(encoding='utf-8')
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
client = (ROOT / 'public/js/connection-monitor.js').read_text(encoding='utf-8')

# Preserve the V1.18-B foundation.
check("'health_probe'" in widgets, 'dashboard widget type health_probe remains registered')
check("'health_probe'" in information, 'Information Widget allowlist still contains health_probe')
check("require_once __DIR__ . '/health_probe.php';" in bootstrap, 'bootstrap still loads health_probe module')
for action in ['widget.healthprobe.create', 'widget.healthprobe.update', 'widget.healthprobe.delete']:
    check(action in api, f'health_probe API action remains routed: {action}')
check("app_asset_url('js/connection-monitor.js')" in index, 'Dashboard still loads connection-monitor.js')
check("./connection_probe.php" in client, 'client still uses the same-origin lightweight probe')
check('probeIntervalMs = 5000' in client and 'probePending' in client, 'five-second shared non-overlapping probe remains')
check('document.hidden' in client and 'visibilitychange' in client, 'background probe pause remains')
check('http_response_code(204)' in probe and 'Cache-Control: no-store' in probe, 'probe remains a no-store 204 endpoint')
check('conn_db' not in probe and 'curl_' not in probe, 'probe remains DB-free and outbound-free')

# V1.18-C history contract.
check('historyRetentionMs = 300000' in client, 'history retention is five minutes')
check('historyMaxSamples = 120' in client, 'history has a hard sample cap')
check('defaultHistoryWindowMs = 60000' in client, 'default visible history is 60 seconds')
for value in ['30000', '60000', '300000']:
    check(value in client, f'history window is present: {value} ms')
check('probeHistory.push' in client and 'trimHistory(sampledAt)' in client, 'terminal probe results are recorded and pruned')
check('recordProbeResult(result);' in client, 'published probe results feed the history before rendering')
check('localStorage' not in client and 'sessionStorage' not in client, 'history is not persisted in browser storage')
check('createElementNS' in client and "'polyline'" in client and "'circle'" in client,
      'history graph is browser-native inline SVG')
check('Chart.' not in client and 'new Chart' not in client and 'canvas.getContext' not in client,
      'no graph library/canvas dependency was introduced')
check("historyStat('Avg'" in client and "historyStat('Max'" in client and "historyStat('Jitter'" in client,
      'Avg / Max / Jitter are rendered')
check('Math.abs(latency - previousLatency)' in client, 'Jitter uses absolute consecutive RTT deltas')
check('previousLatency = null' in client, 'failed/non-online samples break the Jitter sequence')
check('continuityLimitMs = probeIntervalMs * 2.5' in client, 'long sampling gaps break graph/Jitter continuity')
check("sample.state === 'online'" in client, 'statistics use successful latency samples')
check('health-probe-window-trigger' in client and 'data-health-probe-window-ms' in client,
      'per-card 30s/60s/5m history controls are wired')
check(".data('health-probe-history-window', defaultHistoryWindowMs)" in client,
      'each card has its own non-persistent history window state')
check('renderHistory($card)' in client, 'history section is integrated into card rendering')
check('DBへ保存しません' in client, 'modal explains that latency history is not stored in DB')

# Execute the actual pure helper functions extracted from the shipped JS.
try:
    functions = '\n'.join(extract_js_function(client, name) for name in [
        'normalizeHistoryWindow', 'trimHistory', 'recordProbeResult', 'latencyMetrics', 'chartScaleMaximum'
    ])
    node_script = f"""
const defaultHistoryWindowMs = 60000;
const probeIntervalMs = 5000;
let historyRetentionMs = 300000;
let historyMaxSamples = 120;
let probeHistory = [];
{functions}
function assert(c,m){{if(!c){{throw new Error(m);}}}}
assert(normalizeHistoryWindow(30000)===30000,'30s');
assert(normalizeHistoryWindow(60000)===60000,'60s');
assert(normalizeHistoryWindow(300000)===300000,'5m');
assert(normalizeHistoryWindow(123)===60000,'default');
const t0=1000000;
const m=latencyMetrics([
  {{state:'online',latencyMs:20,sampledAt:t0}},{{state:'online',latencyMs:30,sampledAt:t0+5000}},
  {{state:'offline',latencyMs:null,sampledAt:t0+10000}},
  {{state:'online',latencyMs:50,sampledAt:t0+15000}},{{state:'online',latencyMs:70,sampledAt:t0+20000}}
]);
assert(m.count===4,'count');
assert(Math.abs(m.average-42.5)<0.0001,'avg');
assert(m.maximum===70,'max');
assert(Math.abs(m.jitter-15)<0.0001,'jitter gap reset');
const longGap=latencyMetrics([
  {{state:'online',latencyMs:20,sampledAt:t0}},
  {{state:'online',latencyMs:100,sampledAt:t0+20000}}
]);
assert(longGap.jitter===null,'long gap jitter reset');
assert(chartScaleMaximum([{{state:'online',latencyMs:80}}])===100,'minimum scale');
assert(chartScaleMaximum([{{state:'online',latencyMs:200}}])===250,'dynamic scale');
const base=Date.now();
for(let i=0;i<130;i++){{recordProbeResult({{state:'online',latencyMs:10+i,checkedAt:new Date(base-129000+i*1000),httpStatus:204}});}}
assert(probeHistory.length===120,'sample cap');
recordProbeResult({{state:'online',latencyMs:15,checkedAt:new Date(base+301000),httpStatus:204}});
assert(probeHistory.length===1,'five minute prune');
console.log('OK');
"""
    with tempfile.NamedTemporaryFile('w', suffix='.js', encoding='utf-8', delete=False) as handle:
        handle.write(node_script)
        temp_name = handle.name
    completed = subprocess.run(['node', temp_name], capture_output=True, text=True, timeout=10)
    check(completed.returncode == 0 and completed.stdout.strip() == 'OK',
          'actual JavaScript metrics/history helpers pass known-value execution tests')
except Exception as exc:
    check(False, f'JavaScript helper execution test raised: {exc}')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
