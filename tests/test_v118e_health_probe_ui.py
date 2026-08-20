from pathlib import Path
import re
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


client_path = ROOT / 'public/js/connection-monitor.js'
client = client_path.read_text(encoding='utf-8')
probe = (ROOT / 'public/connection_probe.php').read_text(encoding='utf-8')

# Preserve B-D network / state behavior.
check('V1.18-E' in client, 'client source identifies the V1.18-E phase')
check('V1.18-D' in client, 'V1.18-D state semantics are explicitly preserved')
check("url: './connection_probe.php'" in client, 'same-origin probe endpoint remains unchanged')
check('probeIntervalMs = 5000' in client and 'probeTimeoutMs = 4000' in client,
      'five-second probe and four-second timeout remain')
check('probePending' in client and 'window.setTimeout(runProbe, probeIntervalMs)' in client,
      'single non-overlapping self-scheduled probe remains')
check('document.hidden' in client and 'visibilitychange' in client,
      'background pause and immediate visibility recovery remain')
check('localStorage' not in client and 'sessionStorage' not in client,
      'monitor history/state remains page-memory only')
check('http_response_code(204)' in probe and 'conn_db' not in probe and 'curl_' not in probe,
      'probe remains 204, DB-free and outbound-free')
check('createElementNS' in client and "'polyline'" in client,
      'inline SVG graph remains dependency-free')
check("historyStat('Avg'" in client and "historyStat('Max'" in client and "historyStat('Jitter'" in client,
      'V1.18-C statistics remain')
check('offlineConfirmFailures = 2' in client and 'Last Disconnect' in client and 'Last Downtime' in client,
      'V1.18-D outage confirmation and downtime remain')
check(all(label in client for label in ['Excellent', 'Good', 'Fair', 'Slow', 'Offline']),
      'V1.18-D quality labels remain')

# E Height 1 / Height 2 presentation.
compact_selector = '.dashboard-grid>.health-probe-card[data-widget-height="1"] .health-probe-message'
check(compact_selector in client and '.health-probe-baseline' in client and '.health-probe-route' in client and '.health-probe-meta' in client,
      'Height 1 compact selectors cover verbose message/baseline/route/meta')
check('data-widget-height="2"] .health-probe-chart-wrap{height:170px;min-height:170px}' in client,
      'Height 2 receives a larger 170px graph on desktop/tablet')
check('data-widget-height="1"] .health-probe-chart-wrap{height:62px;min-height:62px}' in client,
      'Height 1 uses a compact graph on desktop/tablet')
check('@media (min-width:768px)' in client and '@media (max-width:767.98px)' in client,
      'desktop/tablet and smartphone behavior are separated at the dashboard breakpoint')
check('data-widget-height="1"] .health-probe-message' in client and 'display:flex' in client and 'display:block' in client,
      'smartphone restores detail content even when stored Height is 1')
check('.health-probe-chart-wrap{height:78px;min-height:78px}' in client,
      'smartphone graph has a stable readable height')
check('.health-probe-history-head{flex-wrap:wrap}' in client,
      'smartphone history header can wrap')
check('.health-probe-history-buttons .btn{min-height:34px' in client,
      'smartphone history controls have larger touch targets')
check('@media (max-width:359.98px)' in client and '.health-probe-events{grid-template-columns:minmax(0,1fr)}' in client,
      'very narrow smartphones stack outage event rows')

# E theme readability.
for token in [
    '--bs-body-bg', '--bs-body-color', '--bs-border-color', '--bs-tertiary-bg',
    '--bs-success-bg-subtle', '--bs-primary-bg-subtle', '--bs-warning-bg-subtle', '--bs-danger-bg-subtle'
]:
    check(token in client, f'theme-aware Bootstrap variable is used: {token}')
check('var(--bs-success-text-emphasis' in client and 'var(--bs-danger-text-emphasis' in client,
      'status text uses theme-aware emphasis colors')
check('health-probe-chart-scale' in client and 'background:var(--bs-body-bg' in client,
      'graph scale remains readable over themed graph backgrounds')

# E shared refresh / light performance polish.
check('checkedTimeFormatter' in client and 'new window.Intl.DateTimeFormat' in client,
      'time formatter is cached instead of recreated for every card render')
check('function setRefreshPending(pending)' in client,
      'shared refresh pending state exists')
check(".prop('disabled', isPending)" in client and "toggleClass('fa-spin', isPending)" in client,
      'all Connection Monitor refresh buttons reflect shared probe activity')
check('setRefreshPending(true)' in client and 'setRefreshPending(false)' in client,
      'refresh pending UI is entered and cleared around the same shared probe')
check('@media (prefers-reduced-motion:reduce)' in client,
      'refresh animation respects reduced-motion preference')
check('PCでは「標準」は主要情報をコンパクトに表示' in client and 'スマートフォンでは縦幅設定に関係なく詳細を表示' in client,
      'widget modal explains Height 1 / Height 2 / smartphone behavior')

# No accidental external monitoring or new chart dependency in E.
external_url_source = client.replace('http://www.w3.org/2000/svg', '')
check('http://' not in external_url_source and 'https://' not in external_url_source,
      'Connection Monitor JavaScript introduces no external URL dependency beyond the SVG namespace')
check('Chart(' not in client and 'new Chart' not in client and '<canvas' not in client,
      'no graph library/canvas dependency was introduced')

# Execute the shipped JS syntax as an independent signal.
completed = subprocess.run(['node', '--check', str(client_path)], capture_output=True, text=True, timeout=10)
if completed.returncode != 0:
    print(completed.stdout)
    print(completed.stderr)
check(completed.returncode == 0, 'shipped Connection Monitor JavaScript passes node --check')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
