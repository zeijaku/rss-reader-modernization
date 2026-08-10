from pathlib import Path
import shutil
import sys

ROOT = Path(__file__).resolve().parents[1]
CSS = ROOT / 'public/css/dashboard.css'
JS = ROOT / 'public/js/dashboard.js'
JQUERY = ROOT / 'public/js/jquery-3.7.1.min.js'
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('SKIP: Playwright unavailable.')
    raise SystemExit(0)

chromium = shutil.which('chromium') or shutil.which('chromium-browser') or shutil.which('google-chrome')
if chromium is None:
    print('SKIP: Chromium unavailable.')
    raise SystemExit(0)

html = '''<!doctype html><html lang="ja"><head><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="v16b"></head>
<body class="drawer"><div id="app-notice" hidden></div><div id="page-top"></div><nav id="drawerMenu"></nav>
<main id="main-content" tabindex="-1" data-dashboard-current-tab="1" data-dashboard-tab-count="4">
<div id="swipe-surface" style="height:700px;background:#f4f5f7"></div>
<input id="swipe-input" type="text"><div id="swipe-ignore" data-dashboard-swipe-ignore="true"></div>
</main></body></html>'''

dispatch_script = '''([selector,type,x,y]) => {
  const target = document.querySelector(selector);
  const event = new Event(type, {bubbles:true, cancelable:true});
  const point = {clientX:x, clientY:y};
  Object.defineProperty(event, type === 'touchend' ? 'changedTouches' : 'touches', {value:[point]});
  target.dispatchEvent(event);
  return event.defaultPrevented;
}'''


def prepare(page) -> None:
    page.set_content(html)
    page.add_style_tag(path=str(CSS))
    page.add_script_tag(path=str(JQUERY))
    page.evaluate('''() => {
      jQuery.fn.popover = function(){ return this; };
      jQuery.fn.drawer = function(){ return this; };
      jQuery.fn.modal = function(){ return this; };
      jQuery.ajax = function(){ return {done(){return this}, fail(){return this}, always(){return this}}; };
    }''')
    page.add_script_tag(path=str(JS))
    page.wait_for_timeout(30)


with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=chromium, headless=True, args=['--no-sandbox'])

    mobile = browser.new_page(viewport={'width': 390, 'height': 844}, has_touch=True, is_mobile=True)
    prepare(mobile)
    mobile.evaluate(dispatch_script, ['#swipe-surface', 'touchstart', 320, 420])
    prevented = mobile.evaluate(dispatch_script, ['#swipe-surface', 'touchmove', 130, 424])
    indicator = mobile.locator('[data-dashboard-swipe-indicator="true"]')
    check(indicator.count() == 1, '390px Mobile creates one Swipe indicator')
    check(indicator.inner_text() == '‹', 'next-tab Swipe shows the expected left arrow')
    style = indicator.evaluate('''el => { const s=getComputedStyle(el); return {display:s.display,pointer:s.pointerEvents,right:s.right,left:s.left,opacity:Number(s.opacity),position:s.position}; }''')
    check(style['display'] == 'flex' and style['position'] == 'fixed', 'Mobile indicator is fixed and visible during Swipe')
    check(style['pointer'] == 'none', 'Mobile indicator cannot intercept Link, Button, Form, Timer or Game input')
    box = indicator.bounding_box()
    check(indicator.evaluate("el => el.classList.contains('is-right')") and box is not None and box['x'] + box['width'] > 340, 'next-tab indicator is placed at the right edge')
    check(style['opacity'] > 0.5 and prevented, 'longer horizontal movement strengthens the indicator and preserves gesture handling')
    check(mobile.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth'), 'Indicator creates no horizontal overflow')

    mobile.evaluate(dispatch_script, ['#swipe-surface', 'touchstart', 210, 200])
    mobile.evaluate(dispatch_script, ['#swipe-surface', 'touchmove', 200, 460])
    check(indicator.evaluate("el => el.classList.contains('is-hiding')"), 'vertical Scroll starts a quiet indicator dismissal')
    mobile.wait_for_timeout(240)
    check(indicator.evaluate("el => el.className === 'dashboard-swipe-indicator'"), 'vertical Scroll fully removes the Swipe indicator')

    mobile.evaluate(dispatch_script, ['#swipe-input', 'touchstart', 320, 420])
    mobile.evaluate(dispatch_script, ['#swipe-input', 'touchmove', 100, 420])
    check(indicator.evaluate("el => el.className === 'dashboard-swipe-indicator'"), 'Input interaction does not activate the indicator')
    mobile.close()

    desktop = browser.new_page(viewport={'width': 1024, 'height': 800}, has_touch=True)
    prepare(desktop)
    desktop.evaluate(dispatch_script, ['#swipe-surface', 'touchstart', 800, 420])
    desktop.evaluate(dispatch_script, ['#swipe-surface', 'touchmove', 500, 420])
    check(desktop.locator('[data-dashboard-swipe-indicator="true"]').count() == 0, 'PC width does not create or display the Swipe indicator')
    desktop.close()

    reduced = browser.new_page(viewport={'width': 390, 'height': 844}, has_touch=True, is_mobile=True, reduced_motion='reduce')
    prepare(reduced)
    reduced.evaluate(dispatch_script, ['#swipe-surface', 'touchstart', 320, 420])
    reduced.evaluate(dispatch_script, ['#swipe-surface', 'touchmove', 190, 420])
    reduced_indicator = reduced.locator('[data-dashboard-swipe-indicator="true"]')
    reduced_style = reduced_indicator.evaluate('''el => { const s=getComputedStyle(el); return {transform:s.transform,transition:s.transitionDuration}; }''')
    check(reduced_style['transform'].startswith('matrix(1, 0, 0, 1, 0,'), 'Reduced Motion suppresses horizontal movement')
    durations = [float(value.rstrip('s')) for value in reduced_style['transition'].split(', ')]
    check(all(value <= 0.001 for value in durations), 'Reduced Motion collapses indicator transitions')
    reduced.close()
    browser.close()

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
sys.exit(1 if failed else 0)
