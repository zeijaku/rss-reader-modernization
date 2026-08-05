from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
js = (ROOT / 'public/js/dashboard.js').read_text(encoding='utf-8')
css = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
api = (ROOT / 'app/api.php').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
checks = []

def check(condition, message):
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

check('feed-refresh-trigger' in index and 'fa-sync-alt' in index, 'Feed header includes an individual refresh action')
check(index.index('content-edit-trigger') < index.index('feed-refresh-trigger'), 'edit action remains before refresh action')
check('widget-drag-handle' in index and 'feed-card-actions' in index, 'drag handle and right-side Feed actions remain separate')
check('feed-stock-column' in index and 'feed-summary-column' in index, 'Stock and summary actions have independent fixed columns')
check('data-feed-content-id' in index, 'Feed refresh remains bound to the owned Content ID')

for name in [
    'feedItemSummary', 'renderFeedItems', 'setFeedSummaryExpanded', 'toggleFeedSummary',
    'feedTitleIsTruncated', 'scheduleFeedTitleTooltip', 'refreshFeedTitleOverflow',
    'setFeedRefreshPending', 'keepFeedAfterRefreshError', 'fetch_content'
]:
    check(f'function {name}' in js, f'V1.2-B responsibility exists: {name}')

check("String(item && item.content ? item.content : '').trim()" in js, 'content is selected first for the accordion')
check("String(item && item.description ? item.description : '').trim()" in js, 'description is the fallback for the accordion')
check(".text(String(item.summary))" in js, 'accordion inserts RSS text without HTML interpretation')
for unsafe in ['.html(', 'innerHTML', 'insertAdjacentHTML', 'document.write(', 'eval(', 'new Function']:
    check(unsafe not in js, f'unsafe DOM/code operation remains absent: {unsafe}')
check(".prop('disabled', summary === '')" in js, 'empty summary cannot open a blank accordion')
check(".attr('aria-expanded', 'false')" in js and "aria-controls" in js, 'accordion exposes accessible state and relationship')
check(".attr('tabindex', '0')" in js, 'non-link titles remain keyboard focusable')
check("Number(element.scrollWidth || 0) > Number(element.clientWidth || 0) + 1" in js and "Number(element.scrollHeight || 0) > Number(element.clientHeight || 0) + 1" in js, 'overflow detection checks both horizontal and two-line vertical truncation')
check("}, 240);" in js, 'title tooltip uses a short display delay')
check(".attr('role', 'tooltip')" in js and "aria-describedby" in js, 'title tooltip has accessible semantics')
check(".attr('data-full-title', viewTitle)" in js and ".text(viewTitle)" in js, 'full title is retained as text')
check('truncateFeedTitle' not in js, 'fixed JavaScript title cutting is removed')

check("settings.preserve === true" in js, 'individual refresh has a preserve-current-content mode')
check("apiRequest('feed.fetch', {'content_id': content_id}, 25000)" in js, 'refresh reuses the existing Feed API')
check('force' not in js[js.index('function fetch_content'):js.index('var widgetDragState')], 'refresh does not request a force-cache bypass')
check("data('feed-request-pending')" in js, 'Feed card prevents concurrent refresh requests')
check(".toggleClass('fa-spin', pending)" in js, 'refresh icon rotates only while pending')
check("keepFeedAfterRefreshError" in js and "renderFeedError($card, message)" in js, 'initial failure and preserved refresh failure are handled separately')
check("showNotice('RSSを更新しました'" in js, 'successful individual refresh reports completion')
check("event.stopPropagation()" in js and "'.feed-refresh-trigger, .feed-item-action'" in js, 'refresh and article actions do not leak pointer events into drag handling')
check("fetch_content($card, {preserve: true})" in js, 'NEW state refresh also keeps current articles visible')

check('.feed-item-title-text' in css and 'text-overflow: ellipsis' in css and 'white-space: normal' in css and '-webkit-line-clamp: 2' in css, 'article titles use a natural one-to-two-line CSS clamp')
check('.feed-title-tooltip' in css and 'max-width: min(420px' in css, 'tooltip is bounded to the viewport')
check('.feed-item-summary' in css and 'max-height: 14rem' in css and 'overflow: auto' in css, 'long RSS summary is bounded and scrollable')
check('.feed-item-action' in css and 'width: 44px' in css and 'min-height: 44px' in css, 'article actions have touch-friendly targets')
check('.feed-item-stock-cell' in css and '.feed-item-summary-cell' in css, 'Stock stays left and summary stays right without sharing one crowded cell')
check('.feed-item-summary-icon' in css and 'color: #6c757d' in css and 'font-size: 0.88rem' in css, 'summary Font Awesome icon has a compact explicit color and size')
check('.feed-item-summary-toggle[aria-expanded="true"]' in css and '.feed-item-summary-icon' in css, 'expanded accordion has a distinct visual state')
check('@media (prefers-reduced-motion: reduce)' in css, 'reduced-motion handling remains present')

check("'description' => api_feed_text" in api and "'content' => api_feed_text" in api, 'safe API payload continues to include bounded description and content')
check('strip_tags' in api, 'server-side Feed text still strips markup')
check("'feed.fetch' => api_feed_fetch" in api, 'existing structured Feed API remains in use')
check(re.search(r"const APP_VERSION = '(?:1\.2\.0-dev\.[3-9][0-9]*|1\.[2-9][0-9]*\.\d+(?:-dev\.\d+)?)';", version) is not None, 'Version marker identifies V1.2-B or a later checkpoint')
check(".addClass('feed-item-stock-cell')" in js and ".addClass('feed-item-summary-cell')" in js, 'article DOM uses Stock-left and summary-right cells')
check(".addClass('fas fa-plus-square feed-item-summary-icon')" in js and ".toggleClass('fa-minus-square', expanded)" in js, 'summary control uses compact Font Awesome plus/minus icons')
check(js.index(".addClass('feed-item-stock-cell')") < js.index(".addClass('feed-item-title-cell')") < js.index(".addClass('feed-item-summary-cell')"), 'article cells are generated in Stock, title, summary order')
check(not any((ROOT / 'database').glob('*v1_2_b*')), 'V1.2-B adds no database migration')
check(not (ROOT / 'package.json').exists(), 'V1.2-B adds no npm/build dependency')

if not all(checks):
    raise SystemExit(1)
print(f'All {len(checks)} V1.2-B architecture checks passed.')
