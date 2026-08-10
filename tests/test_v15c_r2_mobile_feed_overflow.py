from pathlib import Path
import shutil
import sys

ROOT = Path(__file__).resolve().parents[1]
CSS = ROOT / 'public/css/dashboard.css'
BOOTSTRAP = ROOT / 'public/css/bootstrap.min.css'
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
<thead><tr><th colspan="3" class="bg-dark feed-card-header"><div class="feed-card-header-inner">
<button class="btn btn-link widget-drag-handle">＝</button>
<small class="content-title widget-title-text"><span class="feed-title-text text-white">はてなブックマーク - 人気エントリー - テクノロジー</span><button class="feed-new-clear"><span>Bell</span><span class="feed-new-count">30</span></button></small>
<span class="feed-card-actions"><button class="btn btn-link content-edit-trigger">E</button><button class="btn btn-link feed-refresh-trigger">R</button></span>
</div></th></tr></thead>
<tbody><tr><td class="feed-item-stock-cell"><button class="feed-item-action article-actions-trigger">…</button></td><td class="feed-item-title-cell"><div class="feed-item-title-wrap has-feed-item-new"><button class="feed-item-new">B</button><a class="feed-item-title-text" href="#">Claude Code の「無駄」を可視化する非常に長い記事タイトルです</a></div></td><td class="feed-item-summary-cell"><button class="feed-item-action feed-item-summary-toggle">+</button></td></tr></tbody>
</table></div></section>
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
    page.add_style_tag(path=str(CSS))
    page.wait_for_timeout(50)

    check(page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth'), '375px mobile page has no horizontal overflow')

    card = page.locator('.feed-card').bounding_box()
    inner = page.locator('.feed-card-inner').bounding_box()
    table = page.locator('.feed-table').bounding_box()
    header = page.locator('.feed-card-header-inner').bounding_box()
    actions = page.locator('.feed-card-actions').bounding_box()
    summary_cell = page.locator('.feed-item-summary-cell').bounding_box()
    summary_button = page.locator('.feed-item-summary-toggle').bounding_box()

    for name, box in [('inner', inner), ('table', table), ('header', header), ('actions', actions), ('summary cell', summary_cell), ('summary button', summary_button)]:
        check(box is not None and card is not None and box['x'] >= card['x'] - 0.6 and box['x'] + box['width'] <= card['x'] + card['width'] + 0.6,
              f'{name} stays inside the Feed card')

    title_style = page.locator('.content-title').evaluate('(e) => { const s=getComputedStyle(e); return {width:s.width, flexBasis:s.flexBasis, overflow:s.overflow}; }')
    check(title_style['flexBasis'] == '0px' and title_style['overflow'] == 'hidden', 'Feed title flex item can shrink on mobile Safari')
    check(abs(summary_cell['width'] - 44) <= 0.6, 'mobile summary column uses a consistent 44px touch width')
    check(abs(summary_button['x'] - summary_cell['x']) <= 0.6, 'summary button is no longer shifted outside its column')

    context.close()
    browser.close()

passed = sum(checks)
failed = len(checks) - passed
print(f'RESULT: PASS {passed} / FAIL {failed} / SKIP 0')
sys.exit(1 if failed else 0)
