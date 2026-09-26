#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []

def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

def text(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')

mini = text('app/mini_game.php')
base_runtime = text('public/js/mini-game.js')
runtime = text('public/js/reversi.js')
style = text('public/css/reversi.css')
index = text('public/index.php')
stock = text('public/stock.php')

check("'reversi'" in mini and 'mini_game_widget_validate_type' in mini, 'Reversi is behind the existing strict Game subtype validator')
check("'reversi'" in base_runtime, 'Icon Quest runtime explicitly leaves Reversi cards to the dedicated runtime')
for page_name, page in [('Dashboard', index), ('Stock', stock)]:
    check("app_asset_url('css/reversi.css')" in page, f'{page_name} loads Reversi CSS through the asset helper')
    check("app_asset_url('js/reversi.js')" in page, f'{page_name} loads Reversi JS through the asset helper')

check("SIZE = 8" in runtime and 'CELL_COUNT = SIZE * SIZE' in runtime, 'Reversi runtime fixes the board at 8x8')
check("option.value = 'reversi'" in runtime and 'data-game-preset="reversi"' in runtime, 'Game selector and catalog expose Reversi')
check('DIRECTIONS' in runtime and 'flipsForMove' in runtime and 'legalMoves' in runtime, 'Reversi validates moves across all eight directions')
check('chooseCpuMove' in runtime and 'POSITION_WEIGHTS' in runtime and 'opponentMobility' in runtime, 'CPU uses a lightweight positional / mobility heuristic')
check('CPU_DELAY_MS = 280' in runtime, 'CPU response uses a short local delay without deep search')
check('Pass' in runtime and 'finishGame' in runtime, 'Pass and game-over flows are implemented')
check('reversi-black-count' in runtime and 'reversi-white-count' in runtime and 'reversi-turn' in runtime, 'stone counts and active turn are visible')
check("addEventListener('click'" in runtime and 'SmartphoneはTap' in runtime, 'same board supports PC click and smartphone tap')
check('min-height:44px' in style.replace(' ', ''), 'Restart control keeps coarse-pointer-friendly height')
check('@media(max-width:575.98px)' in style.replace(' ', ''), 'Reversi has smartphone responsive CSS')
check('radial-gradient' not in style and '#174a27' not in style and '#2f8f4e' not in style, 'Reversi avoids the old realistic green-board / shaded-disc treatment')
check('rgba(var(--bs-body-color-rgb,33,37,41),.09)' in style.replace(' ', ''), 'Reversi board uses the same neutral Bootstrap-derived surface language as existing games')
check('background:var(--bs-primary,#0d6efd)' in style.replace(' ', ''), 'legal move marker uses the shared Bootstrap primary accent')
check('box-shadow' not in style, 'Reversi discs stay flat without realistic drop shadows')
check(all(token not in runtime for token in ['fetch(', 'XMLHttpRequest', '$.ajax(']), 'Reversi CPU makes no network request')
check('localStorage' not in runtime and 'sessionStorage' not in runtime, 'Reversi keeps game state in memory and adds no browser persistence dependency')
check(all('reversi' not in path.read_text(encoding='utf-8', errors='ignore') for path in (ROOT / 'database').rglob('*.sql')), 'Reversi adds no database migration or schema dependency')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
