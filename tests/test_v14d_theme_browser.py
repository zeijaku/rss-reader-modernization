from pathlib import Path
import re,shutil,sys
ROOT=Path(__file__).resolve().parents[1];CSS=ROOT/'public/css/mini-game.css';checks=[]
THEMES=[('bootstrap.min.css','bootstrap'),('bootstrap-yeti.min.css','bootstrap-yeti'),('bootstrap-minty.min.css','bootstrap-minty'),('bootstrap-flatly.min.css','bootstrap-flatly'),('bootstrap-journal.min.css','bootstrap-journal'),('bootstrap-sketchy.min.css','bootstrap-sketchy'),('bootstrap-solar.min.css','bootstrap-solar'),('bootstrap-slate.min.css','bootstrap-slate')]
def check(ok,msg): checks.append(bool(ok)); print(('PASS' if ok else 'FAIL')+': '+msg)
try: from playwright.sync_api import sync_playwright
except Exception: print('SKIP: Playwright unavailable.'); raise SystemExit(0)
chromium=shutil.which('chromium') or shutil.which('google-chrome')
if not chromium: print('SKIP: Chromium unavailable.'); raise SystemExit(0)
cells=''.join(f'<button class="mini-game-cell {"mini-game-cell-player" if i==0 else "mini-game-cell-floor"}" data-mini-game-cell-index="{i}"><span>·</span></button>' for i in range(25))
body='''<section class="col-12 col-sm-6 col-lg-3 mini-game-card"><div class="mini-game-card-inner"><div class="mini-game-card-header bg-secondary"><span class="mini-game-title text-white">Icon Quest</span></div><div class="mini-game-card-body"><div class="mini-game-summary"><span>Level</span><strong>Level 1</strong><span>Moves</span><strong>0 / 20</strong></div><div class="mini-game-board">CELLS</div><div class="mini-game-result mini-game-result-won"><strong>CLEAR</strong></div><div class="mini-game-controls"><button class="btn btn-outline-primary mini-game-new-game">New Game</button><div class="mini-game-dpad"><button class="btn btn-outline-secondary mini-game-direction mini-game-direction-up">U</button><button class="btn btn-outline-secondary mini-game-direction mini-game-direction-left">L</button><button class="btn btn-outline-secondary mini-game-direction mini-game-direction-down">D</button><button class="btn btn-outline-secondary mini-game-direction mini-game-direction-right">R</button></div></div><div class="mini-game-tools"><button class="btn btn-outline-info mini-game-tutorial-toggle">遊び方</button><button class="btn btn-outline-danger mini-game-storage-reset">記録を削除</button></div><div class="mini-game-tutorial">Treasureを取ってGoalへ進みます。</div><p class="mini-game-storage-note">進行状態は保存されます</p></div></div></section>'''.replace('CELLS',cells)
contrast_js="""el=>{function rgb(s){const m=s.match(/[\\d.]+/g).map(Number);return m.slice(0,3)}function lum(c){return c.map(v=>{v/=255;return v<=.03928?v/12.92:Math.pow((v+.055)/1.055,2.4)}).reduce((a,v,i)=>a+v*[.2126,.7152,.0722][i],0)}const s=getComputedStyle(el),a=lum(rgb(s.color)),b=lum(rgb(s.backgroundColor));return (Math.max(a,b)+.05)/(Math.min(a,b)+.05)}"""
with sync_playwright() as p:
 b=p.chromium.launch(executable_path=chromium,headless=True,args=['--no-sandbox'])
 for file,key in THEMES:
  page=b.new_page(viewport={'width':420,'height':1000});page.set_content(f'<main id="main-content" class="container-fluid" data-dashboard-theme="{key}"><div class="row">{body}</div></main>')
  theme=(ROOT/'public/css'/file).read_text();
  if theme.startswith('@charset'): theme=theme.split(';',1)[1]
  theme=re.sub(r'@import\s+url\([^;]+;','',theme);page.add_style_tag(content=theme);page.add_style_tag(path=str(CSS))
  for width in (360,420,1024):
   page.set_viewport_size({'width':width,'height':1000});page.wait_for_timeout(20);prefix=f'{key}/{width}px';card=page.locator('.mini-game-card').first
   check(page.evaluate('document.documentElement.scrollWidth<=document.documentElement.clientWidth'),f'{prefix}: no horizontal overflow')
   board=card.locator('.mini-game-board').bounding_box();inner=card.locator('.mini-game-card-inner').bounding_box();check(board and inner and board['width']<=inner['width'],f'{prefix}: board stays inside Widget')
   cell=card.locator('.mini-game-cell').first.bounding_box();check(cell and cell['height']>=44,f'{prefix}: cells keep 44px height')
   tool=card.locator('.mini-game-storage-reset').bounding_box();check(tool and tool['height']>=44,f'{prefix}: tool controls keep 44px target')
   card.locator('.mini-game-storage-reset').focus();outline=card.locator('.mini-game-storage-reset').evaluate('e=>getComputedStyle(e).outlineStyle');check(outline!='none',f'{prefix}: keyboard Focus remains visible')
   ratio=card.locator('.mini-game-card-inner').evaluate(contrast_js);check(ratio>=4.5,f'{prefix}: main Game surface text contrast is readable')
  page.close()
 b.close()
failed=len(checks)-sum(checks);print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0');sys.exit(1 if failed else 0)
