from pathlib import Path
import re

from version_test_utils import is_later_application_release, is_later_visible_label
ROOT = Path(__file__).resolve().parents[1]
failures = []

def check(condition, message):
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        failures.append(message)

version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
js = (ROOT / 'public/js/dashboard.js').read_text(encoding='utf-8')
css = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
schema = (ROOT / 'database/schema.sql').read_text(encoding='utf-8')
migration = (ROOT / 'database/migrations/008_v1_7_widget_height.sql').read_text(encoding='utf-8')
preflight = (ROOT / 'database/audit/v1_7_h_preflight.sql').read_text(encoding='utf-8')
postflight = (ROOT / 'database/audit/v1_7_h_postflight.sql').read_text(encoding='utf-8')

check(is_later_application_release(version, (1, 7, 0)) or re.search(r"const APP_VERSION = '(?:1\.7\.0-dev\.(?:9|10)|1\.7\.0)';", version) is not None, 'R3-or-later checkpoint preserves immutable asset cache refresh')
check(is_later_visible_label(version, (1, 7, 0)) or "RSS Reader Modernization V1.7-H / R3" in version or "RSS Reader Modernization V1.7-H / R4" in version or "RSS Reader Modernization 1.7.0" in version, 'R3-or-later V1.7-H label is visible')
check(css.count('grid-auto-rows: minmax(320px, auto)') >= 2, 'Desktop and Tablet use a 320px minimum row')
check('[data-widget-height="2"] { grid-row: span 2; }' in css, 'height 2 still spans two Grid rows')
check('grid-auto-flow: dense' not in css, 'dense packing remains disabled')
check('grid-auto-rows: auto' in css and '@media (max-width: 767.98px)' in css, 'Smartphone remains auto-height')

check('function feedAutoDefaultLimit' in js, 'RSS automatic mode uses deterministic defaults')
check("return String($card.attr('data-widget-height') || '1') === '2' ? 10 : 5;" in js, 'RSS automatic mode is 5 items for standard height and 10 for height 2')
check("displayLimit === 'auto' ? feedAutoDefaultLimit($card) : displayLimit" in js, 'RSS renderer applies automatic defaults directly')
check('trimAutoFeedRows' not in js, 'R2 layout-measurement trimming is removed')
check("displayLimit !== 'auto' && feedInnerOverflows($card)" in js, 'only explicit RSS limits opt in to overflow scrolling')
check('id="registerContentItemLimit"' in index and 'id="changeContentItemLimit"' in index, 'RSS add/edit UI still exposes display count')
check('min="1" max="30"' in index and 'placeholder="自動"' in index, 'RSS display count remains 1..30 or automatic')

check('.dashboard-grid > .clock-card[data-widget-height="1"] .clock-card-inner' in css, 'Clock standard-height compatibility rule exists')
check('.dashboard-grid > .mini-game-card[data-widget-height="1"] .mini-game-card-inner' in css, 'Game standard-height compatibility rule exists')
check('height: auto;' in css and 'min-height: 320px;' in css, 'Clock/Game standard cards may grow beyond the 320px minimum')
check('min-height: calc(320px - 44px);' in css and 'overflow-y: visible;' in css, 'Clock/Game body is not clipped at standard height')
clock_hidden = re.search(r'\.dashboard-grid\s+\.clock-card-body\s*,\s*\.dashboard-grid\s+\.mini-game-card-body\s*\{[^}]*overflow-y:\s*hidden', css, re.S)
check(clock_hidden is None, 'R2 Clock/Game overflow-hidden clipping rule is removed')

check('`widget_height` TINYINT UNSIGNED NOT NULL DEFAULT 1' in schema, 'existing V1.7-H widget_height schema remains unchanged')
check(not (ROOT / 'database/migrations/009_v1_7_h_r3.sql').exists(), 'R3 adds no migration')
check(not (ROOT / 'database/migrations/009_v1_7_feed_item_limit.sql').exists(), 'RSS count still uses widget_config without a migration')
for text, label in [(migration, 'migration'), (preflight, 'preflight'), (postflight, 'postflight')]:
    check("SET @table_prefix = 'ig_';" in text, label + ' keeps the configurable prefix entry point')
    check(re.search(r'FROM\s+information_schema', text, re.I) is None, label + ' remains information_schema independent')

if failures:
    raise SystemExit(f'{len(failures)} V1.7-H/R3 architecture checks failed')
print('All V1.7-H/R3 architecture checks passed.')
