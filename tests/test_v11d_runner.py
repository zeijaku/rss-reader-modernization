from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
main_runner = (ROOT / 'tests' / 'run.sh').read_text(encoding='utf-8')
local_runner = (ROOT / 'tests' / 'run-local-v1-1-d.sh').read_text(encoding='utf-8')
m2c_render = (ROOT / 'tests' / 'test_m2c_dashboard_render.py').read_text(encoding='utf-8')
m2d_render = (ROOT / 'tests' / 'test_m2d_dashboard_render.py').read_text(encoding='utf-8')
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


for runner, label in [(main_runner, 'main'), (local_runner, 'local')]:
    check('== V1.1-B Tracking Parameter checks ==' in runner, f'{label} runner keeps V1.1-B regression')
    check('== V1.1-C Feed item NEW state checks ==' in runner, f'{label} runner keeps V1.1-C regression')
    check('== V1.1-D Dashboard Widget foundation checks ==' in runner, f'{label} runner includes V1.1-D section')
    check('test_v11d_dashboard_widget.php' in runner, f'{label} runner executes Widget transaction/owner tests')
    check('test_v11d_architecture.py' in runner and 'test_v11d_sql.py' in runner, f'{label} runner executes architecture and SQL tests')
    check('test_v11d_dashboard_render.py' in runner, f'{label} runner executes Dashboard render tests')
    check('test_v11d_runner.py' in runner, f'{label} runner validates its V1.1-D wiring')

check(main_runner.find('== V1.1-B') < main_runner.find('== V1.1-C') < main_runner.find('== V1.1-D'), 'V1.1 work units run in dependency order')
check("const APP_VERSION = '1\\.0\\.0" in main_runner, 'Version 1.0 release gates remain conditional')
check('SKIP: M2-G final-release gate is historical during V1.1 development.' in main_runner, 'historical M2-G gate is explicitly reported as SKIP')
check('SKIP: M4-A..G Version 1.0 release gates are historical during V1.1 development.' in main_runner, 'historical M4 gates are explicitly reported as SKIP')
check('test_v11d_dashboard_render.py' in m2c_render, 'M2-C render test follows the current Widget-backed Dashboard fixture')
check('test_v11d_dashboard_render.py' in m2d_render, 'M2-D render test follows the current Widget-backed Dashboard fixture')
check('aria-' in m2c_render and 'fieldset' in m2c_render and 'drawer' in m2c_render.lower(), 'M2-C accessibility checks remain represented')
check('44px' in m2d_render and 'pointer: coarse' in m2d_render and 'stock-card' in m2d_render, 'M2-D responsive/touch/Stock checks remain represented')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} V1.1-D runner checks passed.')
