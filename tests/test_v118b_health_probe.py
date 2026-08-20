from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


bootstrap = (ROOT / 'app/bootstrap.php').read_text(encoding='utf-8')
api = (ROOT / 'app/api.php').read_text(encoding='utf-8')
widgets = (ROOT / 'app/dashboard_widget.php').read_text(encoding='utf-8')
information = (ROOT / 'app/information_widget.php').read_text(encoding='utf-8')
module = (ROOT / 'app/health_probe.php').read_text(encoding='utf-8')
probe = (ROOT / 'public/connection_probe.php').read_text(encoding='utf-8')
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
client = (ROOT / 'public/js/connection-monitor.js').read_text(encoding='utf-8')

check("'health_probe'" in widgets, 'dashboard widget type health_probe is registered')
check("'health_probe'" in information, 'Information Widget persistence allowlist contains health_probe')
check("require_once __DIR__ . '/health_probe.php';" in bootstrap, 'bootstrap loads health_probe module')

for action in [
    'widget.healthprobe.create',
    'widget.healthprobe.update',
    'widget.healthprobe.delete',
]:
    check(action in api, f'API action is routed: {action}')

for helper in [
    'information_widget_create_record',
    'information_widget_update_record',
    'information_widget_delete_record',
]:
    check(helper in module, f'health_probe module reuses shared persistence: {helper}')

check("'health_probe'" in module, 'health_probe module stores the agreed internal widget_type')
check("REQUEST_METHOD" in probe and "!== 'GET'" in probe, 'probe accepts GET only')
check('http_response_code(204)' in probe, 'probe returns 204 No Content')
check("require" not in probe.lower(), 'probe does not load the application/bootstrap')
check('conn_db' not in probe and 'PDO' not in probe, 'probe does not access the database')
check('curl_' not in probe and 'http://' not in probe and 'https://' not in probe, 'probe has no outbound target')
check('Cache-Control: no-store' in probe, 'probe disables response caching')

check("app_asset_url('js/connection-monitor.js')" in index, 'Dashboard loads connection-monitor.js')
check("widget.widget_type || '') !== 'health_probe'" in client, 'client loads only health_probe rows')
check("widget.healthprobe.create" in client and "widget.healthprobe.update" in client and "widget.healthprobe.delete" in client,
      'client uses health_probe CRUD API actions')
check("./connection_probe.php" in client, 'client probes only the same-origin lightweight endpoint')
check('performance.now' in client, 'latency uses a monotonic browser timer when available')
check('probeIntervalMs = 5000' in client, 'foreground probe interval is five seconds')
check('document.hidden' in client and 'visibilitychange' in client, 'background page pauses periodic probes')
check("'online'" in client and "'offline'" in client and 'navigator.onLine' in client,
      'browser online/offline signal is supplemental input')
check('probePending' in client, 'client prevents overlapping probe requests')
check('widgetCatalog-information' in client, 'Connection Monitor is added to the Information catalog')
check('Chart' not in client and 'canvas.getContext' not in client, 'V1.18-B adds no graph library or graph implementation')
check('google.' not in client.lower() and 'cloudflare.' not in client.lower(), 'client does not use a fixed external probe service')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
