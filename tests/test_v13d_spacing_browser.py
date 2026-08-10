from pathlib import Path
import shutil
import sys

ROOT = Path(__file__).resolve().parents[1]
CSS = ROOT / 'public/css/dashboard.css'
BOOTSTRAP = ROOT / 'public/css/bootstrap.min.css'
checks: list[bool] = []


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

HTML = '''<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>
<div class="igcontainer"><div class="row content-grid">
<section class="col-6 dashboard-widget clock-card"><div class="clock-card-inner"><div class="bg-info clock-card-header"><button class="btn btn-link widget-drag-handle"><i aria-hidden="true">=</i></button><small class="clock-title widget-title-text text-white">Clock の非常に長い見出し</small><button class="btn btn-link clock-edit-trigger">E</button></div></div></section>
<section class="col-6 dashboard-widget memo-card"><div class="memo-card-inner"><div class="bg-info memo-card-header"><button class="btn btn-link widget-drag-handle"><i aria-hidden="true">=</i></button><small class="memo-title widget-title-text text-white">Memo の非常に長い見出し</small><button class="btn btn-link memo-edit-trigger">E</button></div></div></section>
<section class="col-6 dashboard-widget task-card"><div class="task-card-inner"><div class="bg-info task-card-header"><button class="btn btn-link widget-drag-handle"><i aria-hidden="true">=</i></button><small class="task-widget-title widget-title-text text-white">Task の非常に長い見出し</small><button class="btn btn-link task-widget-edit-trigger">E</button></div></div></section>
<section class="col-6 dashboard-widget calendar-card"><div class="calendar-card-inner"><div class="bg-info calendar-card-header"><button class="btn btn-link widget-drag-handle"><i aria-hidden="true">=</i></button><small class="calendar-widget-title widget-title-text text-white">Calendar の非常に長い見出し</small><button class="btn btn-link calendar-widget-edit-trigger">E</button></div></div></section>
<section class="col-6 dashboard-widget feed-card normal-feed"><div class="feed-card-inner"><table class="table table-hover feed-table"><colgroup><col class="feed-stock-column"><col><col class="feed-summary-column"></colgroup><thead><tr><th colspan="3" class="bg-info feed-card-header"><div class="feed-card-header-inner"><button class="btn btn-link widget-drag-handle"><i aria-hidden="true">=</i></button><small class="content-title widget-title-text"><span class="feed-title-text text-white">通常RSSの非常に長い見出し</span><button class="feed-new-clear"><span>Bell</span><span class="feed-new-count">12</span></button></small><span class="feed-card-actions"><button class="btn content-edit-trigger">E</button><button class="btn feed-refresh-trigger">R</button></span></div></th></tr></thead><tbody><tr><td class="feed-item-stock-cell"><button class="feed-item-action article-actions-trigger"><i aria-hidden="true">...</i></button></td><td class="feed-item-title-cell"><div class="feed-item-title-wrap has-feed-item-new"><button class="feed-item-new" aria-label="新着表示を解除">B</button><a class="feed-item-title-text" href="#">Microsoft 製デスクトップアプリで記録した操作を確認する非常に長い記事タイトルです</a></div></td><td class="feed-item-summary-cell"><button class="feed-item-action feed-item-summary-toggle">+</button></td></tr></tbody></table></div></section>
<section class="col-6 dashboard-widget feed-card search-feed-card"><div class="feed-card-inner"><table class="table table-hover feed-table"><colgroup><col class="feed-stock-column"><col><col class="feed-summary-column"></colgroup><thead><tr><th colspan="3" class="bg-info feed-card-header"><div class="feed-card-header-inner"><button class="widget-drag-handle">=</button><span class="content-title widget-title-text"><span class="feed-title-text text-white">Search Feedの非常に長い見出し</span></span><span class="feed-card-actions"><button class="btn content-edit-trigger">E</button><button class="btn feed-refresh-trigger">R</button></span></div></th></tr></thead></table></div></section>
</div></div></body></html>'''


