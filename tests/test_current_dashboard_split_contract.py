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
    check(index.find(tag) >= 0, f'{label} split controller is loaded through app_asset_url')
    check('window.IGuguruDashboardCore' in source, f'{label} consumes the shared Dashboard core')
    check(f'window.{namespace} = {{' in source, f'{label} exposes one explicit split-controller namespace')
    check('bindEvents: bindEvents' in source, f'{label} exposes its delegated-event binder')
    check('$.ajax(' not in source, f'{label} does not duplicate the shared jQuery transport')
    check("url: './api_v1.php'" not in source, f'{label} does not duplicate the API endpoint')
    for action in actions:
        check(action in source, f'{label} preserves API action: {action}')
    for selector in selectors:
        check(selector in source, f'{label} preserves delegated selector: {selector}')

load_positions.append(index.find(dashboard_tag))
check(all(pos >= 0 for pos in load_positions), 'all split Dashboard scripts are present in the Dashboard entrypoint')
check(load_positions == sorted(load_positions), 'load order is core -> split controllers -> dashboard.js')

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
    check(f'function {name}(' not in dashboard, f'dashboard.js no longer owns extracted function: {name}')

for namespace in ('IGuguruDashboardTask', 'IGuguruDashboardMemo', 'IGuguruDashboardGame', 'IGuguruDashboardClock'):
    check(f'bindFeatureModule(window.{namespace});' in dashboard,
          f'dashboard.js initializes split controller: {namespace}')

check("function renderClock($card, now)" in dashboard,
      'Clock display/timer rendering remains in dashboard.js in B')
check("function widgetBeginDrag(" in dashboard and "function widgetFinishDrag(" in dashboard,
      'Dashboard D&D remains untouched in B')
check("function fetch_content(" in dashboard,
      'RSS feed rendering/refresh remains untouched in B')

for test_name in (
    'test_current_dashboard_split_contract.py',
    'test_current_dashboard_split_runtime.js',
):
    check(test_name in runner, f'current regression includes {test_name}')

for path, _, _, _ in modules.values():
    check(f'node --check "$ROOT/{path}"' in runner, f'current regression syntax-checks {path}')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
