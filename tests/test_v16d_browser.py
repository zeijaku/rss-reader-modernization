from pathlib import Path
import json
import os
try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('SKIP: Playwright Python package is unavailable.')
    raise SystemExit(0)

ROOT = Path(__file__).resolve().parents[1]
CSS = (ROOT / 'public/css/mini-game.css').read_text()
JS = (ROOT / 'public/js/lights-out.js').read_text()
failures = []
checks = 0

def check(condition, message):
    global checks
    checks += 1
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        failures.append(message)

cells = ''.join(
    f'<button type="button" class="mini-game-cell lights-out-cell" role="gridcell" '
    f'aria-rowindex="{i // 5 + 1}" aria-colindex="{i % 5 + 1}" aria-label="{i // 5 + 1}行{i % 5 + 1}列、消灯" '
    f'aria-pressed="false" data-lights-out-cell-index="{i}" tabindex="{0 if i == 0 else -1}"><span aria-hidden="true"></span></button>'
    for i in range(25)
)

def card(widget):
    return f'''<section class="dashboard-widget mini-game-card" data-dashboard-widget-id="{widget}" data-dashboard-widget-type="game" data-mini-game-type="lights_out">
      <div class="mini-game-card-inner"><div class="mini-game-card-body">
        <div class="lights-out-summary"><span>Moves</span><strong class="lights-out-moves">0</strong></div>
        <div class="mini-game-board lights-out-board" role="grid" aria-label="Lights Out 5×5盤面">{cells}</div>
        <div class="mini-game-result lights-out-result" hidden aria-hidden="true"><strong class="mini-game-result-text">CLEAR</strong></div>
        <div class="mini-game-status-row"><p class="mini-game-status lights-out-status" aria-live="polite" aria-atomic="true"></p></div>
        <div class="lights-out-controls"><button type="button" class="lights-out-reset">Reset</button><button type="button" class="lights-out-new-game">新しい問題</button></div>
        <p class="mini-game-storage-note text-muted">進行状態を確認しています...</p>
      </div></div></section>'''

html = f'''<!doctype html><html><head><meta charset="utf-8"><style>{CSS}</style></head><body>
<main id="main-content" data-dashboard-user-id="7" data-dashboard-theme="bootstrap-solar">{card(41)}{card(42)}</main>
</body></html>'''

storage_bootstrap = """([localValues, sessionValues]) => {
    const makeStorage = values => ({
        getItem(key) { return Object.prototype.hasOwnProperty.call(values, key) ? values[key] : null; },
        setItem(key, value) { values[key] = String(value); },
        removeItem(key) { delete values[key]; }
    });
    window.__testLocalValues = localValues;
    window.__testSessionValues = sessionValues;
    Object.defineProperty(window, 'localStorage', {configurable: true, value: makeStorage(localValues)});
    Object.defineProperty(window, 'sessionStorage', {configurable: true, value: makeStorage(sessionValues)});
}"""

def load_runtime(page, local_values=None, session_values=None):
    page.set_content(html)
    page.evaluate(storage_bootstrap, [local_values or {}, session_values or {}])
    page.add_script_tag(content=JS)
    page.wait_for_function("document.querySelectorAll('[data-lights-out-initialized=\"1\"]').length===2")

