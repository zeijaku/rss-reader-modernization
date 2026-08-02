from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
runner = (ROOT / 'tests' / 'run.sh').read_text(encoding='utf-8')
ci = (ROOT / '.github' / 'workflows' / 'ci.yml').read_text(encoding='utf-8')
checks = []

def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

for script in [
    'test_m2a_frontend_structure.py',
    'test_m2b_feed_runtime.js',
    'test_m2f_dependency_inventory.py',
]:
    check(script in runner, f'active M2 functional regression remains in the main runner: {script}')

check("const APP_VERSION = '1\\.0\\.0" in runner, 'historical release-gate condition is tied to the Version 1.0 marker')
check('test_m2g_final_regression.py' in runner, 'M2-G final gate remains available for the Version 1.0 tree')
check('test_m4g_final_release.py' in runner, 'M4-G final package gate remains available for the Version 1.0 tree')
check('M2-G final-release gate is historical during V1.1 development' in runner, 'V1.1 development records an explicit M2-G historical SKIP')
check('M4-A..G Version 1.0 release gates are historical during V1.1 development' in runner, 'V1.1 development records an explicit M4 historical SKIP')
check(runner.index('test_v11b_tracking_parameters.php') > runner.index('fi\n'), 'V1.1-B checks run outside the Version 1.0 historical branch')
check(runner.index('test_v11c_feed_item_state.php') > runner.index('test_v11b_tracking_parameters.php'), 'V1.1-C checks follow V1.1-B')
check('bash tests/run.sh' in ci, 'GitHub Actions continues to run the repository regression entry point')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} V1.1-C runner checks passed.')
