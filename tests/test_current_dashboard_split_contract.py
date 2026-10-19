#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def text(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


index = text('public/index.php')
core = text('public/js/dashboard-core.js')
dashboard = text('public/js/dashboard.js')
runner = text('tests/run-current.sh')

modules = {
    'Task': ('public/js/dashboard-task.js', 'IGuguruDashboardTask', [
        'widget.task.create', 'widget.task.update', 'widget.task.delete',
        'task.item.create', 'task.item.update', 'task.item.toggle', 'task.item.delete',
    ], [
        '#registerTaskWidgetForm', '.task-widget-edit-trigger', '#changeTaskWidgetForm',
        '.delete_task_widget', '.task-item-create-form', '.task-item-edit-trigger',
        '#changeTaskItemForm', '.task-toggle', '.delete_task_item',
    ]),
    'Memo': ('public/js/dashboard-memo.js', 'IGuguruDashboardMemo', [
        'widget.memo.create', 'widget.memo.update', 'widget.memo.delete',
    ], [
        '#registerMemoForm', '.memo-edit-trigger', '#changeMemoForm', '.delete_memo',
    ]),
    'Game': ('public/js/dashboard-game.js', 'IGuguruDashboardGame', [
        'widget.game.create', 'widget.game.update', 'widget.game.delete',
    ], [
        '#registerGameWidgetForm', '.registerGameType', '.changeGameType',
        '.mini-game-edit-trigger', '#changeGameWidgetForm', '.delete_game_widget',
    ]),
    'Clock': ('public/js/dashboard-clock.js', 'IGuguruDashboardClock', [
        'widget.clock.create', 'widget.clock.update', 'widget.clock.delete',
    ], [
        '#registerClockForm', '.clock-edit-trigger', '#changeClockForm', '.delete_clock',
    ]),
}

core_tag = "app_asset_url('js/dashboard-core.js')"
dashboard_tag = "app_asset_url('js/dashboard.js')"
check(core_tag in index and dashboard_tag in index, 'Dashboard core and main controller remain versioned entry assets')

load_positions = [index.find(core_tag)]
for label, (path, namespace, actions, selectors) in modules.items():
    source = text(path)
    asset = path.removeprefix('public/')
    tag = f"app_asset_url('{asset}')"
    load_positions.append(index.find(tag))
    check(index.find(tag) >= 0, f'{label} controller is loaded through app_asset_url')
    check('window.IGuguruDashboardCore' in source, f'{label} consumes the shared Dashboard core')
    check(f'window.{namespace} = {{' in source, f'{label} exposes one explicit controller namespace')
    check('bindEvents: bindEvents' in source, f'{label} exposes its event binder')
    check('document.addEventListener(' in source, f'{label} uses native delegated events')
    check('.querySelector(' in source, f'{label} uses native querySelector')
    check('.closest(' in source, f'{label} preserves delegated dynamic-element lookup with closest')
    check('const runMutation = dashboardCore.runMutation;' in source,
          f'{label} uses the shared native Widget mutation helper')
    check('runMutation(' in source, f'{label} preserves guarded Widget mutation call sites')
    check('jQuery' not in source and '$(' not in source and '$.' not in source,
          f'{label} has no direct jQuery dependency')
    check('.on(' not in source and '.off(' not in source,
          f'{label} has no jQuery event binding')
    check('var ' not in source, f'{label} no longer declares legacy var bindings')
    check("url: './api_v1.php'" not in source and 'fetch(' not in source,
          f'{label} does not duplicate or bypass the shared API transport')
    for action in actions:
        check(action in source, f'{label} preserves API action: {action}')
    for selector in selectors:
        check(selector in source, f'{label} preserves delegated selector: {selector}')

load_positions.append(index.find(dashboard_tag))
check(all(pos >= 0 for pos in load_positions), 'all Dashboard scripts are present in the Dashboard entrypoint')
check(load_positions == sorted(load_positions), 'load order remains core -> split controllers -> dashboard.js')

swipe = text('public/js/dashboard-tab-swipe.js')
swipe_tag = "app_asset_url('js/dashboard-tab-swipe.js')"
check(swipe_tag in index and index.index(swipe_tag) < index.index(dashboard_tag),
      'tab swipe controller loads before the Dashboard initializer')
