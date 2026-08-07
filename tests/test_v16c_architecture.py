from pathlib import Path
import re

from version_test_utils import is_later_application_release, is_later_visible_label
ROOT = Path(__file__).resolve().parents[1]
failures = []
def check(condition, message):
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition: failures.append(message)

version = (ROOT / 'app/version.php').read_text()
mini_php = (ROOT / 'app/mini_game.php').read_text()
index = (ROOT / 'public/index.php').read_text()
icon_js = (ROOT / 'public/js/mini-game.js').read_text()
lights_js = (ROOT / 'public/js/lights-out.js').read_text()
dash_js = (ROOT / 'public/js/dashboard.js').read_text()
css = (ROOT / 'public/css/mini-game.css').read_text()
run = (ROOT / 'tests/run.sh').read_text()

check("const APP_VERSION = '1.6.0-dev.2';" in version or "const APP_VERSION = '1.6.0-dev.3';" in version or "const APP_VERSION = '1.6.0';" in version or "const APP_VERSION = '1.7.0-dev.1';" in version or "const APP_VERSION = '1.7.0-dev.2';" in version or "const APP_VERSION = '1.7.0-dev.3';" in version or "const APP_VERSION = '1.7.0-dev.4';" in version or "const APP_VERSION = '1.7.0-dev.5';" in version or "const APP_VERSION = '1.7.0-dev.6';" in version or "const APP_VERSION = '1.7.0-dev.7';" in version or "const APP_VERSION = '1.7.0-dev.8';" in version or "const APP_VERSION = '1.7.0-dev.9';" in version or "const APP_VERSION = '1.7.0-dev.10';" in version or "const APP_VERSION = '1.7.0';" in version or is_later_application_release(version, (1, 6, 0)), 'Application version is V1.6-C or later checkpoint')
check('RSS Reader Modernization V1.6-C / R1' in version or 'RSS Reader Modernization V1.6-D / R1' in version or "APP_VERSION_LABEL = 'RSS Reader Modernization 1.6.0'" in version or 'RSS Reader Modernization V1.7-' in version or is_later_visible_label(version, (1, 6, 0)), 'visible label identifies V1.6-C or later checkpoint')
check("return ['icon_quest', 'lights_out'];" in mini_php, 'Game type whitelist includes Lights Out without a new Widget type')
check('value="lights_out"' in index and index.count('Lights Out（5×5 消灯Puzzle）') == 2, 'add and edit forms expose Lights Out')
check('data-mini-game-type="' in index and "if ($gameType === 'lights_out')" in index, 'Dashboard render branches by the existing Game subtype')
check(index.count('data-lights-out-cell-index=') == 1, 'Lights Out cell markup is generated from one bounded 25-cell loop')
check('lights-out-reset' in index and 'lights-out-new-game' in index and 'lights-out-moves' in index, 'Moves, Reset and new problem controls render')
check('lights-out-result' in index and 'CLEAR' in index, 'Clear result is present without a global notification')
check("app_asset_url('js/lights-out.js')" in index or './js/lights-out.js?v=1.6-c-r1' in index or './js/lights-out.js?v=1.6-d-r1' in index, 'Lights Out runtime uses the active Cache Busting strategy')
check(("app_asset_url('css/mini-game.css')" in index and "app_asset_url('js/mini-game.js')" in index) or ('./css/mini-game.css?v=1.6-c-r1' in index and './js/mini-game.js?v=1.6-c-r1' in index) or ('./css/mini-game.css?v=1.6-d-r1' in index and './js/mini-game.js?v=1.6-d-r1' in index), 'changed Game assets use the active Cache Busting strategy')
check("card.getAttribute('data-mini-game-type') === 'lights_out'" in icon_js, 'Icon Quest runtime explicitly leaves Lights Out cards untouched')
check('function applyPress' in lights_js and 'function toggleIndexes' in lights_js, 'press and neighbour rules are explicit')
check('function generatePuzzle' in lights_js and 'board = applyPress(board, randomIndex())' in lights_js, 'problems are generated from the all-off board by valid presses')
check('function isClear' in lights_js and "status = 'cleared'" in lights_js, 'Clear detection is explicit')
check('state.initialBoard' in lights_js and 'function reset' in lights_js, 'Reset uses the original generated board')
check("querySelectorAll('[data-dashboard-widget-type=\"game\"][data-mini-game-type=\"lights_out\"]')" in lights_js, 'multiple Lights Out Widgets initialize independently')
check('innerHTML' not in lights_js and 'textContent' in lights_js, 'runtime updates text without innerHTML')
check('aria-pressed' in index and 'aria-label' in lights_js, 'cell state is exposed through native buttons and ARIA')
check('min-height: 44px' in css and '.lights-out-cell-on' in css, 'Lights Out keeps 44px targets and visible ON state')
check('bootstrap-solar' in css and 'bootstrap-slate' in css, 'dark Themes receive explicit Lights Out contrast')
check('gameDefaultTitle' in dash_js and "gameType === 'lights_out' ? 'Lights Out'" in dash_js, 'selecting the Game subtype keeps its default title consistent')
check(not re.search(r'Notification|vibrate|new Audio|Audio\(', lights_js), 'no sound, vibration or Browser notification was added')
check(not any('v16' in path.name.lower() for path in (ROOT / 'database/migrations').glob('*')), 'V1.6-C adds no Migration')
check('test_v16c_lights_out_runtime.js' in run and 'test_v16c_browser.py' in run, 'main runner includes V1.6-C tests')

if failures:
    raise SystemExit(f'{len(failures)}/{25} V1.6-C architecture checks failed')
print('All V1.6-C architecture checks passed.')
