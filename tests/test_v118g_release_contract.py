from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
checks = []

def check(ok: bool, message: str) -> None:
    checks.append(bool(ok))
    print(('PASS' if ok else 'FAIL') + ': ' + message)

version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
readme = (ROOT / 'README.md').read_text(encoding='utf-8')
changelog = (ROOT / 'CHANGELOG.md').read_text(encoding='utf-8')
notes = (ROOT / 'RELEASE_NOTES.md').read_text(encoding='utf-8')
release_builder = (ROOT / 'tools/build_release_package.py').read_text(encoding='utf-8')
complete_builder = (ROOT / 'tools/build_complete_package.py').read_text(encoding='utf-8')
release_verifier = (ROOT / 'tools/verify_release_package.py').read_text(encoding='utf-8')
complete_verifier = (ROOT / 'tools/verify_complete_package.py').read_text(encoding='utf-8')
ci = (ROOT / '.github/workflows/ci.yml').read_text(encoding='utf-8')

check("const APP_VERSION = '1.18.0';" in version, 'APP_VERSION is 1.18.0')
check("const APP_VERSION_LABEL = 'RSS Reader Modernization 1.18.0';" in version, 'version label is 1.18.0')
check("const APP_ASSET_REVISION = '1.18.0-r2';" in version, 'asset revision is 1.18.0-r2')
check('RSS Reader Modernization 1.18.0' in readme and 'v1.18.0' in readme, 'README declares 1.18.0 stable release')
check('RSS Reader Modernization 1.18.0' in changelog and 'Connection Monitor' in changelog, 'CHANGELOG contains 1.18.0 Connection Monitor entry')
check(notes.startswith('# RSS Reader Modernization 1.18.0 Release Notes'), 'Release Notes target 1.18.0')
check('Verification limits' in notes, 'Release Notes disclose verification limits')
check("INTENDED_RELEASE = '1.18.0'" in release_builder and "INTENDED_TAG = 'v1.18.0'" in release_builder, 'Runtime builder targets 1.18.0')
check("VERSION = '1.18.0'" in complete_builder, 'Complete builder targets 1.18.0')
check("intended_release') == '1.18.0'" in release_verifier, 'Runtime verifier checks 1.18.0')
check("VERSION = '1.18.0'" in complete_verifier, 'Complete verifier checks 1.18.0')
check('bash tests/run-v118.sh' in (ROOT / 'docs/tag-and-github-release.md').read_text(encoding='utf-8'), 'Tag guide includes V1.18 focused tests')
check('Run Version 1.18 focused tests' in ci, 'CI includes V1.18 focused tests')
check((ROOT / 'docs/v1-18-connection-monitor.md').is_file(), 'Connection Monitor design document exists')
check((ROOT / 'docs/release-gate-v1.18.0.md').is_file(), 'V1.18 release gate document exists')
check((ROOT / 'public/connection_probe.php').is_file(), 'Connection probe endpoint exists')
check((ROOT / 'app/health_probe.php').is_file(), 'Health probe persistence module exists')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
