from pathlib import Path
import shutil
import sys

ROOT = Path(__file__).resolve().parents[1]
CSS = ROOT / 'public/css/dashboard.css'
BOOTSTRAP = ROOT / 'public/css/bootstrap.min.css'
FONTAWESOME = ROOT / 'public/css/all.css'
checks = []


def check(condition, message):
    ok = bool(condition)
    checks.append(ok)
    print(('PASS' if ok else 'FAIL') + ': ' + message)


try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('SKIP: Playwright unavailable.')
    raise SystemExit(0)

chromium = shutil.which('chromium') or shutil.which('google-chrome')
if chromium is None:
    print('SKIP: Chromium unavailable.')
    raise SystemExit(0)

HTML = '''<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>
<div class="igcontainer"><div class="row content-grid">
<section class="col-12 dashboard-widget feed-card normal-feed"><div class="feed-card-inner">
<table class="table table-hover feed-table"><colgroup><col class="feed-stock-column"><col><col class="feed-summary-column"></colgroup>
<tbody>
<tr class="feed-item-row"><td class="feed-item-stock-cell"></td><td class="feed-item-title-cell"><div class="feed-item-title-wrap"><a class="feed-item-title-text">概要ありの記事</a></div></td><td class="feed-item-summary-cell"><button type="button" class="feed-item-action feed-item-summary-toggle" aria-expanded="false"><i class="fas fa-plus-square feed-item-summary-icon" aria-hidden="true"></i></button></td></tr>
<tr class="feed-item-row"><td class="feed-item-stock-cell"></td><td class="feed-item-title-cell"><div class="feed-item-title-wrap"><a class="feed-item-title-text">概要なしの記事</a></div></td><td class="feed-item-summary-cell"><button type="button" class="feed-item-action feed-item-summary-toggle" aria-expanded="false" disabled><i class="fas fa-plus-square feed-item-summary-icon" aria-hidden="true"></i></button></td></tr>
</tbody></table></div></section>
</div></div></body></html>'''

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=chromium, headless=True, args=['--no-sandbox'])
    context = browser.new_context(
        viewport={'width': 375, 'height': 812},
        is_mobile=True,
        has_touch=True,
        device_scale_factor=2,
        user_agent='Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1',
        locale='ja-JP',
    )
    page = context.new_page()
    page.set_content(HTML)
    page.add_style_tag(path=str(BOOTSTRAP))
    page.add_style_tag(path=str(FONTAWESOME))
    page.add_style_tag(path=str(CSS))
    page.wait_for_timeout(50)

    check(page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth'), '375px mobile page has no horizontal overflow')

    buttons = page.locator('.feed-item-summary-toggle')
    icons = page.locator('.feed-item-summary-icon')
    check(buttons.count() == 2 and icons.count() == 2, 'summary controls exist for enabled and disabled rows')

    for index, label in ((0, 'enabled'), (1, 'disabled')):
        button = buttons.nth(index)
        icon = icons.nth(index)
        button_style = button.evaluate("el=>({display:getComputedStyle(el).display,visibility:getComputedStyle(el).visibility,opacity:parseFloat(getComputedStyle(el).opacity)})")
        icon_style = icon.evaluate("el=>({display:getComputedStyle(el).display,visibility:getComputedStyle(el).visibility,opacity:parseFloat(getComputedStyle(el).opacity),color:getComputedStyle(el).color,textFill:getComputedStyle(el).webkitTextFillColor,size:parseFloat(getComputedStyle(el).fontSize),content:getComputedStyle(el,'::before').content})")
        cell_box = button.locator('xpath=..').bounding_box()
        button_box = button.bounding_box()
        icon_box = icon.bounding_box()

        check(button_style['display'] == 'flex' or button_style['display'] == 'inline-flex', f'{label} summary button is rendered')
        check(button_style['visibility'] == 'visible' and button_style['opacity'] > 0.99, f'{label} summary button remains visible on smartphone')
        check(icon_style['display'] != 'none' and icon_style['visibility'] == 'visible' and icon_style['opacity'] > 0.99, f'{label} plus icon remains visible on smartphone')
        check(icon_style['content'] not in ('none', 'normal', '""'), f'{label} Font Awesome plus glyph is generated')
        check(icon_style['size'] >= 15, f'{label} plus icon uses a readable mobile size')
        check(icon_box is not None and icon_box['width'] > 0 and icon_box['height'] > 0, f'{label} plus icon has a visible box')
        check(cell_box is not None and button_box is not None and button_box['x'] >= cell_box['x'] - 0.6 and button_box['x'] + button_box['width'] <= cell_box['x'] + cell_box['width'] + 0.6, f'{label} summary button stays inside the 44px column')

    first = buttons.first
    first_icon = icons.first
    first.evaluate("el=>el.setAttribute('aria-expanded','true')")
    first_icon.evaluate("el=>{el.classList.remove('fa-plus-square');el.classList.add('fa-minus-square');}")
    minus_content = first_icon.evaluate("el=>getComputedStyle(el,'::before').content")
    check(minus_content not in ('none', 'normal', '""'), 'expanded state generates a visible minus glyph')

    context.close()
    browser.close()

passed = sum(checks)
failed = len(checks) - passed
print(f'RESULT: PASS {passed} / FAIL {failed} / SKIP 0')
sys.exit(1 if failed else 0)