chromium = os.environ.get('CHROMIUM_PATH', '/usr/bin/chromium')
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=chromium, headless=True, args=['--no-sandbox'])
    context = browser.new_context(viewport={'width': 360, 'height': 1100})
    page = context.new_page()
    load_runtime(page)

    first = page.locator('.mini-game-card').first
    second = page.locator('.mini-game-card').nth(1)
    check(page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth'), '360px layout has no horizontal overflow')
    check(first.get_attribute('data-lights-out-storage-mode') == 'localStorage', 'normal Browser uses localStorage')
    check('この端末' in first.locator('.mini-game-storage-note').inner_text(), 'Storage mode is explained inside the Widget')

    board_before = first.locator('.lights-out-cell').evaluate_all("els=>els.map(e=>e.getAttribute('aria-pressed'))")
    second_before = second.locator('.lights-out-cell').evaluate_all("els=>els.map(e=>e.getAttribute('aria-pressed'))")
    first.locator('[data-lights-out-cell-index="12"]').click()
    moved_board = first.locator('.lights-out-cell').evaluate_all("els=>els.map(e=>e.getAttribute('aria-pressed'))")
    check(first.locator('.lights-out-moves').inner_text() == '1', 'Moves increments before persistence')
    check(sum(a != b for a, b in zip(board_before, moved_board)) == 5, 'center press toggles five cells before persistence')

    key_41 = 'rssReader.miniGame.lightsOut.v1.user.7.widget.41'
    key_42 = 'rssReader.miniGame.lightsOut.v1.user.7.widget.42'
    stored = page.evaluate("([a,b])=>[window.__testLocalValues[a],window.__testLocalValues[b]]", [key_41, key_42])
    check(all(stored), 'each Lights Out Widget writes a separate Storage entry')
    check(json.loads(stored[0])['moves'] == 1 and json.loads(stored[1])['moves'] == 0, 'stored Moves remain independent per Widget')

    local_values = page.evaluate('window.__testLocalValues')
    session_values = page.evaluate('window.__testSessionValues')
    load_runtime(page, local_values, session_values)
    first = page.locator('.mini-game-card').first
    second = page.locator('.mini-game-card').nth(1)
    restored_board = first.locator('.lights-out-cell').evaluate_all("els=>els.map(e=>e.getAttribute('aria-pressed'))")
    restored_second = second.locator('.lights-out-cell').evaluate_all("els=>els.map(e=>e.getAttribute('aria-pressed'))")
    check(restored_board == moved_board and first.locator('.lights-out-moves').inner_text() == '1', 'board and Moves restore after reload-equivalent initialization')
    check(restored_second == second_before, 'second Widget restores its own board')

    first.locator('.lights-out-reset').click()
    reset_board = first.locator('.lights-out-cell').evaluate_all("els=>els.map(e=>e.getAttribute('aria-pressed'))")
    check(reset_board == board_before and first.locator('.lights-out-moves').inner_text() == '0', 'Reset restores the saved initial board and persists zero Moves')

    first.locator('[data-lights-out-cell-index="0"]').focus()
    page.keyboard.press('ArrowRight')
    check(page.evaluate("document.activeElement.getAttribute('data-lights-out-cell-index')") == '1', 'ArrowRight moves keyboard focus one cell')
    page.keyboard.press('End')
    check(page.evaluate("document.activeElement.getAttribute('data-lights-out-cell-index')") == '4', 'End moves keyboard focus to row end')
    page.keyboard.press('ArrowDown')
    check(page.evaluate("document.activeElement.getAttribute('data-lights-out-cell-index')") == '9', 'ArrowDown moves keyboard focus one row')
    check(first.locator('[data-lights-out-cell-index="9"]').get_attribute('tabindex') == '0', 'roving tabindex leaves one keyboard target')
    check(first.locator('[data-lights-out-cell-index="9"]').evaluate("e=>getComputedStyle(e).outlineStyle") != 'none', 'keyboard Focus is visibly styled')

    page.evaluate("key=>window.__testLocalValues[key]='{broken'", key_41)
    local_values = page.evaluate('window.__testLocalValues')
    session_values = page.evaluate('window.__testSessionValues')
    load_runtime(page, local_values, session_values)
    first = page.locator('.mini-game-card').first
    check(first.locator('.lights-out-moves').inner_text() == '0', 'broken Storage resets Moves safely')
    check('復旧' in first.locator('.mini-game-storage-note').inner_text(), 'broken Storage recovery is explained')
    check(page.evaluate("key=>JSON.parse(window.__testLocalValues[key]).game", key_41) == 'lights_out', 'broken Storage is replaced with valid state')

    page.evaluate("""card=>{const g=window.RssLightsOut;const initial=g.applyPress(g.emptyBoard(),12);const state=g.createState(initial);card.__rssLightsOutState=state;g.saveState('7',card.getAttribute('data-dashboard-widget-id'),state);card.querySelector('[data-lights-out-cell-index="12"]').click();}""", first.element_handle())
    check(first.get_attribute('data-lights-out-status') == 'cleared', 'Clear state is reached and saved')
    check(page.evaluate("key=>JSON.parse(window.__testLocalValues[key]).status", key_41) == 'cleared', 'Clear state persists to Storage')
    check(page.evaluate("document.activeElement.classList.contains('lights-out-reset')"), 'focus moves to Reset after Clear')

    local_values = page.evaluate('window.__testLocalValues')
    session_values = page.evaluate('window.__testSessionValues')
    load_runtime(page, local_values, session_values)
    first = page.locator('.mini-game-card').first
    check(first.get_attribute('data-lights-out-status') == 'cleared' and not first.locator('.lights-out-result').is_hidden(), 'Clear result restores after reload-equivalent initialization')
    check(first.locator('.lights-out-cell').first.is_disabled(), 'restored Clear board remains inactive')
    check(first.locator('.lights-out-cell-on').count() == 0, 'restored Clear board is fully unlit')
    check(first.locator('.lights-out-cell').first.evaluate("e=>getComputedStyle(e).minHeight") in ('44px', '44'), 'cell interaction target remains 44px')
    check(first.locator('.lights-out-cell').first.evaluate("e=>getComputedStyle(e).backgroundColor") != 'rgba(0, 0, 0, 0)', 'Dark Theme keeps the board visibly styled')

    reduced = browser.new_context(viewport={'width': 360, 'height': 800}, reduced_motion='reduce')
    reduced_page = reduced.new_page()
    load_runtime(reduced_page)
    duration = reduced_page.locator('.lights-out-cell').first.evaluate("e=>getComputedStyle(e).transitionDuration")
    check(duration in ('0.00001s', '1e-05s', '0s'), 'Reduced Motion suppresses visible transition duration')
    reduced.close()
    browser.close()

if failures:
    raise SystemExit(1)
print(f'RESULT: PASS {checks} / FAIL 0 / SKIP 0')
