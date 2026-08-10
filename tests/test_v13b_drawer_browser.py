from pathlib import Path
import shutil
import sys

ROOT = Path(__file__).resolve().parents[1]
CSS = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
checks = []
skips = 0

def check(condition, message):
    ok = bool(condition)
    checks.append(ok)
    print(('PASS' if ok else 'FAIL') + ': ' + message)

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('SKIP: Playwright Python package is unavailable.')
    raise SystemExit(0)

chromium = shutil.which('chromium') or shutil.which('chromium-browser') or shutil.which('google-chrome')
if chromium is None:
    print('SKIP: Chromium executable is unavailable.')
    raise SystemExit(0)

html = '''<!doctype html><html lang="ja"><head><meta charset="utf-8"></head><body>
<nav class="drawer-nav" id="drawerMenu" style="position:static; width:260px; height:600px" aria-label="RSS Readerメニュー">
<ul class="drawer-menu">
<li class="drawer-brand"><i class="drawer-brand-icon">R</i><span class="drawer-brand-label"><strong>iGuguru</strong></span></li>
<li class="drawer-section-title"><i>V</i><span>表示</span></li>
<li><a href="#" class="text-muted drawer-item drawer-item-current" aria-current="page"><span class="drawer-item-icon">1</span><span class="drawer-item-label">非常に長いタブ名でも崩れず表示される確認用タブ</span></a></li>
<li><button class="btn btn-link text-muted drawer-menu-action drawer-item" type="button"><span class="drawer-item-icon">+</span><span class="drawer-item-label">RSS追加</span></button></li>
<li class="drawer-section-title drawer-mobile-links"><i>L</i><span>リンク</span></li>
<li class="drawer-mobile-links"><a href="#" class="text-muted drawer-item"><span class="drawer-item-icon">X</span><span class="drawer-item-label">外部リンク</span></a></li>
<li class="drawer-section-title"><i>A</i><span>Account</span></li>
<li><button class="btn btn-link text-muted drawer-logout-button drawer-item" type="button"><span class="drawer-item-icon">O</span><span class="drawer-item-label">ログアウト</span></button></li>
</ul></nav></body></html>'''

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=chromium, headless=True, args=['--no-sandbox'])
    for width in (420, 1024):
        page = browser.new_page(viewport={'width': width, 'height': 700}, locale='ja-JP')
        page.set_content(html)
        page.add_style_tag(content=CSS)
        page.wait_for_timeout(30)
        link_item = page.locator('.drawer-mobile-links').first
        visible = link_item.is_visible()
        check(visible if width == 420 else not visible, f'{width}px: user links appear only in the intended navigation surface')
        first_item = page.locator('.drawer-item').first
        box = first_item.bounding_box()
        check(box is not None and box['height'] >= 40, f'{width}px: Drawer item keeps at least 40px height')
        current = page.locator('.drawer-item-current')
        border = current.evaluate('(el) => getComputedStyle(el).borderLeftWidth')
        background = current.evaluate('(el) => getComputedStyle(el).backgroundColor')
        check(border != '0px' and background != 'rgba(0, 0, 0, 0)', f'{width}px: current item remains visually distinct')
        item_x = first_item.bounding_box()['x']
        label_x = first_item.locator('.drawer-item-label').bounding_box()['x']
        button_label_x = page.locator('.drawer-menu-action .drawer-item-label').bounding_box()['x']
        check(abs(label_x - button_label_x) <= 1, f'{width}px: links and buttons share the same label alignment')
        first_item.focus()
        outline_style = first_item.evaluate('(el) => getComputedStyle(el).outlineStyle')
        check(outline_style != 'none', f'{width}px: keyboard focus remains visible')
        overflow = page.locator('.drawer-nav').evaluate('(el) => getComputedStyle(el).overflowY')
        check(overflow == 'auto', f'{width}px: Drawer remains vertically scrollable')
        page.close()
    browser.close()

passed = sum(checks)
failed = len(checks) - passed
print(f'RESULT: PASS {passed} / FAIL {failed} / SKIP {skips}')
sys.exit(1 if failed else 0)
