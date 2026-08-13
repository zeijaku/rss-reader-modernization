from pathlib import Path
import re

from version_test_utils import is_later_application_release, is_later_visible_label
from dashboard_source_utils import dashboard_source
ROOT = Path(__file__).resolve().parents[1]
failures = []
def check(condition, message):
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition: failures.append(message)

version = (ROOT / 'app/version.php').read_text()
index = dashboard_source(ROOT)
lights = (ROOT / 'public/js/lights-out.js').read_text()
dash = (ROOT / 'public/js/dashboard.js').read_text()
css = (ROOT / 'public/css/mini-game.css').read_text()
run = (ROOT / 'tests/run.sh').read_text()

check("const APP_VERSION = '1.6.0-dev.3';" in version or "const APP_VERSION = '1.6.0';" in version or "const APP_VERSION = '1.7.0-dev.1';" in version or "const APP_VERSION = '1.7.0-dev.2';" in version or "const APP_VERSION = '1.7.0-dev.3';" in version or "const APP_VERSION = '1.7.0-dev.4';" in version or "const APP_VERSION = '1.7.0-dev.5';" in version or "const APP_VERSION = '1.7.0-dev.6';" in version or "const APP_VERSION = '1.7.0-dev.7';" in version or "const APP_VERSION = '1.7.0-dev.8';" in version or "const APP_VERSION = '1.7.0-dev.9';" in version or "const APP_VERSION = '1.7.0-dev.10';" in version or "const APP_VERSION = '1.7.0';" in version or is_later_application_release(version, (1, 6, 0)), 'Application version is V1.6-D or later')
check('RSS Reader Modernization V1.6-D / R1' in version or "APP_VERSION_LABEL = 'RSS Reader Modernization 1.6.0'" in version or 'RSS Reader Modernization V1.7-' in version or is_later_visible_label(version, (1, 6, 0)), 'visible label identifies V1.6-D or later')
check("rssReader.miniGame.lightsOut.v1" in lights, 'Lights Out uses its own versioned Storage prefix')
check("'.user.' + safeUserId + '.widget.' + safeWidgetId" in lights, 'Storage key separates User and Widget')
check("localStorage" in lights and "sessionStorage" in lights and "memory" in lights, 'localStorage, sessionStorage and memory fallback are implemented')
check('function validateState' in lights and "value.game !== 'lights_out'" in lights, 'stored state is schema and game validated')
check('initialBoard' in lights and 'moves' in lights and "status: 'playing'" in lights, 'board, Reset board, Moves and status persist')
check('loadStateResult' in lights and 'invalid-data' in lights and 'repaired-copy' in lights, 'invalid and partial Storage recovery is explicit')
check('removeWidgetState' in lights and 'removeEverywhere' in lights, 'Widget state can be removed from every Storage tier')
check('window.RssLightsOut.removeWidgetState' in dash, 'Game deletion cleans Lights Out state')
check('data-original-game-type' in dash and 'originalGameType !==' in dash, 'changing Game subtype cleans the previous game state')
check('keyboardTarget' in lights and "event.key" in lights and "focusCell" in lights, 'Arrow, Home and End keyboard focus movement is implemented')
check("tabindex=\"' . ($gameCellIndex === 0 ? '0' : '-1')" in index, 'server markup starts with one roving tabindex target')
check('aria-pressed' in lights and 'aria-label' in lights and 'aria-live="polite"' in index, 'Screen Reader state and result text remain exposed')
check('prefers-reduced-motion: reduce' in css and '.lights-out-cell:focus-visible' in css, 'Reduced Motion and visible keyboard Focus are retained')
check(("app_asset_url('js/lights-out.js')" in index and "app_asset_url('css/mini-game.css')" in index) or ('./js/lights-out.js?v=1.6-d-r1' in index and './css/mini-game.css?v=1.6-d-r1' in index), 'changed Game assets use the active Cache Busting strategy')
check("app_asset_url('js/dashboard.js')" in index or './js/dashboard.js?v=1.6-d-r1' in index, 'changed Dashboard asset uses the active Cache Busting strategy')
check(not re.search(r'Notification|vibrate|new Audio|Audio\(', lights), 'no sound, vibration or Browser notification was added')
check(not any('v16' in path.name.lower() for path in (ROOT / 'database/migrations').glob('*')), 'V1.6-D adds no Migration')
check('test_v16d_storage_runtime.js' in run and 'test_v16d_browser.py' in run, 'main runner includes V1.6-D tests')

if failures:
    raise SystemExit(f'{len(failures)}/20 V1.6-D architecture checks failed')
print('All V1.6-D architecture checks passed.')
