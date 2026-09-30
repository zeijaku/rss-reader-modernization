from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def text(p): return (ROOT/p).read_text()
mini=text('app/mini_game.php'); common=text('public/js/game-widget.js'); maze=text('public/js/maze-chase.js')
checks=0
def check(value,message):
 global checks
 assert value,message
 checks+=1; print('PASS: '+message)
check("'maze_chase'" in mini,'Maze Chase uses the existing strict subtype validator')
check("'game', NULL" in mini,'shared Game persistence retains NULL reference and multiple placement')
for page in ['public/index.php','public/stock.php']:
 source=text(page)
 check("app_asset_url('js/game-widget.js')" in source,'versioned Game entry in '+page)
 check("app_asset_url('js/maze-chase.js')" not in source,'Maze module is not eagerly included in '+page)
 check("app_asset_url('css/game-widget.css')" in source,'local scoped style in '+page)
check('document.currentScript' in common and 'encodeURIComponent(revision)' in common,'child assets inherit the entry revision')
check("script: './js/maze-chase.js'" in common,'local fixed module path is allowlisted')
check('localStorage' in common and 'sessionStorage' in common and "storage:'memory'" in common,'best has browser-storage fallbacks')
check('destroy' in common and 'removeEventListener' in common and 'intersection.disconnect()' in common,'common lifecycle removes listeners and observers')
check('cancelAnimationFrame' in maze and 'resizeObserver.disconnect()' in maze,'module lifecycle stops animation and resize observer')
check('event.preventDefault(); event.stopPropagation()' in maze and "context.on(canvas,'keydown'" in maze,'game keys are captured only inside the focused board')
check("'maze_chase', 'falling_blocks', 'word_tiles'].indexOf" in text('public/js/mini-game.js'),'legacy Icon Quest leaves Maze cards untouched')
check('data-game-direction' in maze and 'min-height:44px' in text('public/css/game-widget.css'),'touch buttons maintain accessible size')
check(all(word not in maze for word in ['fetch(', 'XMLHttpRequest', '$.ajax(', 'https://']),'Maze engine has no external or API dependency')
check('innerHTML' not in common and 'innerHTML' not in maze,'new UI uses text and DOM nodes rather than HTML interpolation')
check("catalogButton('#registerGameWidget', 'Maze Chase', 'fas fa-route').attr('data-game-preset', 'maze_chase')" in text('public/js/utility-widgets.js'),'production Drawer catalog registers Maze Chase with the existing add-modal target')
check("catalog[button.getAttribute('data-game-preset')]" in common and 'select.value = game' in common,'shared menu preset accepts only allowlisted games')
check("'falling_blocks'" in mini and "script: './js/falling-blocks.js'" in common,'Falling Blocks uses the existing validator and local lazy catalog')
check("catalogButton('#registerGameWidget', 'Falling Blocks'" in text('public/js/utility-widgets.js'),'production Drawer registers Falling Blocks')
for page in ['public/index.php','public/stock.php']:
 check("app_asset_url('js/falling-blocks.js')" not in text(page),'Falling Blocks is not eagerly loaded in '+page)
fall=text('public/js/falling-blocks.js')
check('innerHTML' not in fall and all(x not in fall for x in ['fetch(', 'XMLHttpRequest', 'https://']),'Falling UI and engine keep the existing security boundary')
check("context.on(canvas,'keydown'" in fall and 'event.stopPropagation()' in fall and 'event.repeat' in fall,'Falling keyboard is focus-scoped and repeated Hard Drop is suppressed')
check('cancelAnimationFrame' in fall and 'resizeObserver.disconnect()' in fall,'Falling lifecycle cancels animation and resize observation')
word=text('public/js/word-tiles.js')
check("'word_tiles'" in mini and "script: './js/word-tiles.js'" in common,'Word Tiles uses the existing subtype validator and local lazy catalog')
check("catalogButton('#registerGameWidget', 'Word Tiles'" in text('public/js/utility-widgets.js'),'production Drawer registers Word Tiles')
check("record.game === 'word_tiles'" in common and "key + '.state'" in common,'Word state key and cleanup are scoped to that subtype')
check('parseState' in word and 'raw.length>16384' in word and 'SCHEMA' in word and 'WORDLIST' in word,'saved Word state is bounded and schema/dictionary checked')
check('innerHTML' not in word and all(x not in word for x in ['fetch(', 'XMLHttpRequest', 'https://','requestAnimationFrame']),'Word uses safe DOM, fixed local dictionary and no animation loop')
for page in ['public/index.php','public/stock.php']:
 check("app_asset_url('js/word-tiles.js')" not in text(page),'Word engine is not eagerly loaded in '+page)
print(f'RESULT: PASS {checks} / FAIL 0 / SKIP 0')
