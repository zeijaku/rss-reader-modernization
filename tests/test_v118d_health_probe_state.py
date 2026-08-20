from pathlib import Path
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


client = (ROOT / 'public/js/connection-monitor.js').read_text(encoding='utf-8')
probe = (ROOT / 'public/connection_probe.php').read_text(encoding='utf-8')

# Preserve B/C behavior while adding D state semantics.
check('V1.18-D' in client, 'client source identifies the V1.18-D phase')
check("url: './connection_probe.php'" in client, 'same-origin probe endpoint remains unchanged')
check('probeIntervalMs = 5000' in client and 'probeTimeoutMs = 4000' in client,
      'five-second probe and four-second timeout remain')
check('probePending' in client and 'window.setTimeout(runProbe, probeIntervalMs)' in client,
      'probe remains non-overlapping and self-scheduled')
check('historyRetentionMs = 300000' in client and 'historyMaxSamples = 120' in client,
      'five-minute latency history remains bounded')
check("historyStat('Avg'" in client and "historyStat('Max'" in client and "historyStat('Jitter'" in client,
      'V1.18-C Avg / Max / Jitter remain rendered')
check('createElementNS' in client and "'polyline'" in client, 'inline SVG history remains')
check('localStorage' not in client and 'sessionStorage' not in client,
      'monitor history/state remains page-memory only')
check('http_response_code(204)' in probe and 'conn_db' not in probe and 'curl_' not in probe,
      'lightweight probe remains 204, DB-free and outbound-free')

# D outage confirmation and recovery.
check('offlineConfirmFailures = 2' in client, 'Offline requires two consecutive network failures')
check('pendingFailureAt' in client and 'outageStartedAt' in client and 'lastDisconnectAt' in client,
      'outage start and last disconnect state are tracked')
check('lastDowntimeMs' in client and 'recoveredAt' in client, 'recovery and last downtime are tracked')
check("output.state = 'checking'" in client and 'output.confirmingOffline = true' in client,
      'first network failure is a confirming state rather than immediate Offline')
check("output.state = 'offline'" in client and 'output.confirmedOffline = true' in client,
      'confirmed consecutive failures become Offline')
check('closeOutage(checkedMs)' in client, 'a reachable response closes a confirmed outage')
check('window.setInterval(updateDowntimeLabels, 1000)' in client,
      'confirmed downtime display updates once per second without extra probes')
check('clearDowntimeTimer()' in client and 'document.hidden' in client,
      'downtime UI ticker is stopped while the page is hidden')

# D quality and relative baseline.
for label in ['Excellent', 'Good', 'Fair', 'Slow', 'Offline']:
    check(label in client, f'connection quality label is available: {label}')
check('latency <= 79' in client and 'latency <= 149' in client and 'latency <= 299' in client,
      'quality thresholds are 79 / 149 / 299 ms')
check('values.length >= 5 ? median(values) : null' in client,
      'baseline waits for at least five successful samples')
check('latency > baseline * 2' in client and 'latency - baseline >= 50' in client,
      'relative slow threshold requires 2x baseline and at least +50 ms')
check('monitorState.relativeSlowStreak >= 2' in client,
      'relative slow notice requires two consecutive qualifying samples')
check('Connection Quality' in client and 'Baseline 学習中' in client,
      'quality and baseline are presented to the user')
check('Last Disconnect' in client and 'Last Downtime' in client and "'Downtime'" in client,
      'disconnect and downtime fields are rendered')
check('Recovered' in client and 'recoveryNoticeMs = 15000' in client,
      'recovery notice is transient for fifteen seconds')
check('通常より遅い' in client, 'relative baseline warning is rendered')
check('2回連続で到達出来ない場合にOffline' in client,
      'widget modal explains the Offline confirmation rule')

