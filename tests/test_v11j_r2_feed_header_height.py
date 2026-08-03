from pathlib import Path
import re
import shutil

ROOT = Path(__file__).resolve().parents[1]
CSS = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
INDEX = (ROOT / 'public/index.php').read_text(encoding='utf-8')

failures = []

def check(cond, msg):
    print(('PASS' if cond else 'FAIL') + ': ' + msg)
    if not cond:
        failures.append(msg)

rule = re.search(
    r'\.feed-table thead \.feed-card-header \{(?P<body>.*?)\}',
    CSS,
    re.S,
)
check(rule is not None, 'Feed header uses a selector stronger than Bootstrap table header rules')
if rule is not None:
    body = rule.group('body')
    check('height: 44px;' in body, 'Feed header height is 44px')
    check('min-height: 44px;' in body, 'Feed header minimum height is 44px')
    check('max-height: 44px;' in body, 'Feed header maximum height is 44px')
    check('padding: 0 4px 0 8px;' in body, 'Feed header vertical padding is removed')
    check('border-top: 0;' in body and 'border-bottom: 0;' in body, 'Bootstrap table header borders do not increase the title height')
    check('box-sizing: border-box;' in body, 'Feed header includes its box model in the fixed height')
    check('line-height: 1;' in body, 'Feed header line box does not enlarge the row')

check('class="bg-' in INDEX and 'feed-card-header' in INDEX, 'Feed header markup remains present')
check('.clock-card-header {' in CSS and 'height: 44px;' in CSS, 'Clock header rule remains present')
check('.task-card-header {' in CSS and 'height: 44px;' in CSS, 'Task header rule remains present')

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('SKIP: Playwright Python package is unavailable.')
else:
    chromium = shutil.which('chromium') or shutil.which('chromium-browser') or shutil.which('google-chrome')
    if chromium is None:
        print('SKIP: Chromium executable is unavailable.')
    else:
        html = '''<!doctype html><html lang="ja"><head><meta charset="utf-8"></head><body>
        <div style="display:flex;width:900px">
          <section class="col-6 dashboard-widget clock-card">
            <div class="clock-card-inner">
              <div class="bg-info clock-card-header">
                <button type="button" class="btn btn-link widget-drag-handle"><i aria-hidden="true">=</i></button>
                <small class="clock-title widget-title-text text-white">Clock</small>
                <button type="button" class="btn btn-link clock-edit-trigger">E</button>
              </div>
            </div>
          </section>
          <section class="col-6 dashboard-widget feed-card">
            <div class="feed-card-inner">
              <table class="table table-hover feed-table">
                <colgroup><col class="feed-stock-column"><col></colgroup>
                <thead><tr><th colspan="2" class="bg-info feed-card-header">
                  <button type="button" class="btn btn-link widget-drag-handle"><i aria-hidden="true">=</i></button>
                  <small><span class="content-title widget-title-text"><a class="text-white feed-title-text">無いニュース(ﾊﾟﾜ)</a><button class="feed-new-clear">15</button></span></small>
                  <button type="button" class="btn btn-link float-right content-edit-trigger">E</button>
                </th></tr></thead>
              </table>
            </div>
          </section>
        </div></body></html>'''
        with sync_playwright() as p:
            browser = p.chromium.launch(executable_path=chromium, headless=True, args=['--no-sandbox'])
            page = browser.new_page(viewport={'width': 1000, 'height': 400})
            page.set_content(html)
            page.add_style_tag(path=str(ROOT / 'public/css/bootstrap.min.css'))
            page.add_style_tag(path=str(ROOT / 'public/css/dashboard.css'))
            page.wait_for_timeout(30)

            clock = page.locator('.clock-card-header').bounding_box()
            feed = page.locator('.feed-card-header').bounding_box()
            check(clock is not None and feed is not None, 'Clock and Feed headers render')
            if clock is not None and feed is not None:
                check(abs(clock['height'] - 44) <= 0.5, 'Clock header renders at 44px')
                check(abs(feed['height'] - 44) <= 0.5, 'Feed header renders at 44px')
                check(abs(clock['height'] - feed['height']) <= 0.5, 'Feed and Clock header heights match')

            drag = page.locator('.feed-card-header .widget-drag-handle').bounding_box()
            edit = page.locator('.feed-card-header .content-edit-trigger').bounding_box()
            title = page.locator('.feed-card-header .content-title').bounding_box()
            check(all(box is not None for box in [drag, edit, title]), 'Feed header controls render')
            if feed is not None and all(box is not None for box in [drag, edit, title]):
                for box, name in [(drag, 'drag handle'), (edit, 'edit button'), (title, 'title area')]:
                    check(box['y'] >= feed['y'] - 0.5 and box['y'] + box['height'] <= feed['y'] + feed['height'] + 0.5, f'Feed {name} stays inside the 44px header')
            browser.close()

if failures:
    raise SystemExit(1)
print('All V1.1-J / R2 Feed header height checks passed.')
