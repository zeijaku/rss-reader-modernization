from pathlib import Path
import re
import shutil
import sys

ROOT = Path(__file__).resolve().parents[1]
CSS = ROOT / 'public/css/clock-timer.css'
THEMES = [
    ('bootstrap.min.css', 'bootstrap'),
    ('bootstrap-yeti.min.css', 'bootstrap-yeti'),
    ('bootstrap-minty.min.css', 'bootstrap-minty'),
    ('bootstrap-flatly.min.css', 'bootstrap-flatly'),
    ('bootstrap-journal.min.css', 'bootstrap-journal'),
    ('bootstrap-sketchy.min.css', 'bootstrap-sketchy'),
    ('bootstrap-solar.min.css', 'bootstrap-solar'),
    ('bootstrap-slate.min.css', 'bootstrap-slate'),
]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('SKIP: Playwright unavailable.')
    raise SystemExit(0)

chromium = shutil.which('chromium') or shutil.which('google-chrome')
if not chromium:
    print('SKIP: Chromium unavailable.')
    raise SystemExit(0)

presets = ''.join(f'<button class="btn btn-outline-secondary clock-timer-preset clock-timer-duration-control">{value}分</button>' for value in (1, 3, 5, 10, 25))
body = f'''
<section class="col-12 col-sm-6 col-lg-3 clock-card clock-timer-completed">
  <div class="clock-card-inner">
    <div class="clock-card-header bg-primary"><span class="text-white">Clock</span></div>
    <div class="clock-card-body clock-timer-enabled">
      <div class="clock-view-switch">
        <button class="btn btn-outline-secondary clock-view-toggle">時計</button>
        <button class="btn btn-outline-secondary clock-view-toggle active">タイマー</button>
      </div>
      <div class="clock-view-panel clock-view-timer">
        <time class="clock-timer-display">00:00:00</time>
        <p class="clock-timer-status">タイマーが終了しました</p>
        <div class="clock-timer-presets">{presets}</div>
        <div class="clock-timer-custom">
          <input class="form-control clock-timer-custom-minutes" type="number" value="5">
          <span class="clock-timer-custom-unit">分</span>
          <button class="btn btn-outline-secondary clock-timer-custom-apply">設定</button>
        </div>
        <div class="clock-timer-actions">
          <button class="btn btn-primary">開始</button><button class="btn btn-outline-secondary">一時停止</button><button class="btn btn-outline-danger">Reset</button>
        </div>
      </div>
    </div>
  </div>
</section>'''
base_css = '.clock-card{padding:4px}.clock-card-inner{border:1px solid #ced4da;background:#fff;color:#212529}.clock-card-header{height:44px;padding:0 4px 0 8px;display:flex;align-items:center}'
contrast_js = """([fg,bg])=>{function rgb(s){const m=s.match(/[\\d.]+/g);if(!m)return [0,0,0];return m.slice(0,3).map(Number)}function lum(c){return c.map(v=>{v/=255;return v<=.03928?v/12.92:Math.pow((v+.055)/1.055,2.4)}).reduce((a,v,i)=>a+v*[.2126,.7152,.0722][i],0)}const a=lum(rgb(fg)),b=lum(rgb(bg));return (Math.max(a,b)+.05)/(Math.min(a,b)+.05)}"""

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=chromium, headless=True, args=['--no-sandbox'])
    for file_name, theme_key in THEMES:
        page = browser.new_page(viewport={'width': 420, 'height': 1000})
        page.set_content(f'<main id="main-content" class="container-fluid" data-dashboard-theme="{theme_key}"><div class="row">{body}</div></main>')
        page.add_style_tag(content=base_css)
        theme = (ROOT / 'public/css' / file_name).read_text(encoding='utf-8')
        if theme.startswith('@charset'):
            theme = theme.split(';', 1)[1]
        theme = re.sub(r'@import\s+url\([^;]+;', '', theme)
        page.add_style_tag(content=theme)
        page.add_style_tag(path=str(CSS))
        for width in (360, 420, 1024):
            page.set_viewport_size({'width': width, 'height': 1000})
            prefix = f'{theme_key}/{width}px'
            card = page.locator('.clock-card').first
            check(page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth'), f'{prefix}: no horizontal overflow')
            inner = card.locator('.clock-card-inner').bounding_box()
            timer = card.locator('.clock-view-timer').bounding_box()
            check(inner is not None and timer is not None and timer['width'] <= inner['width'], f'{prefix}: Timer stays inside Widget')
            control_boxes = card.locator('.clock-view-toggle, .clock-timer-preset, .clock-timer-actions .btn').all()
            check(all((control.bounding_box() or {}).get('height', 0) >= 44 for control in control_boxes), f'{prefix}: interactive controls keep 44px targets')
            focus_target = card.locator('.clock-timer-actions .btn').first
            focus_target.focus()
            outline = focus_target.evaluate('el => getComputedStyle(el).outlineStyle')
            check(outline != 'none', f'{prefix}: keyboard Focus remains visible')
            colors = card.locator('.clock-timer-status').evaluate("el => { const fg=getComputedStyle(el).color; const bg=getComputedStyle(el.closest('.clock-card-inner')).backgroundColor; return [fg,bg]; }")
            ratio = page.evaluate(contrast_js, colors)
            check(ratio >= 4.5, f'{prefix}: completed status contrast is readable')
        page.close()

    reduced = browser.new_page(viewport={'width': 420, 'height': 800}, reduced_motion='reduce')
    reduced.set_content(f'<main id="main-content">{body}</main>')
    reduced.add_style_tag(content=base_css)
    reduced.add_style_tag(path=str(CSS))
    reduced.locator('.clock-card').evaluate("el => el.classList.add('clock-timer-completed-recent')")
    animation = reduced.locator('.clock-view-timer').evaluate('el => getComputedStyle(el).animationName')
    check(animation == 'none', 'reduced motion disables completion animation')
    reduced.close()
    browser.close()

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
sys.exit(1 if failed else 0)
