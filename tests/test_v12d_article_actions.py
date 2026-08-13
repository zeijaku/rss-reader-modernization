from pathlib import Path
import re

from dashboard_source_utils import dashboard_source
ROOT = Path(__file__).resolve().parents[1]
index = dashboard_source(ROOT)
js = (ROOT / 'public/js/dashboard.js').read_text(encoding='utf-8')
css = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
checks = []

def check(condition, message):
    ok = bool(condition)
    checks.append(ok)
    print(('PASS' if ok else 'FAIL') + ': ' + message)

check(re.search(r"const APP_VERSION = '(?:1\.2\.0-dev\.[4-9][0-9]*|1\.(?:[2-9]|[1-9][0-9]+)\.\d+(?:-dev\.\d+)?)';", version) is not None, 'V1.2-D or a later Version marker is visible')
check('id="articleActionsMenu"' in index and 'role="menu"' in index, 'one shared Article Actions menu exists')
for label in ('Stockへ保存', 'URLをコピー', 'Xへ投稿', 'Taskへ追加'):
    check(label in index, f'Article Actions menu includes {label}')
check(".addClass('feed-item-action article-actions-trigger')" in js, 'shared Feed renderer creates one Article Actions trigger')
check(".addClass('fas fa-ellipsis-h fa-fw text-info')" in js, 'Article trigger uses the requested ellipsis icon')
render_start = js.index('function renderFeedItems')
render_end = js.index('function feedResultIsValid', render_start)
renderer = js[render_start:render_end]
check('fa-bookmark' not in renderer, 'renderer no longer creates direct Bookmark icons')
check("renderFeedItems($card, resultFeed.item)" in js and "renderFeedItems($card,r.items)" in js.replace(' ', '').replace('\n', ''),
      'normal Feed and Search Feed still share the same article renderer')
check("rewriteInformationModal($(this));" in js and "saveStock($(this));" in js,
      'Stock action reuses the existing modal rewrite and Stock save path')
check('window.navigator.clipboard.writeText' in js and "document.execCommand('copy')" in js,
      'Clipboard API and legacy copy fallback are both implemented')
check("https://x.com/intent/post?text=" in js and js.count('encodeURIComponent(') >= 2,
      'X Web Intent URL encodes title and article URL')
check("characters.slice(0, 199).join('') + '…'" in js,
      'long X titles are capped before opening the post screen')
check("apiRequest('task.item.create'" in js, 'Task action reuses the existing Task item API')
check("'task_due_date': ''" in js and "'task_priority': 'normal'" in js,
      'Article Task uses no due date and normal priority')
task_block = js[js.index('function addArticleToTask'):js.index('/* Content追加 */')]
check('article-url' not in task_block and 'stock_data' not in task_block,
      'Task action does not send or save the article URL')
check("$('#main-content .task-card[data-dashboard-widget-id]').first()" in js,
      'Task action targets the first Task Widget in the current tab')
check("event.key === 'Escape'" in js and "['ArrowDown', 'ArrowUp', 'Home', 'End']" in js,
      'Article Actions supports Escape and menu keyboard navigation')
check("closeArticleActionsMenu(false);" in js[js.index('function fetch_content'):js.index('function setFeedSummaryExpanded')],
      'normal Feed refresh closes an open Article Actions menu')
check("closeArticleActionsMenu(false);" in js[js.index('function fetchSearchFeed'):js.index('function bindEvents')],
      'Search Feed refresh closes an open Article Actions menu')
check('.article-actions-menu {' in css and 'position: fixed;' in css and 'z-index: 1080;' in css,
      'shared menu uses a fixed overlay layer')
check('.article-actions-item {' in css and 'min-height: 44px;' in css,
      'each Article Actions item keeps a 44px minimum touch target')
check('maxWidth: Math.min(224, availableWidth)' in js and 'cardRect' in js,
      'menu placement is clamped to the current Feed card and viewport')
check(not any(p.name.startswith('007_v1_2') or p.name.startswith('v1_2_d') for p in (ROOT / 'database').rglob('*.sql')),
      'V1.2-D adds no SQL or migration file')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed}')
raise SystemExit(1 if failed else 0)
