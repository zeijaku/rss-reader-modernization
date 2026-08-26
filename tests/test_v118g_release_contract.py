from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
checks = []

def check(ok: bool, message: str) -> None:
    checks.append(bool(ok))
    print(('PASS' if ok else 'FAIL') + ': ' + message)

def text(rel: str) -> str:
    return (ROOT / rel).read_text(encoding='utf-8')

def extract(source: str, pattern: str) -> str:
    match = re.search(pattern, source)
    return match.group(1) if match else ''

def release_tuple(value: str) -> tuple[int, int, int]:
    match = re.fullmatch(r'(\d+)\.(\d+)\.(\d+)(?:-[A-Za-z0-9.]+)?', value)
    return tuple(int(part) for part in match.groups()) if match else (0, 0, 0)

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
check('RSS Reader Modernization 1.18.0' in changelog and 'Connection Monitor' in changelog, 'CHANGELOG retains the V1.18.0 Connection Monitor entry')

runtime_release = extract(release_builder, r"INTENDED_RELEASE\s*=\s*'([^']+)'")
runtime_tag = extract(release_builder, r"INTENDED_TAG\s*=\s*'v([^']+)'")
complete_builder_version = extract(complete_builder, r"VERSION\s*=\s*'([^']+)'")
runtime_verifier_release = extract(release_verifier, r"metadata\.get\('intended_release'\)\s*==\s*'([^']+)'")
complete_verifier_version = extract(complete_verifier, r"VERSION\s*=\s*'([^']+)'")
minimum_release = (1, 19, 0)

check(runtime_release == runtime_tag and release_tuple(runtime_release) >= minimum_release, 'Runtime builder targets V1.19 or a later release line')
check(release_tuple(complete_builder_version) >= minimum_release, 'Complete builder targets V1.19 or a later source')
check(release_tuple(runtime_verifier_release) >= minimum_release, 'Runtime verifier targets V1.19 or a later release line')
check(release_tuple(complete_verifier_version) >= minimum_release, 'Complete verifier targets V1.19 or a later source')
check('Run Version 1.18 focused tests' in ci, 'CI continues to include V1.18 compatibility tests')
check((ROOT / 'docs/v1-18-connection-monitor.md').is_file(), 'Connection Monitor design document exists')
check((ROOT / 'docs/release-gate-v1.18.0.md').is_file(), 'V1.18 release gate document remains available')
check((ROOT / 'public/connection_probe.php').is_file(), 'Connection probe endpoint exists')
check((ROOT / 'app/health_probe.php').is_file(), 'Health probe persistence module exists')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
