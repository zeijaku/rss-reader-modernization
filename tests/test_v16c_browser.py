from pathlib import Path
import os
from playwright.sync_api import sync_playwright

ROOT=Path(__file__).resolve().parents[1]
CSS=ROOT/'public/css/mini-game.css';JS=ROOT/'public/js/lights-out.js'
failures=[];checks=0
def check(condition,message):
    global checks;checks+=1;print(('PASS' if condition else 'FAIL')+': '+message)
    if not condition:failures.append(message)

cells=''.join(f'<button type="button" class="mini-game-cell lights-out-cell" data-lights-out-cell-index="{i}"><span></span></button>' for i in range(25))
def card(widget):
    return f'''<section class="dashboard-widget mini-game-card" data-dashboard-widget-id="{widget}" data-dashboard-widget-type="game" data-mini-game-type="lights_out"><div class="mini-game-card-inner"><div class="mini-game-card-body"><div class="lights-out-summary"><span>Moves</span><strong class="lights-out-moves">0</strong></div><div class="mini-game-board lights-out-board">{cells}</div><div class="mini-game-result lights-out-result" hidden aria-hidden="true"><strong class="mini-game-result-text">CLEAR</strong></div><div class="mini-game-status-row"><p class="mini-game-status lights-out-status"></p></div><div class="lights-out-controls"><button class="lights-out-reset">Reset</button><button class="lights-out-new-game">新しい問題</button></div></div></div></section>'''
html=f'<main id="main-content" data-dashboard-theme="bootstrap-solar">{card(1)}{card(2)}</main>'
chromium=os.environ.get('CHROMIUM_PATH','/usr/bin/chromium')
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path=chromium,headless=True,args=['--no-sandbox'])
    page=browser.new_page(viewport={'width':360,'height':1100})
    page.set_content(html);page.add_style_tag(path=str(CSS));page.add_script_tag(path=str(JS))
    page.wait_for_function("document.querySelectorAll('[data-lights-out-initialized=\"1\"]').length===2")
    check(page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth'),'360px layout has no horizontal overflow')
    first=page.locator('.mini-game-card').first
    initial=first.locator('.lights-out-cell-on').count()
    check(initial>0,'new puzzle starts with at least one lit cell')
    board_before=first.locator('.lights-out-cell').evaluate_all("els=>els.map(e=>e.getAttribute('aria-pressed'))")
    first.locator('[data-lights-out-cell-index="12"]').click()
    board_after=first.locator('.lights-out-cell').evaluate_all("els=>els.map(e=>e.getAttribute('aria-pressed'))")
    changed=sum(a!=b for a,b in zip(board_before,board_after))
    check(changed==5 and first.locator('.lights-out-moves').inner_text()=='1','center click toggles five cells and increments Moves')
    first.locator('.lights-out-reset').click()
    reset_board=first.locator('.lights-out-cell').evaluate_all("els=>els.map(e=>e.getAttribute('aria-pressed'))")
    check(reset_board==board_before and first.locator('.lights-out-moves').inner_text()=='0','Reset restores the generated board and Moves')
    second_before=page.locator('.mini-game-card').nth(1).locator('.lights-out-cell').evaluate_all("els=>els.map(e=>e.getAttribute('aria-pressed'))")
    first.locator('[data-lights-out-cell-index="0"]').click()
    second_after=page.locator('.mini-game-card').nth(1).locator('.lights-out-cell').evaluate_all("els=>els.map(e=>e.getAttribute('aria-pressed'))")
    check(second_before==second_after,'multiple Lights Out Widgets keep separate runtime state')
    page.evaluate("""card=>{const g=window.RssLightsOut;const b=g.applyPress(g.emptyBoard(),12);card.__rssLightsOutState={board:b.slice(),initialBoard:b.slice(),moves:0,status:'playing'};card.querySelector('[data-lights-out-cell-index="12"]').click();}""", first.element_handle())
    check(first.get_attribute('data-lights-out-status')=='cleared','last valid press changes the state to Clear')
    check(not first.locator('.lights-out-result').is_hidden() and 'Clear' in first.locator('.lights-out-status').inner_text(),'Clear result and status are shown inside the Widget')
    check(first.locator('.lights-out-cell').first.is_disabled(),'board input is disabled after Clear')
    first.locator('.lights-out-new-game').click()
    check(first.get_attribute('data-lights-out-status')=='playing' and first.locator('.lights-out-moves').inner_text()=='0','new problem starts a fresh playable board')
    check(first.locator('.lights-out-cell').first.evaluate("e=>getComputedStyle(e).minHeight") in ('44px','44'),'cell interaction target remains 44px')
    check(first.locator('.lights-out-cell-on').first.evaluate("e=>getComputedStyle(e).backgroundColor")!='rgba(0, 0, 0, 0)','dark Theme keeps the ON state visibly styled')
    browser.close()
if failures:raise SystemExit(1)
print(f'RESULT: PASS {checks} / FAIL 0 / SKIP 0')
