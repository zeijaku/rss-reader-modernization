#!/usr/bin/env python3
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
calendar = text('public/js/calendar.js')
streaming = text('public/js/camera-video-streaming.js')
notes = text('RELEASE_NOTES.md')
readme = text('README.md')
changelog = text('CHANGELOG.md')
rb = text('tools/build_release_package.py')
rv = text('tools/verify_release_package.py')
cb = text('tools/build_complete_package.py')
cv = text('tools/verify_complete_package.py')
ci = text('.github/workflows/ci.yml')

check("const APP_VERSION = '1.19.0-rc1';" in version or "const APP_VERSION = '1.19.0';" in version, 'APP_VERSION is the V1.19 RC1 or final line')
check('RSS Reader Modernization 1.19.0' in version, 'visible label remains on the V1.19 release line')
revision = '1.19.0-rc1' if "1.19.0-rc1" in version else '1.19.0'
check(f"const APP_ASSET_REVISION = '{revision}';" in version, 'V1.19 build has a fresh asset revision')
for asset in [
    './css/mail-widget.css', './css/camera-video.css', './css/camera-video-playback.css', './css/camera-video-streaming.css', './css/x-widget.css',
    './js/app-notice.js', './js/widget-card-refresh.js', './js/information-widget-watchdog.js', './js/mail-widget-watchdog.js',
    './js/camera-video-watchdog.js', './js/mail-widget.js', './js/camera-video.js', './js/camera-video-playback.js',
    './js/camera-video-streaming.js', './js/x-widget.js', './js/widget-settings-no-reload.js'
]:
    check(asset + '?v=' + revision in calendar, f'current dynamic asset uses active V1.19 revision: {asset}')
check('./css/camera-video-streaming.css?v=' + revision in streaming, 'Camera streaming fallback CSS uses active V1.19 revision')
check('sha384-5E8B0pTlZZJMabWpC0fyYf6OUpe15jJij34BqBAh4NXoHAlLNOjCPRrwtOXOQFAn' in streaming, 'hls.js SRI digest is the verified value')
check(notes.startswith('# RSS Reader Modernization 1.19.0'), 'release notes remain on the V1.19 release line')
check(('正式Releaseではありません' in notes) == ('-rc1' in revision), 'release notes final/RC warning matches the active build')
check('Verification limits' in notes, 'RC notes disclose verification limits')
check(('1.19.0-RC1' in readme) or ('**Stable release:** `RSS Reader Modernization 1.19.0`' in readme), 'README exposes the accepted RC or final V1.19 release')
check('1.19.0' in changelog, 'CHANGELOG retains the V1.19 release history')
check("INTENDED_RELEASE = '1.19.0'" in rb and "INTENDED_TAG = 'v1.19.0'" in rb, 'runtime builder targets final V1.19 release line')
check(r"1\.19\.0-rc" in rb and 'RELEASE_CANDIDATE' in rb, 'runtime builder supports V1.19 RC mode')
check("metadata.get('intended_release') == '1.19.0'" in rv and r'1\.19\.0-rc' in rv, 'runtime verifier enforces V1.19 RC metadata')
check(("VERSION = '1.19.0-rc1'" in cb and 'publishable=no' in cb) or ("VERSION = '1.19.0'" in cb and 'publishable=yes' in cb), 'complete builder matches the active V1.19 publication state')
check(("VERSION = '1.19.0-rc1'" in cv) or ("VERSION = '1.19.0'" in cv), 'complete verifier targets the active V1.19 source')
check(('bash tests/run-v119e.sh' in ci) or ('bash tests/run-v119f.sh' in ci), 'CI includes V1.19 compatibility/final gate')
check(not any(re.search(r'1[_\.-]19', p.name, re.I) for p in (ROOT / 'database/migrations').glob('*')) if (ROOT / 'database/migrations').is_dir() else True, 'V1.19 adds no database migration')
check((ROOT / 'docs/v1-19-e-release-candidate.md').is_file(), 'V1.19-E RC documentation exists')
check((ROOT / 'docs/v1-19-e-production-checklist.md').is_file(), 'V1.19-E production checklist exists')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
