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
    re.compile(r"asset_revision\s*==\s*['\"]\d+\.\d+\.\d+"),
    re.compile(r"asset_revision\.startswith\(\s*['\"]\d+\.\d+\.\d+"),
    re.compile(r"active_revision\s*==\s*['\"]\d+\.\d+\.\d+"),
    re.compile(r"active_revision\.startswith\(\s*['\"]\d+\.\d+\.\d+"),
    re.compile(r"re\.fullmatch\([^\n]*\d+\\\.\d+\\\.\d+[^\n]*active_revision"),
]

for path in current_following:
    body = path.read_text(encoding='utf-8')
    pinned = any(pattern.search(body) for pattern in pinned_asset_patterns)
    check(not pinned, f'current-following test has no pinned active release key: {path.name}')

# Active automation must stay version-neutral. Version-specific runners are
# retained for historical/targeted investigation but must not accumulate in
# normal CI or the standard Release workflow.
ci = (ROOT / '.github/workflows/ci.yml').read_text(encoding='utf-8')
release = (ROOT / '.github/workflows/release.yml').read_text(encoding='utf-8')
version_runner = re.compile(r'\b(?:bash|sh)\s+tests/run-v\d', flags=re.IGNORECASE)

for name, body in [('CI', ci), ('Release', release)]:
    check(not version_runner.search(body), f'{name} does not invoke version-specific run-v*.sh gates')
    check('bash tests/run-current.sh' in body, f'{name} runs the current regression suite')
    check('bash tests/run-current-features.sh' in body, f'{name} runs durable current feature contracts')

# Historical release/compatibility tests remain in the source tree because
# they document immutable release contracts and support targeted investigation.
for rel in [
    'tests/test_v121a_drawer_categories.py',
    'tests/test_v121b_drawer_visual.py',
    'tests/test_v121c_mobile_touch.py',
    'tests/test_v121e_final.py',
    'tests/test_v122e_final.py',
]:
    check((ROOT / rel).is_file(), f'historical test remains preserved: {rel}')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
