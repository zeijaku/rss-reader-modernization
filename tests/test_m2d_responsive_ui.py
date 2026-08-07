from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
login = (ROOT / 'app/common/common_login.php').read_text(encoding='utf-8')
js = (ROOT / 'public/js/dashboard.js').read_text(encoding='utf-8')
css = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
api = (ROOT / 'app/api.php').read_text(encoding='utf-8')
widget = (ROOT / 'app/dashboard_widget.php').read_text(encoding='utf-8')

checks: list[bool] = []
def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

check('class="row content-grid feed-grid"' in index or 'class="row content-grid feed-grid dashboard-grid"' in index, 'Feed cards use one responsive grid')
check("default => 'col-12 col-md-6 col-lg-3'" in widget and "app_html($widgetWidthClass) . ' dashboard-widget feed-card" in index, 'Feed grid keeps Mobile 1 / Tablet 2 / Desktop 4 as the default width')
check('class="row content-grid stock-grid"' in index, 'Stock cards use one responsive grid')
check('class="col-12 col-md-6 col-lg-3 stock-card"' in index, 'Stock grid follows the same responsive columns')
check('$row_cnt' not in index, 'legacy four-item PHP row counter is removed')
check('style="padding: 0px; margin: 2px;"' not in index, 'card spacing is no longer inline')
check('.content-grid' in css and '.feed-card,' in css and '.stock-card' in css, 'grid spacing is centralized in Dashboard CSS')
check('@media (max-width: 767.98px)' in css, 'Mobile breakpoint is explicit')
check('@media (min-width: 768px) and (max-width: 991.98px)' in css, 'Tablet breakpoint is explicit')
check('table-layout: fixed' in css, 'Feed tables use stable fixed layout')
check(css.count('overflow-wrap: anywhere') >= 2 and 'word-break: break-word' in css, 'long titles and URLs can wrap')
check('min-height: 13rem' in css and 'min-height: 11rem' in css, 'Feed card height is stabilized across widths')
check('min-width: 44px' in css and 'min-height: 44px' in css, 'small icon actions receive touch-sized targets')
check('.save-modal-dialog' in css and 'style="width: 240px;"' not in index, 'Stock modal no longer uses a fixed inline width')
check('.modal-footer .btn' in css and 'flex: 1 1 100%' in css, 'Modal actions stack on narrow screens')
check('.app-navbar-current' in css and 'text-overflow: ellipsis' in css, 'long current-tab names do not break the Navbar')
check('#page-top' in css and 'z-index: 1040' in css, 'Page Top stays usable without falling behind content')

check('id="app-notice"' in index and 'aria-live="polite"' in index, 'shared UI notice region is present')
check('function showNotice(message, type' in js, 'mutation feedback uses one notice helper')
check('alert(' not in js, 'browser alert is no longer used for Dashboard feedback')
check("showNotice('Stockへ保存しました', 'success', 2500)" in js, 'Stock success has visible page feedback')
check("$('#saveContent').modal('hide')" in js, 'Stock modal closes only after successful save')
check('type="button" class="btn btn-outline-danger delete_content"' in index, 'RSS deletion has an explicit button')
check("window.confirm('このRSSを削除しますか？')" in js, 'RSS deletion asks for confirmation')
check("apiRequest('content.delete', {'content_id': contentId}" in js, 'explicit deletion keeps the existing API action')
check("apiRequest('content.update', {" in js, 'RSS changes keep a separate update action')
check("var action = contentValue === ''" not in js, 'empty URL is no longer an implicit delete command')
check('id="changeContentValue"' in index and 'type="url"' in index and 'required inputmode="url"' in index, 'RSS edit URL uses browser URL validation')
check('id="registerContentValue"' in index and 'placeholder="https://example.com/feed.xml"' in index, 'RSS add form gives a useful URL example')
check('追加先：<?php echo app_html($addTargetName); ?>' in index, 'RSS add modal states its destination tab')
check("$addTargetLocation = is_int($tabParam) ? $tabParam : 0;" in index, 'Stock-page RSS additions keep the existing tab-1 destination')

check(".addClass('btn btn-sm btn-outline-secondary feed-retry')" in js, 'Feed error state includes a retry control')
check(".on('click' + eventNamespace, '.feed-retry'" in js, 'Feed retry uses delegated event handling')
check("fetch_content($(this).closest('[data-feed-content-id]'))" in js, 'Feed retry remains scoped to its card')
check('Stockした記事はまだありません。' in index, 'Stock has a specific empty state')
check('このタブにはWidgetが登録されていません。' in index, 'RSS tabs have a specific Widget-aware empty state')
check('RSSを追加する' in index, 'empty RSS tab offers the existing add action')
check('<h5 class="modal-title" id="registerContentTitle">RSSを追加</h5>' in index, 'RSS add modal has a direct title')
check('<h5 class="modal-title" id="changeContentTitle">RSSを変更</h5>' in index, 'RSS edit modal has a direct title')
check('<h5 class="modal-title" id="changeConfTitle">表示設定</h5>' in index, 'Settings modal title is clear')
check('Nabvar Link' not in index and 'Navbarリンク' in index, 'Drawer Navbar label typo is corrected')
check('iGugur RSS Reader' not in login and 'iGuguru RSS Reader' in login, 'Login product name is consistent')

check('.html(' not in js and 'innerHTML' not in js, 'UI changes retain text-based safe DOM rendering')
check("url: './api_v1.php'" in js and "'csrf_token': appCsrfToken()" in js, 'API endpoint and CSRF path remain unchanged')
for action in ['content.create', 'content.update', 'content.delete', 'stock.create', 'settings.update', 'tabs.update', 'feed.fetch']:
    check(action in js or action in api, f'existing API action remains represented: {action}')
check(not (ROOT / 'package.json').exists(), 'M2-D adds no npm or build dependency')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M2-D responsive/UI structure checks passed.')
