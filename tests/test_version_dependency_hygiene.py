#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
TESTS = ROOT / 'tests'
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


# Current-following tests must not freeze the active asset revision to the
# release in which the feature test was first introduced. Historical final
# release tests are intentionally outside this scan.
current_following = sorted(TESTS.glob('test_current_*.py'))
current_following.extend([
    TESTS / 'test_v122b_feed_health.py',
    TESTS / 'test_v122c_rss_rules.py',
    TESTS / 'test_v122d_rss_rules.py',
])

pinned_asset_patterns = [
    re.compile(r"APP_ASSET_REVISION\s*=\s*['\"]\d+\.\d+\.\d+(?:-[A-Za-z0-9._-]+)?['\"]"),
    re.compile(r"\?v=\d+\.\d+\.\d+(?:-[A-Za-z0-9._-]+)?"),
    re.compile(r"asset_revision\s*==\s*['\"]\d+\.\d+\.\d+"),
    re.compile(r"asset_revision\.startswith\(\s*['\"]\d+\.\d+\.\d+"),
]

for path in current_following:
    text = path.read_text(encoding='utf-8')
    pinned = any(pattern.search(text) for pattern in pinned_asset_patterns)
    check(not pinned, f'current-following test has no pinned release asset key: {path.name}')

ci = (ROOT / '.github/workflows/ci.yml').read_text(encoding='utf-8')
check('run-v121e.sh' not in ci, 'current CI does not invoke the historical V1.21 final release gate')
check('run-v122e.sh' not in ci, 'current CI does not invoke the historical V1.22 final release gate')
check('run-v121-compat.sh' in ci, 'current CI keeps V1.21 feature compatibility without finalization checks')
for runner in ['run-v122b.sh', 'run-v122c.sh', 'run-v122d.sh']:
    check(runner in ci, f'current CI keeps focused compatibility runner: {runner}')

# Historical release tests remain in the source tree because they document
# immutable release contracts. Historical workflow YAML is preserved by Git
# history/tags rather than kept active under .github/workflows.
for rel in [
    'tests/test_v121e_final.py',
    'tests/test_v122e_final.py',
]:
    check((ROOT / rel).is_file(), f'historical release test remains preserved: {rel}')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
