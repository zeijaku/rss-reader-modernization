#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
css = (ROOT / 'public/css/dashboard-card-wheel.css').read_text(encoding='utf-8')
loader = (ROOT / 'public/js/calendar.js').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
memo_css = (ROOT / 'public/css/memo-widget.css').read_text(encoding='utf-8')
dashboard_css = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
checks = []

def check(ok, msg):
    checks.append(bool(ok)); print(('PASS' if ok else 'FAIL') + ': ' + msg)

check('#main-content .dashboard-grid .dashboard-widget' in css,
      'wheel chaining is scoped to Dashboard widgets')
check('#main-content .dashboard-grid .dashboard-widget *' in css,
      'nested card contents inherit the wheel chaining correction')
check('overscroll-behavior-y: auto' in css,
      'vertical wheel gestures may chain from card scroll areas to the page')
check('overflow-y' not in css and 'height:' not in css and 'position:' not in css,
      'D does not change card sizing, overflow layout, or positioning')
check('pointer-events' not in css and 'touch-action' not in css,
      'D does not change click/touch interaction semantics')
check('overscroll-behavior: contain' in memo_css,
      'test fixture retains a bounded internal card scroller that D must override at its boundary')
check('overscroll-behavior: contain' in dashboard_css,
      'non-card Drawer containment remains present and outside D scope')
check("./css/dashboard-card-wheel.css?v=1.34.2-dev.7" in loader,
      'D stylesheet is loaded with the dev.7 cache key')
check("data-dashboard-card-wheel-style" in loader,
      'D stylesheet has a duplicate-load marker')
check("const APP_VERSION = '1.34.2-dev.7';" in version and
      "const APP_ASSET_REVISION = '1.34.2-dev.7';" in version,
      'version and asset revision are synchronized at dev.7')
check('preventDefault' not in css and 'scrollBy' not in css,
      'D relies on native scroll chaining rather than synthetic wheel scrolling')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
