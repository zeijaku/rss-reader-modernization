from pathlib import Path
import shutil,sys
ROOT=Path(__file__).resolve().parents[1];CSS=ROOT/'public/css/mini-game.css';JS=ROOT/'public/js/mini-game.js';checks=[]
def check(ok,msg): checks.append(bool(ok)); print(('PASS' if ok else 'FAIL')+': '+msg)
try: from playwright.sync_api import sync_playwright
except Exception: print('SKIP: Playwright unavailable.'); raise SystemExit(0)
chromium=shutil.which('chromium') or shutil.which('google-chrome')
if not chromium: print('SKIP: Chromium unavailable.'); raise SystemExit(0)
def card(w):
 cells=''.join(f'<button class="mini-game-cell" data-mini-game-cell-index="{i}" tabindex="{0 if i==0 else -1}" aria-label="cell {i}"></button>' for i in range(25))
 return f'''<section class="mini-game-card" data-dashboard-widget-id="{w}" data-dashboard-widget-type="game"><div class="mini-game-card-inner"><div class="mini-game-card-body"><div class="mini-game-summary"><b class="mini-game-level"></b><b class="mini-game-moves"></b><b class="mini-game-best"></b><b class="mini-game-treasure-state"></b><b class="mini-game-enemy-turn"></b><b><span class="mini-game-wins"></span>/<span class="mini-game-losses"></span></b></div><div class="mini-game-board">{cells}</div><div class="mini-game-result" hidden aria-hidden="true"><i class="mini-game-result-icon"></i><strong class="mini-game-result-text"></strong></div><p class="mini-game-status"></p><div class="mini-game-controls"><div class="mini-game-action-buttons"><button class="mini-game-new-game">New</button><button class="mini-game-reset">Reset</button></div><div class="mini-game-dpad"><button class="mini-game-direction mini-game-direction-up" data-mini-game-direction="up">U</button><button class="mini-game-direction mini-game-direction-left" data-mini-game-direction="left">L</button><button class="mini-game-direction mini-game-direction-down" data-mini-game-direction="down">D</button><button class="mini-game-direction mini-game-direction-right" data-mini-game-direction="right">R</button></div></div><div class="mini-game-tools"><button class="mini-game-tutorial-toggle" aria-expanded="false" aria-controls="tutorial-{w}">Help</button><button class="mini-game-storage-reset">Delete</button></div><div class="mini-game-tutorial" id="tutorial-{w}" hidden>How to play</div><p class="mini-game-storage-note"></p></div></div></section>'''
html=f'<main id="main-content" data-dashboard-user-id="77" data-dashboard-theme="bootstrap-solar">{card(101)}{card(102)}</main>'
storage="""() => {const v={};const s={getItem:k=>v[k]||null,setItem:(k,x)=>v[k]=String(x),removeItem:k=>delete v[k]};Object.defineProperty(window,'localStorage',{value:s,configurable:true});Object.defineProperty(window,'sessionStorage',{value:s,configurable:true});window.__v=v;}"""
with sync_playwright() as p:
 b=p.chromium.launch(executable_path=chromium,headless=True,args=['--no-sandbox']);page=b.new_page(viewport={'width':360,'height':1200});page.on('dialog',lambda d:d.accept());page.set_content(html);page.add_style_tag(path=str(CSS));page.evaluate(storage);page.add_script_tag(path=str(JS));page.wait_for_function("document.querySelectorAll('[data-mini-game-initialized=\"1\"]').length===2");first=page.locator('.mini-game-card').first
 check(page.evaluate('document.documentElement.scrollWidth<=document.documentElement.clientWidth'),'360px has no horizontal overflow')
 check(first.locator('.mini-game-tutorial').is_visible(),'Tutorial opens for a new Widget')
 first.locator('.mini-game-tutorial-toggle').click();check(first.locator('.mini-game-tutorial-toggle').get_attribute('aria-expanded')=='false','Tutorial toggle closes the panel')
 check(page.evaluate("JSON.parse(Object.values(window.__v).find(v=>v.includes('tutorialSeen'))).tutorialSeen") is True,'Tutorial seen state saves per Widget')
 before=first.locator('.mini-game-moves').inner_text();first.locator('[data-mini-game-cell-index="0"]').dispatch_event('keydown',{'key':'ArrowRight','repeat':True});check(first.locator('.mini-game-moves').inner_text()==before,'repeated Key event is ignored')
 page.evaluate("""() => {const b=document.querySelector('.mini-game-direction-right');b.dispatchEvent(new MouseEvent('click',{bubbles:true,detail:0}));b.dispatchEvent(new MouseEvent('click',{bubbles:true,detail:0}));}""")
 check(first.locator('.mini-game-moves').inner_text()=='1 / 20','rapid keyboard activation moves only once')
 first.locator('.mini-game-reset').click()
 sel={'R':'.mini-game-direction-right','D':'.mini-game-direction-down'}
 for ch in 'RDDRRDRD': first.locator(sel[ch]).click()
 check(first.get_attribute('data-mini-game-status')=='won','Level clears through controls')
 check(first.locator('.mini-game-result').is_visible() and first.locator('.mini-game-result-text').inner_text()=='CLEAR','Clear result is visibly displayed')
 check(first.locator('.mini-game-wins').inner_text()=='1','win record is displayed')
 first.locator('.mini-game-storage-reset').click();check(first.locator('.mini-game-moves').inner_text()=='0 / 20' and first.locator('.mini-game-best').inner_text()=='--','record reset clears progress and Best')
 check(first.locator('.mini-game-wins').inner_text()=='0' and first.locator('.mini-game-losses').inner_text()=='0','record reset clears win/loss statistics')
 check(first.locator('.mini-game-tutorial').is_visible(),'record reset restores first-use Tutorial')
 page.evaluate("""() => {const s=RssMiniGame.defaultState();s.player=4;s.enemy=4;s.status='lost';s.resultReason='enemy';s.stats.losses=1;RssMiniGame.saveState('77','101',s);const c=document.querySelector('.mini-game-card');c.removeAttribute('data-mini-game-initialized');RssMiniGame.init();}""")
 check(first.locator('.mini-game-result').is_visible() and first.locator('.mini-game-result-text').inner_text()=='GAME OVER','Game Over result is visibly displayed')
 check(first.locator('.mini-game-losses').inner_text()=='1','loss record is displayed')
 tool_box=first.locator('.mini-game-storage-reset').bounding_box();check(tool_box and tool_box['height']>=44,'record reset keeps a 44px target')
 bg=first.locator('.mini-game-card-inner').evaluate("e=>getComputedStyle(e).backgroundColor");check(bg!='rgb(255, 255, 255)','Solar Theme uses dark Game surface')
 b.close()
failed=len(checks)-sum(checks);print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0');sys.exit(1 if failed else 0)
