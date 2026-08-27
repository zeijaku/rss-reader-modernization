from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
checks = []

def check(ok: bool, message: str) -> None:
    checks.append(bool(ok))
    print(('PASS' if ok else 'FAIL') + ': ' + message)

def text(rel: str) -> str:
    return (ROOT / rel).read_text(encoding='utf-8')

version = text('app/version.php')
readme = text('README.md')
changelog = text('CHANGELOG.md')
release_builder = text('tools/build_release_package.py')
complete_builder = text('tools/build_complete_package.py')
release_verifier = text('tools/verify_release_package.py')
complete_verifier = text('tools/verify_complete_package.py')
ci = text('.github/workflows/ci.yml')

m = re.search(r"const APP_VERSION = '(\d+)\.(\d+)\.(\d+)(?:-[^']+)?';", version)
version_tuple = tuple(int(x) for x in m.groups()) if m else (0, 0, 0)
check(version_tuple >= (1, 18, 0), 'current application remains Version 1.18.0-or-later')
check('Version 1.18.0' in readme and 'Connection Monitor' in readme, 'README retains the V1.18.0 Connection Monitor release history')
check('1.18.0' in changelog and 'Connection Monitor' in changelog, 'CHANGELOG retains the V1.18.0 Connection Monitor entry')

# V1.23 standardized release tooling receives the intended final version as an
# explicit independent input. This V1.18 compatibility gate checks that durable
# release-tool contract instead of requiring a historical hardcoded VERSION /
# INTENDED_RELEASE constant to remain in each tool forever.
for name, body in (
    ('Runtime builder', release_builder),
    ('Complete builder', complete_builder),
    ('Runtime verifier', release_verifier),
    ('Complete verifier', complete_verifier),
):
    check('--release' in body and 'required=True' in body, f'{name} accepts an explicit final release version')

check('Run Version 1.18 focused tests' in ci, 'CI continues to include V1.18 compatibility tests')
check((ROOT / 'docs/v1-18-connection-monitor.md').is_file(), 'Connection Monitor design document exists')
check((ROOT / 'docs/release-gate-v1.18.0.md').is_file(), 'V1.18 release gate document remains available')
check((ROOT / 'public/connection_probe.php').is_file(), 'Connection probe endpoint exists')
check((ROOT / 'app/health_probe.php').is_file(), 'Health probe persistence module exists')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
