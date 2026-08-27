#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
WORKFLOWS = ROOT / '.github/workflows'
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


workflow_paths = sorted([
    *WORKFLOWS.glob('*.yml'),
    *WORKFLOWS.glob('*.yaml'),
])
workflow_names = {path.name for path in workflow_paths}
expected_names = {'ci.yml', 'release.yml'}

check((WORKFLOWS / 'ci.yml').is_file(), 'current CI workflow exists')
check((WORKFLOWS / 'release.yml').is_file(), 'generic Release workflow exists')
check(workflow_names == expected_names, 'active workflows are exactly ci.yml and release.yml')

version_named = sorted(
    path.name for path in workflow_paths
    if re.match(r'^v\d', path.name, flags=re.IGNORECASE)
)
check(not version_named, 'no version-specific workflow file remains active')

release_branch_literal = re.compile(r'release/v\d+\.\d+\.\d+(?:-[A-Za-z0-9._-]+)?')
for path in workflow_paths:
    body = path.read_text(encoding='utf-8')
    check(
        not release_branch_literal.search(body),
        f'active workflow has no release branch pinned to one historical version: {path.name}',
    )

ci = (WORKFLOWS / 'ci.yml').read_text(encoding='utf-8')
release = (WORKFLOWS / 'release.yml').read_text(encoding='utf-8')
check('contents: read' in ci, 'CI token remains read-only')
check('pull_request_target' not in ci, 'CI does not use pull_request_target')
check('tests/test_workflow_hygiene.py' in ci, 'CI runs workflow hygiene guard')

check('workflow_dispatch:' in release, 'Release workflow keeps manual workflow_dispatch support')
check('\n  push:' in release, 'Release workflow supports browser-only release requests through a restricted push trigger')
check('pull_request:' not in release, 'Release workflow is never triggered directly by pull_request')
check("- '.github/release-request.txt'" in release, 'Release push trigger is restricted to the browser release request file')
check('branches:' in release and '- main' in release, 'Release push trigger is restricted to main')
check('release-main' in release, 'Release runs are serialized on main')
check('contents: write' in release, 'Release workflow has contents write permission for final tag/Release publication')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
