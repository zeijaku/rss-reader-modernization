#!/usr/bin/env python3
from pathlib import Path
import re
import sys

root = Path(__file__).resolve().parents[1]
source = (root / 'public' / 'api_v1.php').read_text(encoding='utf-8')

checks = []

def check(name: str, condition: bool) -> None:
    checks.append((name, bool(condition)))

check('policy helper exists', 'function api_action_requires_open_session(string $action): bool' in source)
check('email update keeps session open', "'account.email.update'" in source)
check('password update keeps session open', "'account.password.update'" in source)
check('normal actions release session', re.search(
    r"if\s*\(!api_action_requires_open_session\(\$action\)\)\s*\{\s*app_session_release\(\);\s*\}",
    source,
    re.S,
) is not None)

csrf_pos = source.find('app_csrf_is_valid($csrfToken)')
action_validation_pos = source.find("preg_match('/^[a-z]+(?:\\.[a-z]+)+$/', $action)")
try_pos = source.find('try {', action_validation_pos)
release_pos = source.find('app_session_release();')
dispatch_pos = source.find("if (str_starts_with($action, 'camera.widget.'))")
catch_pos = source.find('} catch (Throwable $exception)', dispatch_pos)
check(
    'release occurs only after auth/CSRF/action validation and before dispatch',
    csrf_pos >= 0
    and action_validation_pos > csrf_pos
    and release_pos > action_validation_pos
    and dispatch_pos > release_pos,
)
check(
    'session release is inside the API Throwable boundary',
    try_pos > action_validation_pos
    and release_pos > try_pos
    and dispatch_pos > release_pos
    and catch_pos > dispatch_pos,
)
check(
    'release failure comment documents JSON error containment',
    'session_write_close() failure still returns the normal JSON error' in source,
)

failed = [name for name, ok in checks if not ok]
for name, ok in checks:
    print(f"[{'PASS' if ok else 'FAIL'}] {name}")

if failed:
    print(f"V1.17.1-A/E API session policy: {len(failed)}/{len(checks)} failed", file=sys.stderr)
    sys.exit(1)

print(f"V1.17.1-A/E API session policy: {len(checks)}/{len(checks)} passed")