def inspect(page, label, expect_touch):
    page.set_content(HTML)
    page.add_style_tag(path=str(BOOTSTRAP))
    page.add_style_tag(path=str(CSS))
    page.wait_for_timeout(30)

    check(page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth'), f'{label}: no horizontal overflow')

    cards = ['.clock-card', '.memo-card', '.task-card', '.calendar-card', '.normal-feed', '.search-feed-card']
    starts = []
    fonts = []
    for selector in cards:
        card = page.locator(selector).first
        header = card.locator('.clock-card-header,.memo-card-header,.task-card-header,.calendar-card-header,.feed-card-header').first.bounding_box()
        title = card.locator('.widget-title-text').first.bounding_box()
        drag = card.locator('.widget-drag-handle').first.bounding_box()
        check(header is not None and 43.5 <= header['height'] <= 45, f'{label}/{selector}: Widget header stays 44px')
        check(title is not None and drag is not None, f'{label}/{selector}: title and drag handle render')
        if title and drag:
            starts.append(round(title['x'] - (drag['x'] + drag['width']), 2))
            check(title['y'] >= header['y'] - 0.6 and title['y'] + title['height'] <= header['y'] + header['height'] + 0.6,
                  f'{label}/{selector}: title stays inside header')
        fonts.append(float(card.locator('.widget-title-text').first.evaluate('(e) => parseFloat(getComputedStyle(e).fontSize)')))
    check(max(starts) - min(starts) <= 0.6, f'{label}: all Widget titles start at the same spacing after the drag handle')
    check(max(fonts) - min(fonts) <= 0.2, f'{label}: all Widget title font sizes match')

    stock_cell = page.locator('.normal-feed .feed-item-stock-cell').bounding_box()
    action = page.locator('.normal-feed .article-actions-trigger').bounding_box()
    title_cell = page.locator('.normal-feed .feed-item-title-cell').bounding_box()
    title_wrap = page.locator('.normal-feed .feed-item-title-wrap').bounding_box()
    summary_cell = page.locator('.normal-feed .feed-item-summary-cell').bounding_box()
    summary_button = page.locator('.normal-feed .feed-item-summary-toggle').bounding_box()
    styles = page.locator('.normal-feed .feed-item-title-cell').evaluate('(e) => { const s=getComputedStyle(e); return {top:s.paddingTop,right:s.paddingRight,bottom:s.paddingBottom,left:s.paddingLeft}; }')
    stock_padding = page.locator('.normal-feed .feed-item-stock-cell').evaluate('(e) => getComputedStyle(e).padding')
    summary_padding = page.locator('.normal-feed .feed-item-summary-cell').evaluate('(e) => getComputedStyle(e).padding')
    check(styles == {'top': '7px', 'right': '2px', 'bottom': '7px', 'left': '6px'}, f'{label}: article title padding is the intended compact 7/2/7/6px')
    check(stock_padding == '0px' and summary_padding == '0px', f'{label}: action cells no longer inherit Bootstrap 12px padding')
    expected = 44 if expect_touch else 36
    check(stock_cell is not None and action is not None and abs(stock_cell['width'] - expected) <= 0.6 and abs(action['width'] - expected) <= 0.6,
          f'{label}: three-dot cell and trigger use {expected}px width')
    if stock_cell and action:
        check(abs((stock_cell['x'] + stock_cell['width']/2) - (action['x'] + action['width']/2)) <= 0.6,
              f'{label}: three-dot trigger is centered in its table column')
        check(action['height'] >= 44, f'{label}: three-dot trigger keeps 44px height')
    if title_cell and title_wrap:
        check(abs(title_wrap['x'] - (title_cell['x'] + 6)) <= 0.6, f'{label}: article title begins after the intended 6px gap')
        check(abs((title_wrap['x'] + title_wrap['width']) - (title_cell['x'] + title_cell['width'] - 2)) <= 0.6,
              f'{label}: article title keeps only the intended 2px right inset')
    if summary_cell and summary_button:
        check(summary_button['height'] >= 44, f'{label}: summary action retains 44px tap height')

    title = page.locator('.normal-feed .feed-item-title-text')
    bell = page.locator('.normal-feed .feed-item-new')
    check(title.evaluate('(e) => getComputedStyle(e).webkitLineClamp') == '2', f'{label}: long article title remains two-line clamped')
    check(bell.is_visible() and bell.bounding_box()['width'] >= 22, f'{label}: new Bell remains visible with compact dimensions')


with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=chromium, headless=True, args=['--no-sandbox'])
    desktop = browser.new_page(viewport={'width': 1024, 'height': 900}, locale='ja-JP')
    inspect(desktop, 'desktop-1024', False)
    desktop.close()
    browser.close()

    touch_browser = p.chromium.launch(executable_path=chromium, headless=True, args=['--no-sandbox'])
    touch_context = touch_browser.new_context(viewport={'width': 420, 'height': 900}, locale='ja-JP', has_touch=True, is_mobile=True)
    touch = touch_context.new_page()
    inspect(touch, 'touch-420', True)
    touch_context.close()
    touch_browser.close()

passed = sum(checks)
failed = len(checks) - passed
print(f'RESULT: PASS {passed} / FAIL {failed} / SKIP 0')
sys.exit(1 if failed else 0)
