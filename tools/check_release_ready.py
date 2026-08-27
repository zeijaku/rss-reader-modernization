#!/usr/bin/env python3
from __future__ import annotations

import argparse
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
SEMVER = re.compile(r'[0-9]+\.[0-9]+\.[0-9]+')
checks: list[bool] = []


def check(ok: bool, message: str) -> None:
    checks.append(bool(ok))
    print(('PASS' if ok else 'FAIL') + ': ' + message)


def text(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def main() -> int:
    parser = argparse.ArgumentParser(description='Validate source readiness for a final RSS Reader release.')
    parser.add_argument('--release', required=True, help='Expected final version, for example X.Y.Z')
    args = parser.parse_args()
    release = args.release

    check(SEMVER.fullmatch(release) is not None, 'requested release version is valid semantic version')
    if not checks[-1]:
        return 1

    version = text('app/version.php')
    readme = text('README.md')
    changelog = text('CHANGELOG.md')
    notes = text('RELEASE_NOTES.md')

    expected = {
        'APP_VERSION': release,
        'APP_VERSION_LABEL': f'RSS Reader Modernization {release}',
        'APP_ASSET_REVISION': release,
    }
    for name, value in expected.items():
        match = re.search(rf"{name}\s*=\s*'([^']+)'", version)
        check(match is not None, f'app/version.php contains {name}')
        if match is not None:
            check(match.group(1) == value, f'{name} matches requested release')

    check(
        f'**Stable release:** `RSS Reader Modernization {release}`' in readme,
        'README stable release matches requested release',
    )
    check(f'Release tag: `v{release}`' in readme, 'README release tag matches requested release')
    check(
        re.search(rf'^## {re.escape(release)} - \d{{4}}-\d{{2}}-\d{{2}}$', changelog, flags=re.MULTILINE) is not None,
        'CHANGELOG contains dated requested release heading',
    )
    check(
        f'# RSS Reader Modernization {release}' in notes,
        'RELEASE_NOTES heading matches requested release',
    )
    check('正式Releaseではありません' not in notes, 'RELEASE_NOTES has no non-release warning')
    check('Verification limits' in notes, 'RELEASE_NOTES discloses verification limits')
    check(not (ROOT / 'config/local.php').exists(), 'repository does not contain config/local.php')
    check((ROOT / '.github/workflows/ci.yml').is_file(), 'current CI workflow exists')
    check((ROOT / '.github/workflows/release.yml').is_file(), 'generic release workflow exists')

    failed = len(checks) - sum(checks)
    print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
    return 1 if failed else 0


if __name__ == '__main__':
    sys.exit(main())