# Execute the actual shipped pure/state helper functions with known sequences.
try:
    names = [
        'roundedMetric', 'qualityFromLatency', 'median', 'baselineLatency',
        'materiallySlowerThanBaseline', 'currentDowntimeMs', 'resetPendingFailure',
        'closeOutage', 'monitorMetadata', 'applyConnectionState', 'formatDuration'
    ]
    functions = '\n'.join(extract_js_function(client, name) for name in names)
    node_script = f"""
const historyRetentionMs = 300000;
const offlineConfirmFailures = 2;
const recoveryNoticeMs = 15000;
let probeHistory = [];
let monitorState = {{
  consecutiveNetworkFailures:0,pendingFailureAt:null,outageStartedAt:null,lastDisconnectAt:null,
  lastDowntimeMs:null,recoveredAt:null,relativeSlowStreak:0
}};
{functions}
function assert(c,m){{if(!c)throw new Error(m);}}
function reset(){{
  probeHistory=[];
  monitorState={{consecutiveNetworkFailures:0,pendingFailureAt:null,outageStartedAt:null,lastDisconnectAt:null,lastDowntimeMs:null,recoveredAt:null,relativeSlowStreak:0}};
}}
function sample(state, latency, t, status=0){{return {{state:state,latencyMs:latency,httpStatus:status,checkedAt:new Date(t)}};}}
assert(qualityFromLatency(0).label==='Excellent','quality 0');
assert(qualityFromLatency(79).label==='Excellent','quality 79');
assert(qualityFromLatency(80).label==='Good','quality 80');
assert(qualityFromLatency(149).label==='Good','quality 149');
assert(qualityFromLatency(150).label==='Fair','quality 150');
assert(qualityFromLatency(299).label==='Fair','quality 299');
assert(qualityFromLatency(300).label==='Slow','quality 300');
assert(qualityFromLatency(null).label==='—','quality null');
assert(roundedMetric(null)==='—','metric null');
assert(formatDuration(null)==='—','duration null');
assert(formatDuration(5000)==='0:05','duration seconds');
assert(formatDuration(65000)==='1:05','duration minute');
assert(formatDuration(3661000)==='1:01:01','duration hour');
assert(!materiallySlowerThanBaseline(100,null),'no baseline');
assert(!materiallySlowerThanBaseline(69,20),'less than +50');
assert(!materiallySlowerThanBaseline(40,20),'must exceed 2x');
assert(materiallySlowerThanBaseline(100,20),'materially slower');

const t=1000000;
reset();
let r1=applyConnectionState(sample('offline',null,t,0));
assert(r1.state==='checking' && r1.confirmingOffline===true,'first failure checking');
assert(r1.lastDisconnectAt===null,'first failure not confirmed');
let r2=applyConnectionState(sample('offline',null,t+5000,0));
assert(r2.state==='offline' && r2.confirmedOffline===true,'second failure offline');
assert(r2.lastDisconnectAt.getTime()===t,'outage begins at first failure');
assert(r2.currentDowntimeMs===5000,'current downtime from first failure');
let r3=applyConnectionState(sample('online',25,t+12000,204));
assert(r3.state==='online' && r3.recentlyRecovered===true,'recovery online');
assert(r3.lastDowntimeMs===12000,'downtime finalized on recovery');
assert(r3.recoveredAt.getTime()===t+12000,'recovery time recorded');
assert(monitorState.outageStartedAt===null,'outage closed');
let r4=applyConnectionState(sample('online',25,t+28001,204));
assert(r4.recentlyRecovered===false,'recovery notice expires');

reset();
applyConnectionState(sample('offline',null,t,0));
applyConnectionState(sample('offline',null,t+5000,0));
let er=applyConnectionState(sample('error',null,t+8000,500));
assert(er.state==='error','HTTP error remains Probe Error');
assert(er.lastDowntimeMs===8000,'HTTP reachability closes network outage');
assert(monitorState.outageStartedAt===null,'error reachable closes outage');

reset();
[20,21,19,20,22].forEach((v,i)=>probeHistory.push({{state:'online',latencyMs:v,sampledAt:t+i*5000}}));
let b=baselineLatency(t+30000);
assert(b===20,'median baseline');
let high1=applyConnectionState(sample('online',100,t+30000,204));
assert(high1.relativeSlow===false,'first slow sample no warning');
probeHistory.push({{state:'online',latencyMs:100,sampledAt:t+30000}});
let high2=applyConnectionState(sample('online',110,t+35000,204));
assert(high2.relativeSlow===true,'second slow sample warns');
assert(high2.qualityLabel==='Good','quality still independently classified');
console.log('OK');
"""
    with tempfile.NamedTemporaryFile('w', suffix='.js', encoding='utf-8', delete=False) as handle:
        handle.write(node_script)
        temp_name = handle.name
    completed = subprocess.run(['node', temp_name], capture_output=True, text=True, timeout=10)
    if completed.returncode != 0:
        print(completed.stdout)
        print(completed.stderr)
    check(completed.returncode == 0 and completed.stdout.strip() == 'OK',
          'actual JavaScript D state helpers pass known outage/recovery/quality sequences')
except Exception as exc:
    check(False, f'JavaScript D helper execution test raised: {exc}')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
