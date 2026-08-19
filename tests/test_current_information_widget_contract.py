from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


bootstrap = (ROOT / 'app/bootstrap.php').read_text(encoding='utf-8')
api = (ROOT / 'app/api.php').read_text(encoding='utf-8')
widgets = (ROOT / 'app/dashboard_widget.php').read_text(encoding='utf-8')
api_entry = (ROOT / 'public/api_v1.php').read_text(encoding='utf-8')

modules = ['information_widget.php', 'weather.php', 'earthquake.php', 'sun_moon.php', 'air_quality.php']
for name in modules:
    check((ROOT / 'app' / name).is_file(), f'Information module exists: app/{name}')
    check(f"require_once __DIR__ . '/{name}';" in bootstrap, f'bootstrap loads {name}')

# Current public widget/API contract. Do not freeze helper names, CSS values or
# file-internal implementation order.
for typ in ['weather', 'earthquake', 'sun_moon', 'air_quality']:
    check(f"'{typ}'" in widgets, f'current widget type is registered: {typ}')

for action in [
    'widget.weather.create', 'widget.weather.update', 'widget.weather.delete', 'weather.forecast',
    'widget.earthquake.create', 'widget.earthquake.update', 'widget.earthquake.delete', 'earthquake.latest',
    'widget.sunmoon.create', 'widget.sunmoon.update', 'widget.sunmoon.delete', 'sunmoon.current',
    'widget.airquality.create', 'widget.airquality.update', 'widget.airquality.delete', 'airquality.current',
]:
    check(action in api, f'current API action is routed: {action}')

check("preg_match('/^[a-z]+(?:\\.[a-z]+)+$/', $action)" in api_entry,
      'public API action grammar remains constrained')
check(re.search(r"return \[[^\]]*'weather'[^\]]*'earthquake'[^\]]*'sun_moon'[^\]]*'air_quality'[^\]]*\]", (ROOT / 'app/information_widget.php').read_text(encoding='utf-8'), re.S) is not None,
      'Information persistence allowlist contains current widget families')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
