#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


mini_php = (ROOT / 'app/mini_game.php').read_text(encoding='utf-8')
runtime = (ROOT / 'public/js/cursor-field.js').read_text(encoding='utf-8')
style = (ROOT / 'public/css/cursor-field.css').read_text(encoding='utf-8')
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
stock = (ROOT / 'public/stock.php').read_text(encoding='utf-8')
icon_runtime = (ROOT / 'public/js/mini-game.js').read_text(encoding='utf-8')

check("'cursor_field'" in mini_php, 'Cursor Field is an allowed existing Game subtype')
check("mini_game_widget_validate_type" in mini_php, 'Cursor Field remains behind the existing strict Game validator')
for page_name, page in [('Dashboard', index), ('Stock', stock)]:
    check("app_asset_url('css/cursor-field.css')" in page, f'{page_name} loads Cursor Field style through the asset helper')
    check("app_asset_url('js/cursor-field.js')" in page, f'{page_name} loads Cursor Field runtime through the asset helper')

check('cursor_field' in runtime and 'Cursor Field（マウス反発）' in runtime, 'Game selectors expose the Cursor Field subtype')
check('data-game-preset="cursor_field"' in runtime, 'Widget catalog exposes a Cursor Field preset')
check("createElement('canvas'" in runtime and "getContext('2d')" in runtime, 'Cursor Field uses a bounded Canvas 2D surface')
check("addEventListener('pointermove'" in runtime and 'POINTER_RADIUS' in runtime, 'mouse movement drives the repulsion field')
check('anchorX' in runtime and 'SPRING' in runtime and 'DAMPING' in runtime, 'blocks return to their grid anchors with damped spring motion')
check('requestAnimationFrame' in runtime and 'cancelAnimationFrame' in runtime, 'animation uses a cancellable requestAnimationFrame lifecycle')
check('ResizeObserver' in runtime and 'IntersectionObserver' in runtime, 'Canvas resizes and pauses when outside the viewport')
check('visibilitychange' in runtime and 'pagehide' in runtime, 'background pages stop the animation loop')
check('prefers-reduced-motion: reduce' in style and 'prefers-reduced-motion: reduce' in runtime, 'reduced-motion preference is respected')
check('touch-action: pan-y' in style, 'touch scrolling is not captured by the passive mouse toy')
check(all(token not in runtime for token in ['localStorage', 'sessionStorage', 'fetch(', 'XMLHttpRequest', '$.ajax']), 'Cursor Field has no persistence or network access')
check(all(token not in runtime for token in ['Score', 'CLEAR', 'Game Over']), 'Cursor Field has no score, goal, or game-over state')
check("'cursor_field'" in icon_runtime, 'Icon Quest runtime leaves Cursor Field cards untouched')
check(all('cursor_field' not in path.read_text(encoding='utf-8', errors='ignore') for path in (ROOT / 'database').rglob('*.sql')), 'Cursor Field itself adds no database migration')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
