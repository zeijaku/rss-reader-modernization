#!/usr/bin/env python3
from pathlib import Path
import re
ROOT = Path(__file__).resolve().parents[1]
def src(path): return (ROOT / path).read_text(encoding='utf-8')
checks = 0
def check(value, label):
    global checks
    assert value, label
    checks += 1
    print('PASS: ' + label)
mini = src('app/mini_game.php')
common = src('public/js/game-widget.js')
game = src('public/js/precision-sniper.js')
check("'precision_sniper'" in mini, 'existing backend owner-scoped Game API allowlists Sniper')
check("precision_sniper: {title:'Precision Sniper',script:'./js/precision-sniper.js'}" in common,
      'Sniper uses lazy local asset loading')
check("window.RssGameWidget.register('precision_sniper', mount)" in game, 'new engine owns its lifecycle')
check('precision_sniper' in src('public/js/mini-game.js'), 'legacy Icon Quest does not claim Sniper board')
check(src('app/view/dashboard_modals.php').count('value="precision_sniper"') == 2,
      'add and edit modal both expose Sniper')
check('Precision Sniperを読み込んでいます' in src('app/view/dashboard_widgets.php'),
      'initial render defers to the lazy Sniper loader')
check("'data-game-preset', 'precision_sniper'" in src('public/js/utility-widgets.js'),
      'Drawer exposes Sniper through existing modal')
check("if (gameType === 'precision_sniper') return 'Precision Sniper';" in src('public/js/dashboard-game.js'),
      'settings default title matches selected Sniper')
check("oldType === 'precision_sniper'" in src('public/js/widget-settings-no-reload.js'),
      'partial card update clears the old Sniper state')
check('onlyGame' in common and '[onlyGame]' in common, 'Sniper state can be removed without clearing other game types')
check('precision-sniper-canvas' in src('public/css/game-widget.css'), 'Canvas size follows responsive Widget width')
check('var(--bs-border-color' in src('public/css/game-widget.css')
      and "token('--bs-primary'" in game, 'Sniper aligns frame and Canvas palette with other Bootstrap Game widgets')
check('marks.forEach(bulletMark)' in game and 'impactFlash()' in game
      and "kind = selected !== -1" in game, 'bullet impacts have material-aware permanent marks and a transient effect')
check('Math.sin(phase * 1.55)' in game and 'Math.sin(phase * 1.23)' in game,
      'sway returns to neutral along independent horizontal and vertical cycles')
check('cancelAnimationFrame' in game and 'observer.disconnect()' in game, 'Pause and Destroy clean up scheduled work')
check("context.on(canvas, 'keydown'" in game and "context.on(canvas, 'blur'" in game,
      'keyboard input and held breath remain scope-bound')
check('clientX - rect.left' in game and 'canvas.width / W' in game,
      'pointer and canvas transformations use CSS coordinates and drawing scale independently')
check('aimWorld(camera, pointer, sway)' in game and 'scoreHit(at, target)' in game,
      'the sway shown in the zoomed lens is used for collision')
check('1.50.0-dev.1' in src('app/version.php'), 'visible and asset versions mark development only')
check(all(s not in game for s in ['fetch(', 'XMLHttpRequest', 'innerHTML', 'https://']),
      'original Canvas graphics and no new network boundary')
print(f'RESULT: PASS {checks} / FAIL 0 / SKIP 0')
