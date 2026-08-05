from pathlib import Path
import re
import sys

from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
CSS_PATH = ROOT / 'public/css/dashboard.css'
JS_PATH = ROOT / 'public/js/dashboard.js'
css = CSS_PATH.read_text(encoding='utf-8')
js = JS_PATH.read_text(encoding='utf-8')

checks = []

def check(condition, message):
    condition = bool(condition)
    checks.append(condition)
    print(('PASS' if condition else 'FAIL') + ': ' + message)

check("$titleWrap.addClass('has-feed-item-new');" in js,
      'new Feed item marks the shared title wrapper for compact Bell layout')
check(".addClass('feed-item-new')" in js and ".addClass('feed-item-new mr-1')" not in js,
      'new Bell no longer reserves flex width through the Bootstrap right margin')
check('.feed-item-title-wrap.has-feed-item-new .feed-item-title-text' in css and 'text-indent: 24px' in css,
      'only the first title line reserves Bell space')
position_rule = re.search(
    r'\.feed-item-title-wrap\.has-feed-item-new \.feed-item-new\s*\{([^}]*)\}',
    css,
    re.S,
)
position_body = position_rule.group(1) if position_rule else ''
check(all(token in position_body for token in ['position: absolute', 'top: 0', 'left: 0']),
      'Bell is removed from normal flex width and anchored at the title start')
check('width: 22px' in css and 'height: 22px' in css,
      'existing compact Bell dimensions are preserved')

html = f'''<!doctype html>
<html lang="ja"><head><meta charset="utf-8"><style>
{css}
body {{ margin: 0; padding: 20px; font-family: Arial, "Noto Sans JP", sans-serif; }}
.probe {{ width: 280px; }}
</style></head><body>
<div class="probe">
  <div class="feed-item-title-cell">
    <div class="feed-item-title-wrap has-feed-item-new" id="new-wrap">
      <button type="button" class="feed-item-new" aria-label="新着表示を解除"><span aria-hidden="true">●</span></button>
      <a class="feed-item-title-text" href="#">Microsoft 講製 デスクトップ アプリ「Skill Recorder」で記録した操作を確認する記事タイトルです</a>
    </div>
  </div>
</div>
<div class="probe">
  <div class="feed-item-title-cell">
    <div class="feed-item-title-wrap" id="normal-wrap">
      <a class="feed-item-title-text" href="#">Microsoft 講製 デスクトップ アプリ「Skill Recorder」で記録した操作を確認する記事タイトルです</a>
    </div>
  </div>
</div>
</body></html>'''

with sync_playwright() as playwright:
    browser = playwright.chromium.launch(
        headless=True,
        executable_path='/usr/bin/chromium',
        args=['--no-sandbox'],
    )
    page = browser.new_page(viewport={'width': 430, 'height': 360})
    page.set_content(html)
    measurements = page.evaluate('''() => {
      function inspect(wrapSelector) {
        const wrap = document.querySelector(wrapSelector);
        const title = wrap.querySelector('.feed-item-title-text');
        const bell = wrap.querySelector('.feed-item-new');
        const box = title.getBoundingClientRect();
        const wrapBox = wrap.getBoundingClientRect();
        const range = document.createRange();
        range.selectNodeContents(title);
        const rows = [];
        for (const rect of range.getClientRects()) {
          if (rect.y < box.top - 1 || rect.y >= box.bottom - 1) continue;
          const existing = rows.find(row => Math.abs(row.y - rect.y) < 1);
          if (existing) {
            existing.x = Math.min(existing.x, rect.x);
          } else {
            rows.push({x: rect.x, y: rect.y});
          }
        }
        rows.sort((a, b) => a.y - b.y);
        const bellBox = bell ? bell.getBoundingClientRect() : null;
        return {
          wrap: {x: wrapBox.x, y: wrapBox.y, width: wrapBox.width},
          title: {x: box.x, y: box.y, width: box.width},
          bell: bellBox ? {x: bellBox.x, y: bellBox.y, width: bellBox.width, height: bellBox.height} : null,
          rows,
          titleIndent: getComputedStyle(title).textIndent,
          bellTabIndex: bell ? bell.tabIndex : null,
        };
      }
      return {fresh: inspect('#new-wrap'), normal: inspect('#normal-wrap')};
    }''')
    browser.close()

fresh = measurements['fresh']
normal = measurements['normal']
check(abs(fresh['title']['x'] - fresh['wrap']['x']) < 1 and fresh['title']['width'] >= fresh['wrap']['width'] - 1,
      'new title keeps the full wrapper width instead of losing 22 to 26px on every line')
check(fresh['bell'] is not None and abs(fresh['bell']['x'] - fresh['wrap']['x']) < 1 and abs(fresh['bell']['y'] - fresh['wrap']['y']) <= 1.1,
      'Bell remains at the visual start of the first title line')
check(len(fresh['rows']) >= 2 and fresh['rows'][0]['x'] - fresh['title']['x'] >= 23,
      'first title line starts after the Bell')
check(len(fresh['rows']) >= 2 and abs(fresh['rows'][1]['x'] - fresh['title']['x']) < 1.5,
      'second title line returns to the full left edge')
check(normal['titleIndent'] == '0px' and abs(normal['title']['x'] - normal['wrap']['x']) < 1,
      'non-new article title layout is unchanged')
check(fresh['bellTabIndex'] == 0,
      'Bell remains keyboard focusable')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} V1.2-D R5 Bell layout checks passed.')
