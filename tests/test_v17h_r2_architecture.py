from pathlib import Path
import re

from version_test_utils import is_later_application_release, is_later_visible_label
ROOT = Path(__file__).resolve().parents[1]
failures: list[str] = []

def check(condition: bool, message: str) -> None:
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        failures.append(message)

version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
widget = (ROOT / 'app/dashboard_widget.php').read_text(encoding='utf-8')
api = (ROOT / 'app/api.php').read_text(encoding='utf-8')
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
js = (ROOT / 'public/js/dashboard.js').read_text(encoding='utf-8')
css = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
migration = (ROOT / 'database/migrations/008_v1_7_widget_height.sql').read_text(encoding='utf-8')
preflight = (ROOT / 'database/audit/v1_7_h_preflight.sql').read_text(encoding='utf-8')
postflight = (ROOT / 'database/audit/v1_7_h_postflight.sql').read_text(encoding='utf-8')

check(is_later_application_release(version, (1, 7, 0)) or any(token in version for token in ["const APP_VERSION = '1.7.0-dev.8';", "const APP_VERSION = '1.7.0-dev.9';", "const APP_VERSION = '1.7.0-dev.10';", "const APP_VERSION = '1.7.0';"]), 'R2 behavior remains available in R2/R3/R4 checkpoint')
check(is_later_visible_label(version, (1, 7, 0)) or any(label in version for label in ['V1.7-H / R2','V1.7-H / R3','V1.7-H / R4','RSS Reader Modernization 1.7.0']), 'R2 or successor label is visible')

check('function dashboard_widget_validate_feed_item_limit' in widget, 'Feed item-limit validator exists')
check("'item_limit' => 'auto'" in widget, 'Feed item-limit default is automatic')
check('<= 30' in widget, 'Feed item-limit has an upper bound of 30')
check("'feed' => dashboard_widget_feed_config_from_storage" in widget, 'Feed widget_config is normalized by type')
check("widget_config = :config" in widget, 'Feed update persists item-limit config without a new column')
check(":config, 0, :created_at" in widget, 'Feed create persists item-limit config without a new column')
check("dashboard_widget_validate_feed_item_limit($input['feed_item_limit'] ?? null)" in api, 'Feed create/update API validates item-limit input')
check("'feed_item_limit': $('.registerContentItemLimit').val()" in js, 'Feed create sends item-limit setting')
check("'feed_item_limit': feedItemLimit" in js, 'Feed update sends item-limit setting')

check('id="registerContentItemLimit"' in index and 'id="changeContentItemLimit"' in index, 'Feed add/edit UI exposes item-limit controls')
check('min="1" max="30"' in index, 'Feed item-limit UI constrains values to 1..30')
check('placeholder="自動"' in index, 'Blank Feed item-limit is presented as automatic mode')
check('data-feed-item-limit="' in index, 'Rendered Feed card exposes the saved item-limit mode')

check('function feedDisplayLimit' in js, 'Feed rendering separates Search limit from normal RSS limit')
check(("displayLimit === 'auto' ? 30 : displayLimit" in js) or ("displayLimit === 'auto' ? feedAutoDefaultLimit($card) : displayLimit" in js), 'Automatic mode has an explicit display-limit strategy')
check(('function trimAutoFeedRows' in js and 'feedInnerOverflows($card)' in js) or ('function feedAutoDefaultLimit' in js and 'trimAutoFeedRows' not in js), 'Automatic mode uses R2 fitting or R3 fixed defaults')
check('feedAutoFallbackLimit' in js or 'feedAutoDefaultLimit' in js, 'Automatic mode has a safe deterministic default')
check("data-search-limit') || 5" not in js, 'Normal RSS no longer inherits the former fixed five-item fallback')
check("toggleClass('is-scrollable-y', allowScroll)" in js, 'Feed vertical scrolling is enabled only when required')

check('overflow-x: hidden' in css, 'Grid widget bodies suppress horizontal scrolling')
check('.dashboard-grid .feed-card-inner {' in css and 'overflow-y: hidden' in css, 'Feed cards do not show vertical scrollbars in normal automatic mode')
check('.dashboard-grid .feed-card-inner.is-scrollable-y' in css, 'Feed has an explicit opt-in vertical-scroll state')
check('.dashboard-grid .memo-card-body' in css and '.dashboard-grid .task-card-body' in css and '.dashboard-grid .calendar-card-body' in css, 'Content-heavy widgets retain scoped vertical scrolling')
check(re.search(r'\.dashboard-grid \.feed-card-inner,[\s\S]{0,420}overflow:\s*auto', css) is None, 'R1 universal two-axis overflow:auto block is removed')

for text, label in [(migration, 'migration'), (preflight, 'preflight'), (postflight, 'postflight')]:
    check("SET @table_prefix = 'ig_';" in text, label + ' keeps configurable table prefix entry point')
    check('CONCAT(@table_prefix, \'dashboard_widget\')' in text, label + ' derives the table name from the prefix')
    check(re.search(r'FROM\s+information_schema', text, re.I) is None, label + ' does not query information_schema')
check(not (ROOT / 'database/migrations/009_v1_7_feed_item_limit.sql').exists(), 'R2 RSS item-limit feature needs no new migration')

if failures:
    raise SystemExit(f'{len(failures)} V1.7-H/R2 architecture checks failed')
print('All V1.7-H/R2 architecture checks passed.')
