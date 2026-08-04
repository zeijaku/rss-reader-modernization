from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
js = (ROOT / 'public/js/dashboard.js').read_text(encoding='utf-8')
css = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
api = (ROOT / 'app/api.php').read_text(encoding='utf-8')

checks = []

def check(condition, message):
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

check('<link rel="icon" type="image/png" href="./favicon.png">' in index, 'favicon is explicitly loaded over the current origin')
check('data-feed-state="loading"' in index, 'Feed cards start with an explicit loading state')
check('フィードを読み込んでいます' in index, 'server-rendered Feed placeholder is not blank')
check('content-state-row feed-state-loading' in index, 'initial loading row uses the shared state hook')

for name in [
    'safeFeedLink', 'renderFeedMessage',
    'renderFeedBodyMessage', 'renderFeedLoading', 'renderFeedError',
    'renderFeedTitle', 'renderFeedItems', 'renderFeed',
    'feedRequestErrorMessage', 'fetch_content'
]:
    check(f'function {name}' in js, f'Feed display responsibility exists: {name}')

fetch_start = js.find('function fetch_content')
fetch_end = js.find('function bindEvents', fetch_start)
fetch = js[fetch_start:fetch_end]
render_start = js.find('function renderFeedMessage')
render_end = fetch_start
render = js[render_start:render_end]

check("apiRequest('feed.fetch', {'content_id': content_id}, 25000)" in fetch, 'Feed transport remains one content_id API request')
check("data('feed-request-pending')" in fetch, 'a card does not start the same Feed request twice while pending')
check("renderFeedLoading($card)" in fetch, 'Feed request enters loading state before transport')
check("renderFeed($card, data.data.result_feed)" in fetch, 'successful transport delegates to Feed rendering')
check("renderFeedError($card" in fetch, 'invalid and failed responses delegate to the error state')
check(".always(function ()" in fetch and "feed-request-pending', false" in fetch, 'Feed pending state is released for success and failure')

check("state, 'loading'" not in js or "renderFeedMessage($card, 'loading'" in js, 'loading state is rendered through the shared state helper')
for state in ['loading', 'ready', 'empty', 'error']:
    check(f"'{state}'" in render or f"'{state}'" in fetch, f'Feed state is represented: {state}')

check("rendered < 5" in js, 'Feed display remains limited to five valid items')
check("typeof items[i] !== 'object'" in js and "Array.isArray(items[i])" in js, 'malformed item entries are skipped')
check('function feedResultIsValid' in js and 'Array.isArray(resultFeed.item)' in js, 'missing or malformed item list is rejected as an invalid response')
check("itemTitle !== '' ? itemTitle : 'タイトルなし'" in js, 'missing item title has a stable fallback')
check("channelTitle !== '' ? channelTitle : 'タイトルなし'" in js, 'missing channel title has a stable fallback')
check("renderFeedBodyMessage($card, 'empty', '記事はありません')" in js, 'zero-item Feed has an explicit empty state')
check(".addClass('feed-item-title-text')" in js and ".text(viewTitle)" in js, 'full article title stays in the DOM and visual truncation is delegated to CSS')
check("/^https?:\\/\\//i.test(link)" in js, 'client rendering accepts only http and https Feed links')
check(".attr('rel', 'noopener noreferrer')" in js, 'external Feed links retain opener protection')
check(js.count('.text(viewTitle)') >= 2 and '.text(title)' in js, 'Feed values remain inserted as text')

for unsafe in ['.html(', 'innerHTML', 'insertAdjacentHTML', 'document.write(', 'eval(', 'new Function']:
    check(unsafe not in js, f'unsafe DOM/code operation remains absent: {unsafe}')

check('.content-state-row td' in css, 'Feed state rows have one local CSS rule')
check('[data-feed-state="error"]' in css, 'Feed error state has a visible local style')
check('$safeFeed = api_safe_feed_payload(' in api and "'result_feed' => $safeFeed" in api, 'server-side Feed payload sanitation remains in place')
check('api_feed_fetch' in api and "api_error('invalid_feed'" in api, 'existing structured Feed error contract remains unchanged')
check('package.json' not in [p.name for p in ROOT.iterdir()], 'M2-B adds no npm or build dependency')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M2-B Feed structure checks passed.')
