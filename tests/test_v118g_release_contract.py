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
check('RSS Reader Modernization 1.18.0' in changelog and 'Connection Monitor' in changelog, 'CHANGELOG retains the V1.18.0 Connection Monitor entry')
check(('1.19.0' in release_builder and 'v1.19.0' in release_builder) or ('1.20.0' in release_builder and 'v1.20.0' in release_builder) or ('1.20.1' in release_builder and 'v1.20.1' in release_builder) or ('1.21.0' in release_builder and 'v1.21.0' in release_builder), 'Runtime builder targets V1.19 or a later release line')
check("VERSION = '1.19.0'" in complete_builder or "VERSION = '1.20.0-rc1'" in complete_builder or "VERSION = '1.20.0'" in complete_builder or "VERSION = '1.20.1'" in complete_builder or "VERSION = '1.21.0'" in complete_builder, 'Complete builder targets V1.19 or a later source')
check('1.19.0' in release_verifier or '1.20.0' in release_verifier or '1.20.1' in release_verifier or '1.21.0' in release_verifier, 'Runtime verifier targets V1.19 or a later release line')
check("VERSION = '1.19.0'" in complete_verifier or "VERSION = '1.20.0-rc1'" in complete_verifier or "VERSION = '1.20.0'" in complete_verifier or "VERSION = '1.20.1'" in complete_verifier or "VERSION = '1.21.0'" in complete_verifier, 'Complete verifier targets V1.19 or a later source')
check('Run Version 1.18 focused tests' in ci, 'CI continues to include V1.18 compatibility tests')
check((ROOT / 'docs/v1-18-connection-monitor.md').is_file(), 'Connection Monitor design document exists')
check((ROOT / 'docs/release-gate-v1.18.0.md').is_file(), 'V1.18 release gate document remains available')
check((ROOT / 'public/connection_probe.php').is_file(), 'Connection probe endpoint exists')
check((ROOT / 'app/health_probe.php').is_file(), 'Health probe persistence module exists')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
