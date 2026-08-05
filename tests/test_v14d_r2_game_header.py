from pathlib import Path
import re
import shutil
import sys

ROOT = Path(__file__).resolve().parents[1]
DASHBOARD_CSS = ROOT / 'public/css/dashboard.css'
GAME_CSS = ROOT / 'public/css/mini-game.css'
VERSION = ROOT / 'app/version.php'
checks = []


def check(condition, message):
    ok = bool(condition)
    checks.append(ok)
    print(('PASS' if ok else 'FAIL') + ': ' + message)


dashboard = DASHBOARD_CSS.read_text(encoding='utf-8')
game = GAME_CSS.read_text(encoding='utf-8')
version = VERSION.read_text(encoding='utf-8')

card_block = re.search(r'\.feed-card,.*?\.mini-game-card\s*\{([^}]*)\}', dashboard, re.S)
check(card_block is not None, 'Game Widget is included in the common card spacing selector')
if card_block is not None:
    check('padding: 4px' in card_block.group(1), 'Common card spacing keeps 4px padding')

inner_block = re.search(r'\.feed-card-inner,.*?\.mini-game-card-inner\s*\{([^}]*)\}', dashboard, re.S)
check(inner_block is not None, 'Game Widget inner is included in the common height selector')
if inner_block is not None:
    check('height: 100%' in inner_block.group(1), 'Common inner selector keeps full height')

header = re.search(r'\.mini-game-card-header\s*\{([^}]*)\}', game, re.S)
check(header is not None, 'Game Widget header rule exists')
if header is not None:
    body = header.group(1)
    for token in ['height: 44px', 'min-height: 44px', 'padding: 0 4px 0 8px', 'overflow: hidden', 'white-space: nowrap']:
        check(token in body, f'Game Widget header includes {token}')

check("const APP_VERSION = '1.4.0-dev.3';" in version, 'Application Version remains 1.4.0-dev.3')
check("const APP_VERSION_LABEL = 'RSS Reader Modernization V1.4-D / R2';" in version,
      'Checkpoint label is V1.4-D / R2')

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('SKIP: Playwright unavailable.')
    passed = sum(checks)
    failed = len(checks) - passed
    print(f'RESULT: PASS {passed} / FAIL {failed} / SKIP 1')
    raise SystemExit(1 if failed else 0)

chromium = shutil.which('chromium') or shutil.which('google-chrome')
if not chromium:
    print('SKIP: Chromium unavailable.')
    passed = sum(checks)
    failed = len(checks) - passed
    print(f'RESULT: PASS {passed} / FAIL {failed} / SKIP 1')
    raise SystemExit(1 if failed else 0)

html = '''
<main id="main-content">
  <div class="test-row">
    <section class="dashboard-widget clock-card">
      <div class="clock-card-inner">
        <div class="clock-card-header bg-secondary">
          <button class="btn btn-link widget-drag-handle"><i class="fas fa-grip-lines"></i></button>
          <small class="clock-title widget-title-text text-white">Clock</small>
          <button class="btn btn-link clock-edit-trigger"></button>
        </div>
      </div>
    </section>
    <section class="dashboard-widget mini-game-card">
      <div class="mini-game-card-inner">
        <div class="mini-game-card-header bg-secondary">
          <button class="btn btn-link widget-drag-handle"><i class="fas fa-grip-lines"></i></button>
          <small class="mini-game-title widget-title-text text-white">Icon Quest</small>
          <button class="btn btn-link mini-game-edit-trigger"></button>
        </div>
      </div>
    </section>
  </div>
</main>
'''

bootstrap = (ROOT / 'public/css/bootstrap.min.css').read_text(encoding='utf-8')
if bootstrap.startswith('@charset'):
    bootstrap = bootstrap.split(';', 1)[1]
bootstrap = re.sub(r'@import\s+url\([^;]+;', '', bootstrap)

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=chromium, headless=True, args=['--no-sandbox'])
    page = browser.new_page(viewport={'width': 360, 'height': 600})
    page.set_content(html)
    page.add_style_tag(content=bootstrap)
    page.add_style_tag(path=str(DASHBOARD_CSS))
    page.add_style_tag(path=str(GAME_CSS))
    page.add_style_tag(content='body{margin:0}.test-row{width:320px;margin:8px}.clock-card,.mini-game-card{width:320px}.clock-card{margin-bottom:8px}')

    for width in (360, 1024):
        page.set_viewport_size({'width': width, 'height': 600})
        prefix = f'Browser {width}px'
        game_card = page.locator('.mini-game-card').bounding_box()
        clock_card = page.locator('.clock-card').bounding_box()
        game_handle = page.locator('.mini-game-card .widget-drag-handle').bounding_box()
        clock_handle = page.locator('.clock-card .widget-drag-handle').bounding_box()
        game_title = page.locator('.mini-game-title').bounding_box()
        clock_title = page.locator('.clock-title').bounding_box()
        game_header = page.locator('.mini-game-card-header').bounding_box()
        clock_header = page.locator('.clock-card-header').bounding_box()

        check(game_header is not None and abs(game_header['height'] - 44) < 0.1,
              f'{prefix}: Game Widget header remains 44px high')
        check(clock_header is not None and abs(clock_header['height'] - 44) < 0.1,
              f'{prefix}: comparison Clock header is 44px high')

        game_offset = game_handle['x'] - game_card['x'] if game_handle and game_card else -1
        clock_offset = clock_handle['x'] - clock_card['x'] if clock_handle and clock_card else -2
        check(abs(game_offset - clock_offset) < 0.1,
              f'{prefix}: Game and Clock drag handles have the same left offset')
        check(game_offset >= 8,
              f'{prefix}: Game drag handle is no longer attached to the card left edge')

        game_title_offset = game_title['x'] - game_card['x'] if game_title and game_card else -1
        clock_title_offset = clock_title['x'] - clock_card['x'] if clock_title and clock_card else -2
        check(abs(game_title_offset - clock_title_offset) < 0.1,
              f'{prefix}: Game and Clock title start positions match')

        computed = page.locator('.mini-game-card-header').evaluate(
            "e=>({left:getComputedStyle(e).paddingLeft,right:getComputedStyle(e).paddingRight})"
        )
        check(computed == {'left': '8px', 'right': '4px'},
              f'{prefix}: Game header computed padding is 8px left and 4px right')
        check(page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth'),
              f'{prefix}: viewport has no horizontal overflow')
    browser.close()

passed = sum(checks)
failed = len(checks) - passed
print(f'RESULT: PASS {passed} / FAIL {failed} / SKIP 0')
sys.exit(1 if failed else 0)
