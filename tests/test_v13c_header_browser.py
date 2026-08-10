from pathlib import Path
import shutil
import sys

ROOT = Path(__file__).resolve().parents[1]
CSS = ROOT / 'public/css/dashboard.css'
THEMES = [
    'bootstrap.min.css',
    'bootstrap-yeti.min.css',
    'bootstrap-minty.min.css',
    'bootstrap-flatly.min.css',
    'bootstrap-journal.min.css',
    'bootstrap-sketchy.min.css',
    'bootstrap-solar.min.css',
    'bootstrap-slate.min.css',
]
NAV_STYLES = [('dark', 'dark'), ('primary', 'dark'), ('light', 'light')]
checks: list[bool] = []
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

html_template = '''<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>
<header class="app-header">
<nav class="navbar navbar-expand-lg navbar-{scheme} bg-{background} app-navbar" aria-label="メインナビゲーション">
  <div class="app-navbar-identity">
    <a class="navbar-brand app-navbar-brand" href="#" aria-label="iGuguru ホーム"><i class="app-navbar-brand-icon">R</i><span class="app-navbar-brand-label">iGuguru</span></a>
    <span class="app-navbar-separator" aria-hidden="true"></span>
    <span class="app-navbar-current"><span class="sr-only">現在の表示：</span><span class="app-navbar-current-label">非常に長い現在のタブ名を設定した場合でもヘッダー全体を押し出さず一行で省略される確認</span></span>
  </div>
  <button class="navbar-toggler drawer-toggle app-navbar-menu-button" type="button" aria-controls="drawerMenu" aria-expanded="false" aria-label="メニューを開く"><i>≡</i></button>
  <div class="collapse navbar-collapse app-navbar-collapse" id="navbarSupportedContent">
    <ul class="navbar-nav ml-auto app-navbar-links">
      <li class="nav-item"><a class="nav-link app-navbar-link" href="#"><i>M</i><span class="app-navbar-link-label">Map Link</span></a></li>
      <li class="nav-item"><a class="nav-link app-navbar-link" href="#"><i>E</i><span class="app-navbar-link-label">Mail Link</span></a></li>
      <li class="nav-item"><a class="nav-link app-navbar-link" href="#"><i>S</i><span class="app-navbar-link-label">Search</span></a></li>
      <li class="nav-item"><a class="nav-link app-navbar-link" href="#"><i>I</i><span class="app-navbar-link-label">Image</span></a></li>
    </ul>
    <button class="btn drawer-toggle app-navbar-menu-button app-navbar-menu-button-desktop" type="button" aria-controls="drawerMenu" aria-expanded="false" aria-label="メニューを開く"><i>≡</i></button>
  </div>
</nav></header></body></html>'''

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=chromium, headless=True, args=['--no-sandbox'])
    for theme in THEMES:
        for background, scheme in NAV_STYLES:
            page = browser.new_page(viewport={'width': 420, 'height': 720}, locale='ja-JP')
            page.set_content(html_template.format(scheme=scheme, background=background))
            theme_css = (ROOT / 'public/css' / theme).read_text(encoding='utf-8')
            if theme_css.startswith('@charset'):
                theme_css = theme_css.split(';', 1)[1]
            theme_css = __import__('re').sub(r'@import\s+url\([^;]+;', '', theme_css)
            page.add_style_tag(content=theme_css)
            page.add_style_tag(path=str(CSS))
            page.wait_for_timeout(30)

            for width in (360, 420, 1024):
                page.set_viewport_size({'width': width, 'height': 720})
                page.wait_for_timeout(20)
                prefix = f'{theme}/{background}/{width}px'
                navbar = page.locator('.app-navbar')
                box = navbar.bounding_box()
                check(box is not None and 55 <= box['height'] <= 58, f'{prefix}: Navbar height stays near 56px')
                body_scroll = page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth')
                check(body_scroll, f'{prefix}: Header creates no horizontal page overflow')
                brand_box = page.locator('.app-navbar-brand').bounding_box()
                current_box = page.locator('.app-navbar-current').bounding_box()
                check(brand_box is not None and current_box is not None and abs((brand_box['y'] + brand_box['height']/2) - (current_box['y'] + current_box['height']/2)) <= 2,
                      f'{prefix}: Brand and current page are vertically aligned')
                current_style = page.locator('.app-navbar-current').evaluate('(el) => getComputedStyle(el)')
                check(current_style['whiteSpace'] == 'nowrap' and current_style['textOverflow'] == 'ellipsis', f'{prefix}: Current page remains one-line ellipsis')

                mobile_button = page.locator('.navbar-toggler.app-navbar-menu-button')
                desktop_button = page.locator('.app-navbar-menu-button-desktop')
                collapse = page.locator('.app-navbar-collapse')
                if width < 992:
                    check(mobile_button.is_visible() and not collapse.is_visible(), f'{prefix}: Smartphone uses only the compact menu button')
                    button_box = mobile_button.bounding_box()
                    check(button_box is not None and button_box['width'] >= 44 and button_box['height'] >= 44, f'{prefix}: Smartphone menu target is at least 44px')
                    overflow = page.locator('.app-navbar-current').evaluate('(el) => el.scrollWidth > el.clientWidth')
                    check(overflow, f'{prefix}: Long current page visibly truncates instead of wrapping')
                    mobile_button.focus()
                    outline = mobile_button.evaluate('(el) => getComputedStyle(el).outlineStyle')
                    check(outline != 'none', f'{prefix}: Smartphone menu keyboard focus remains visible')
                else:
                    check(not mobile_button.is_visible() and collapse.is_visible() and desktop_button.is_visible(), f'{prefix}: Desktop shows links and the desktop menu button')
                    button_box = desktop_button.bounding_box()
                    check(button_box is not None and button_box['width'] >= 44 and button_box['height'] >= 44, f'{prefix}: Desktop menu target is at least 44px')
                    links = page.locator('.app-navbar-link')
                    check(links.count() == 4 and all(links.nth(i).is_visible() for i in range(links.count())), f'{prefix}: All configured external links remain visible')
                    check(all((links.nth(i).bounding_box() or {'height': 0})['height'] >= 40 for i in range(links.count())), f'{prefix}: Desktop external links keep 40px target height')
                    desktop_button.focus()
                    outline = desktop_button.evaluate('(el) => getComputedStyle(el).outlineStyle')
                    check(outline != 'none', f'{prefix}: Desktop menu keyboard focus remains visible')

                menu_button = mobile_button if width < 992 else desktop_button
                border_style = menu_button.evaluate('(el) => getComputedStyle(el).borderTopStyle')
                border_color = menu_button.evaluate('(el) => getComputedStyle(el).borderTopColor')
                check(border_style != 'none' and border_color != 'rgba(0, 0, 0, 0)', f'{prefix}: Menu boundary remains visible for the selected theme')
            page.close()
    browser.close()

passed = sum(checks)
failed = len(checks) - passed
print(f'RESULT: PASS {passed} / FAIL {failed} / SKIP {skips}')
sys.exit(1 if failed else 0)
