from pathlib import Path
import shutil

ROOT = Path(__file__).resolve().parents[1]
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
css = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
failures = []
count = 0

def check(condition, message):
    global count
    count += 1
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        failures.append(message)

check('class="content-header feed-card-header"' in index,
      'Search Feed header uses the same fixed-height header class as normal Feed cards')
check('class="content-header-row feed-card-header-inner"' in index,
      'Search Feed header uses the same one-row flex container as normal Feed cards')
check('class="content-actions feed-card-actions"' in index,
      'Search Feed actions use the common Feed action container')
check('width:44px;' in css and 'min-width:44px;' in css and 'height:44px;' in css,
      'Search Feed edit and refresh controls retain 44px touch targets')

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('SKIP: Playwright Python package is unavailable.')
    raise SystemExit(1 if failures else 0)

chromium = shutil.which('chromium') or shutil.which('chromium-browser') or shutil.which('google-chrome')
if chromium is None:
    print('SKIP: Chromium executable is unavailable.')
    raise SystemExit(1 if failures else 0)

card = '''<section class="col-12 dashboard-widget feed-card search-feed-card">
<div class="feed-card-inner"><table class="table table-hover feed-table"><colgroup><col class="feed-stock-column"><col><col class="feed-summary-column"></colgroup>
<thead><tr class="bg-info"><th colspan="3" class="content-header feed-card-header"><div class="content-header-row feed-card-header-inner">
<button type="button" class="widget-drag-handle" aria-label="Search Feedを並び替え">＝</button>
<span class="content-title"><span class="feed-title-text">{query}</span></span>
<span class="content-actions feed-card-actions"><button type="button" class="btn btn-link search-edit-trigger"><i class="fas fa-edit text-white"></i></button><button type="button" class="btn btn-link search-feed-refresh"><i class="fas fa-sync-alt text-white"></i></button></span>
</div></th></tr></thead><tbody><tr><td colspan="3">item</td></tr></tbody></table></div></section>'''
html = '<!doctype html><html lang="ja"><head><meta charset="utf-8"></head><body>' + card.format(query='転職') + card.format(query='非常に長い検索語句を設定した場合でも見出しは一段のまま省略されます') + '</body></html>'

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=chromium, headless=True, args=['--no-sandbox'])
    for width in (420, 1024):
        page = browser.new_page(viewport={'width': width, 'height': 800}, locale='ja-JP')
        page.set_content(html)
        page.add_style_tag(path=str(ROOT / 'public/css/all.css'))
        page.add_style_tag(path=str(ROOT / 'public/css/dashboard.css'))
        page.wait_for_timeout(50)
        cards = page.locator('.search-feed-card')
        for idx in range(cards.count()):
            header = cards.nth(idx).locator('.feed-card-header')
            row = cards.nth(idx).locator('.feed-card-header-inner')
            title = cards.nth(idx).locator('.content-title')
            actions = cards.nth(idx).locator('.feed-card-actions')
            hb = header.bounding_box()
            rb = row.bounding_box()
            tb = title.bounding_box()
            ab = actions.bounding_box()
            check(hb is not None and abs(hb['height'] - 44) <= 1,
                  f'{width}px card {idx + 1}: header height stays at 44px')
            check(rb is not None and abs(rb['height'] - 44) <= 1,
                  f'{width}px card {idx + 1}: flex row stays at 44px')
            check(tb is not None and ab is not None and abs(tb['y'] - ab['y']) <= 1,
                  f'{width}px card {idx + 1}: title and actions start on the same row')
            check(ab is not None and hb is not None and ab['y'] >= hb['y'] and ab['y'] + ab['height'] <= hb['y'] + hb['height'] + 1,
                  f'{width}px card {idx + 1}: edit and refresh controls remain inside header')
        long_title = cards.nth(1).locator('.feed-title-text')
        white_space = long_title.evaluate('(el) => getComputedStyle(el).whiteSpace')
        check(white_space == 'nowrap', f'{width}px: Search Feed title never wraps the action buttons')
        if width == 420:
            overflow = long_title.evaluate('(el) => el.scrollWidth > el.clientWidth')
            check(overflow, '420px: long Search Feed title is truncated with ellipsis')
        page.close()
    browser.close()

if failures:
    raise SystemExit(1)
print(f'All {count} V1.2-C R3 header layout checks passed.')