check('function dashboardSwipeStart(' in swipe and 'function dashboardSwipeEnd(' in swipe
      and 'function initTabSwipe(' in swipe, 'tab swipe behavior was extracted intact')
check('function dashboardSwipeStart(' not in dashboard and 'function dashboardSwipeEnd(' not in dashboard,
      'dashboard.js delegates rather than duplicating touch navigation')
check('isDrawerActive: drawerIsActive' in dashboard
      and 'IGuguruDashboardWidgetOrder.isDragging()' in dashboard,
      'swipe controller gets Drawer and extracted Widget dragging state')


for helper in ('apiRequestPromise', 'requestStartElement', 'requestEndElement', 'requestFailReason'):
    check(f'function {helper}' in core and f'{helper}: {helper}' in core,
          f'Dashboard core exposes native adapter: {helper}')
check('$.ajax({' in core, 'C intentionally keeps the proven jQuery transport centralized in Dashboard core')
check("xhr.getResponseHeader('X-CSRF-Token')" in core,
      'C preserves jQuery transport CSRF rotation handling')
check("xhr.status === 401 && code === 'unauthenticated'" in core,
      'C preserves the existing unauthenticated reload boundary')

extracted_functions = [
    'clockFormPayload', 'addClock', 'editClock', 'changeClock', 'deleteClock',
    'gameFormPayload', 'gameDefaultTitle', 'syncGameDefaultTitle', 'addGameWidget',
    'editGameWidget', 'removeGameWidgetBrowserState', 'changeGameWidget', 'deleteGameWidget',
    'memoFormPayload', 'addMemo', 'editMemo', 'changeMemo', 'deleteMemo',
    'taskWidgetFormPayload', 'addTaskWidget', 'editTaskWidget', 'changeTaskWidget',
    'deleteTaskWidget', 'taskItemPayload', 'addTaskItem', 'editTaskItem',
    'changeTaskItem', 'toggleTaskItem', 'deleteTaskItem',
]
for name in extracted_functions:
    check(f'function {name}(' not in dashboard, f'dashboard.js still does not reclaim extracted function: {name}')

for namespace in ('IGuguruDashboardTask', 'IGuguruDashboardMemo', 'IGuguruDashboardGame', 'IGuguruDashboardClock'):
    check(f'bindFeatureModule(window.{namespace});' in dashboard,
          f'dashboard.js initializes native controller: {namespace}')

check("function renderClock($card, now)" in dashboard,
      'Clock display/timer rendering remains untouched in dashboard.js')
order = text('public/js/dashboard-widget-order.js')
order_tag = "app_asset_url('js/dashboard-widget-order.js')"
check(order_tag in index and index.index(order_tag) < index.index(dashboard_tag),
      'Widget order controller loads before Dashboard initializer')
check("function widgetBeginDrag(" in order and "function widgetFinishDrag(" in order
      and "function widgetKeyboardMove(" in order and "function widgetSaveOrder(" in order,
      'Widget D&D, keyboard actions and save/restore are preserved in dedicated module')
check("function widgetBeginDrag(" not in dashboard and "function widgetFinishDrag(" not in dashboard,
      'Dashboard main module no longer duplicates Widget ordering')
check('IGuguruDashboardWidgetOrder.bindEvents();' in dashboard,
      'Dashboard initializes Widget order handlers')
check('widget.reorder' in order and 'previous_widget_ids' in order and 'widget_ids' in order,
      'Widget reorder API and optimistic prior-order payload remain intact')
feed_renderer = text('public/js/dashboard-feed-render.js')
article_menu_position = text('public/js/dashboard-article-menu-position.js')

article_position_tag = "app_asset_url('js/dashboard-article-menu-position.js')"
check(article_position_tag in index and index.index(article_position_tag) < index.index(dashboard_tag),
      'Article Actions geometry loads before Dashboard controller')
check('function positionArticleActionsMenu(' in article_menu_position
      and 'window.IGuguruDashboardArticleMenuPosition' in article_menu_position,
      'Article Actions geometry retains a single module-scoped implementation')
check('return window.IGuguruDashboardArticleMenuPosition.positionArticleActionsMenu($menu, $trigger);' in dashboard
      and 'maxWidth: Math.min(224, availableWidth)' not in dashboard,
      'Dashboard delegates geometry without duplicating layout computation')
check('test_current_article_menu_position_runtime.js' in runner,
      'Current CI gate executes Article Actions geometry regression')

