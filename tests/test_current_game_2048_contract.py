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
runtime = text('public/js/game-2048.js')
style = text('public/css/game-2048.css')
index = text('public/index.php')
stock = text('public/stock.php')

check("'game_2048'" in mini and 'mini_game_widget_validate_type' in mini, '2048 is behind the existing strict Game subtype validator')
check("'game_2048'" in base_runtime, 'Icon Quest runtime explicitly leaves 2048 cards to the dedicated runtime')
for page_name, page in [('Dashboard', index), ('Stock', stock)]:
    check("app_asset_url('css/game-2048.css')" in page, f'{page_name} loads 2048 CSS through the asset helper')
    check("app_asset_url('js/game-2048.js')" in page, f'{page_name} loads 2048 JS through the asset helper')

check("SIZE = 4" in runtime and 'CELL_COUNT = SIZE * SIZE' in runtime, '2048 runtime fixes the board at 4x4')
check("option.value = 'game_2048'" in runtime and 'data-game-preset="game_2048"' in runtime, 'Game selector and catalog expose the 2048 subtype')
check('ArrowLeft' in runtime and 'ArrowRight' in runtime and 'ArrowUp' in runtime and 'ArrowDown' in runtime, 'PC Arrow Key control is present')
check('pointerdown' in runtime and 'pointerup' in runtime and 'SWIPE_THRESHOLD' in runtime, 'Smartphone pointer swipe control is present')
check('Score' in runtime and 'Best' in runtime and 'game-2048-new-game' in runtime and 'game-2048-restart' in runtime, 'Score, Best, New Game and Restart UI are present')
check('localStorage' in runtime and 'sessionStorage' in runtime and 'memoryStorage' in runtime, 'Best Score uses localStorage with browser fallback')
check('touch-action:none' in style.replace(' ', ''), '2048 board captures deliberate smartphone swipes')
check('min-height:44px' in style.replace(' ', ''), '2048 controls keep coarse-pointer-friendly button height')
check('@media(max-width:575.98px)' in style.replace(' ', ''), '2048 has smartphone responsive CSS')
check(all(token not in runtime for token in ['fetch(', 'XMLHttpRequest', '$.ajax(']), '2048 makes no network request')
check(all('game_2048' not in path.read_text(encoding='utf-8', errors='ignore') for path in (ROOT / 'database').rglob('*.sql')), '2048 adds no database migration or schema dependency')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
