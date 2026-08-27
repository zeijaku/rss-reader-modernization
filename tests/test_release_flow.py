#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


def text(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


workflow = text('.github/workflows/release.yml')
tool_paths = [
    'tools/build_release_package.py',
    'tools/verify_release_package.py',
    'tools/build_complete_package.py',
    'tools/verify_complete_package.py',
    'tools/check_release_ready.py',
]

check('workflow_dispatch:' in workflow, 'Release workflow keeps manual dispatch support')
check('version:' in workflow and 'required: true' in workflow, 'Manual Release requires explicit version input')
check('\n  push:' in workflow, 'Release workflow has browser-only request-file fallback')
check("- '.github/release-request.txt'" in workflow, 'Browser fallback watches only the release request file')
check('RELEASE_REQUEST_FILE: .github/release-request.txt' in workflow, 'Release workflow reads the browser request file')
check('if [ "${GITHUB_EVENT_NAME}" = \'push\' ]; then' in workflow, 'Release context distinguishes request-file pushes from manual dispatch')
check("tr -d '[:space:]' < \"${RELEASE_REQUEST_FILE}\"" in workflow, 'Browser request version is read from the request file')
check('GITHUB_REF_NAME' in workflow and "'main'" in workflow, 'Release workflow requires main as source ref')
check('main moved after this Release run started' in workflow, 'Release workflow checks main SHA before long verification')
check('main moved during Release verification' in workflow, 'Release workflow rechecks main SHA before publication')
check('git ls-remote --exit-code --tags origin' in workflow, 'Release workflow checks existing tag before publication')
check('already exists on a different commit. Refusing to overwrite it.' in workflow, 'Release workflow refuses tag overwrite')
check('gh release view "${TAG}"' in workflow, 'Release workflow checks existing GitHub Release')
check('leaving it unchanged.' in workflow, 'Existing GitHub Release is left unchanged')
check('git tag -f' not in workflow and 'git push --force' not in workflow and 'git push -f' not in workflow, 'Release workflow contains no force tag/ref update')
check('tools/check_release_ready.py --release "${RELEASE_VERSION}"' in workflow, 'Release workflow validates release-ready source against resolved version')

version_runner = re.compile(r'\b(?:bash|sh)\s+tests/run-v\d', flags=re.IGNORECASE)
check(not version_runner.search(workflow), 'Release workflow does not accumulate historical run-v*.sh gates')
check(workflow.count('bash tests/run-current.sh') == 2, 'Release workflow runs current regression on PHP 8.1 and 8.4')
check(workflow.count('bash tests/run-current-features.sh') == 2, 'Release workflow runs durable current feature contracts on PHP 8.1 and 8.4')
check('group: release-main' in workflow, 'Release workflow serializes final publication runs')
check('rss-reader-modernization-final-${{ github.run_id }}' in workflow, 'Actions artifact name does not depend on workflow_dispatch-only inputs')

for command in (
    'tools/build_release_package.py',
    'tools/verify_release_package.py',
    'tools/build_complete_package.py',
    'tools/verify_complete_package.py',
):
    offset = workflow.find(command)
    nearby = workflow[offset:offset + 220] if offset >= 0 else ''
    check(
        offset >= 0 and '--release "${RELEASE_VERSION}"' in nearby,
        f'Release workflow passes resolved version to {command}',
    )

check('config/local.php' in workflow, 'Release workflow checks private local config exclusion')
check('High-signal source secret scan' in workflow, 'Release workflow retains source secret scan')
check('Runtime package clean-room checks' in workflow, 'Release workflow retains Runtime clean-room checks')
check('Complete Source package clean-room checks' in workflow, 'Release workflow retains Complete Source clean-room checks')
check('tests/run-current-features.sh' in workflow, 'Complete Source clean-room checks require the current feature runner')
check('actions/upload-artifact@v4' in workflow, 'Release workflow retains packaged asset artifact upload')
check('gh release create "${TAG}"' in workflow, 'Release workflow publishes GitHub Release only after verification')

for path in tool_paths:
    body = text(path)
    check("'1.22.0'" not in body and '"1.22.0"' not in body, f'generic release tooling has no V1.22 release pin: {path}')
    check('--release' in body, f'generic release tooling accepts explicit release input: {path}')

build_runtime = text('tools/build_release_package.py')
verify_runtime = text('tools/verify_release_package.py')
build_complete = text('tools/build_complete_package.py')
verify_complete = text('tools/verify_complete_package.py')

check("f'intended_release={release}'" in build_runtime, 'Runtime builder writes requested intended_release metadata')
check("metadata.get('intended_release') == release" in verify_runtime, 'Runtime verifier independently checks requested intended_release')
check("f'intended_tag=v{release}'" in build_complete, 'Complete builder writes requested intended_tag metadata')
check("metadata.get('intended_tag') == f'v{release}'" in verify_complete, 'Complete verifier independently checks requested intended_tag')
check("'.github/workflows/release.yml'" in build_complete, 'Complete Source builder requires generic release workflow')
check("'.github/workflows/release.yml'" in verify_complete, 'Complete Source verifier requires generic release workflow')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