feed_render_tag = "app_asset_url('js/dashboard-feed-render.js')"
check(feed_render_tag in index and index.index(feed_render_tag) < index.index(dashboard_tag),
      'Feed renderer loads before Dashboard controller')
check('function renderFeedItems(' in feed_renderer
      and 'function renderFeedMessage(' in feed_renderer,
      'Feed DOM rendering was extracted to dedicated module')
check('function renderFeedItems(' in dashboard and 'return feedRenderer().renderFeedItems(' in dashboard,
      'Dashboard keeps compatibility with existing Feed call sites')
check('feedRenderer().configure({' in dashboard
      and 'renderFeedKeywordTitle: renderFeedKeywordTitle' in dashboard,
      'Feed renderer receives article-action and keyword highlight hooks')
feed_title = text('public/js/dashboard-feed-title.js')
feed_title_tag = "app_asset_url('js/dashboard-feed-title.js')"
check(feed_title_tag in index
      and index.index(feed_render_tag) < index.index(feed_title_tag) < index.index(dashboard_tag),
      'title tooltip module is loaded before Dashboard initialization')
check('function showFeedTitleTooltip(' in feed_title
      and 'function scheduleFeedTitleOverflowRefresh(' in feed_title,
      'title tooltip positioning and overflow checks are isolated')
check('var feedTitleTooltipTimer' not in dashboard
      and 'IGuguruDashboardFeedTitle.hideFeedTitleTooltip()' in dashboard
      and 'IGuguruDashboardFeedTitle.scheduleFeedTitleOverflowRefresh($scope)' in dashboard,
      'Dashboard retains only the tooltip adapter and no longer owns its state')
check("feedTitleIsTruncated(this)" in dashboard
      and "hideFeedTitleTooltip: hideFeedTitleTooltip" in dashboard,
      'keyword highlighting and Feed renderer keep their existing tooltip hooks')

feed_fetch = text('public/js/dashboard-feed-fetch.js')
feed_fetch_tag = "app_asset_url('js/dashboard-feed-fetch.js')"
check(feed_fetch_tag in index
      and index.index(feed_render_tag) < index.index(feed_fetch_tag) < index.index(dashboard_tag),
      'RSS Fetch controller loads after renderer and before Dashboard')
check("function fetch_content(" in dashboard
      and "IGuguruDashboardFeedFetch.fetch_content($card, options)" in dashboard,
      'Dashboard delegates existing refresh call sites')
check("apiRequest('feed.fetch', {'content_id': content_id}, 25000)" in feed_fetch
      and "feed-request-pending" in feed_fetch,
      'RSS network request and per-card pending guard are extracted intact')
check("closeArticleActionsMenu: closeArticleActionsMenu" in dashboard,
      'Feed fetch uses the original Article Actions close hook')

search_feed = text('public/js/dashboard-search-feed.js')
search_tag = "app_asset_url('js/dashboard-search-feed.js')"
category_tag = "app_asset_url('js/search-feed-owned-category.js')"
check(search_tag in index and category_tag in index
      and index.index(feed_fetch_tag) < index.index(search_tag) < index.index(dashboard_tag)
      < index.index(category_tag),
      'Search Feed loads after Fetch and before Dashboard and Category bridge')
check("IGuguruDashboardSearchFeed.fetchSearchFeed($card, preserve)" in dashboard
      and "IGuguruDashboardSearchFeed.addSearchFeed($form)" in dashboard,
      'Dashboard delegates Search Feed calls without changing existing event bindings')
check("widget.search.fetch" in search_feed and "widget.search.create" in search_feed
      and "widget.search.update" in search_feed and "widget.search.delete" in search_feed,
      'Search Feed CRUD and fetch API actions remain unchanged')
check("return core.apiRequest(action, data, timeout)" in search_feed,
      'Search Feed dynamically calls Core API so Category bridge still injects filter')

for test_name in (
    'test_current_dashboard_core_contract.py',
    'test_current_dashboard_core_runtime.js',
    'test_current_dashboard_split_contract.py',
    'test_current_dashboard_split_runtime.js',
):
    check(test_name in runner, f'current regression includes {test_name}')

for path, _, _, _ in modules.values():
    check(f'node --check "$ROOT/{path}"' in runner, f'current regression syntax-checks {path}')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
