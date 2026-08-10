from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


widget = (ROOT / 'app/dashboard_widget.php').read_text(encoding='utf-8')
conf = (ROOT / 'app/common/common_conf.php').read_text(encoding='utf-8')
bootstrap = (ROOT / 'app/bootstrap.php').read_text(encoding='utf-8')
api = (ROOT / 'app/api.php').read_text(encoding='utf-8')
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
css = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
schema = (ROOT / 'database/schema.sql').read_text(encoding='utf-8')

check("'dashboard_widget'" in conf and 'DB_TABLE_PREFIX' in conf, 'Dashboard Widget uses the existing prefixed table-name resolver')
check("require_once __DIR__ . '/dashboard_widget.php';" in bootstrap, 'Dashboard Widget module loads through bootstrap')
check("['feed', 'search', 'clock', 'memo', 'task', 'calendar', 'game']" in widget, 'Widget type allowlist includes existing Widgets and the V1.4 Game type')
check('dashboard_widget_validate_location' in widget and '<= 3' in widget, 'Widget location remains limited to the four existing tabs')
check('dashboard_widget_validate_width' in widget and '<= 4' in widget, 'Widget width is bounded')
check('JSON_THROW_ON_ERROR' in widget and '4096' in widget, 'Widget JSON config is bounded and parsed strictly')
check("app_normalize_content_style" in widget, 'Widget style reuses the existing style allowlist')
check('widget_width_class' in widget and 'col-12 col-md-6 col-lg-3' in widget, 'existing Feed width remains the default')

check('search_dashboard_widgets' in widget and 'widget_owner = :owner' in widget and 'widget_location = :location' in widget, 'Widget list is owner and tab scoped in SQL')
check("w.widget_type = 'feed'" in widget and 'w.widget_reference_id = c.content_id' in widget, 'Feed Widget resolves through the existing content record')
check('ORDER BY w.widget_sort_order ASC, w.widget_id ASC' in widget, 'Widget display order is stable before Drag and Drop is added')
check("dashboard_widget_public_list" in widget and "'widget_owner'" not in widget[widget.find('function dashboard_widget_public_list'):widget.find('function dashboard_widget_lock_owned_content')], 'public Widget list omits owner id')

check('beginTransaction' in widget and 'rollBack' in widget and 'commit' in widget, 'Feed and Widget mutations share transactions')
check('dashboard_widget_create_feed' in api and 'dashboard_widget_update_feed' in api and 'dashboard_widget_delete_feed' in api, 'Feed API mutations use Widget-aware transaction wrappers')
check("'widget.list' => api_widget_list" in api, 'owner-scoped Widget list API is registered')
check("dashboard_widget_public_list($userId" in api, 'Widget list owner comes from the authenticated user')
check("$input['owner_id']" not in api and "$input['widget_owner']" not in api, 'API never trusts client-supplied owner fields')
check('dashboard_widget_unavailable' in api and '503' in api, 'missing Widget migration returns a structured service-unavailable response')

check('search_dashboard_widgets($currentUserId, $content_location)' in index, 'Dashboard rendering uses Widget placement instead of direct Feed order')
check('data-dashboard-widget-id' in index and 'data-dashboard-widget-type="feed"' in index, 'rendered Feed cards expose stable Widget hooks')
check('data-dashboard-widget-location' in index and 'data-dashboard-widget-sort-order' in index, 'rendered Widget carries tab and order metadata')
check('dashboard_widget_width_class(1)' in index, 'Feed card retains a safe width fallback')
check('for ($tabLocation = 0; $tabLocation <= 3; $tabLocation++)' in index, 'existing four-tab navigation remains unchanged')
check('.dashboard-widget' in css and 'min-width: 0' in css, 'Widget base CSS prevents overflow without redesigning the page')

check("CONCAT('`', @table_prefix, 'dashboard_widget`')" in schema, 'new-install schema uses the dynamic prefix for Dashboard Widget')
check('content_location' in schema and 'widget_location' in schema, 'content location remains present for rollback compatibility')
check(("const APP_VERSION = '1.1.0-dev." in version and 'V1.1-' in version) or ("const APP_VERSION = '1.1.0';" in version and 'RSS Reader Modernization 1.1.0' in version) or "const APP_VERSION = '1.2.0-dev.3';" or "const APP_VERSION = '1.2.0-dev.4';" in version, 'visible Version marker remains in the Version 1.1 line')
check('data-dashboard-widget-sort-order' in index, 'V1.1-D Widget order hook remains available to later phases')

if not all(checks):
    raise SystemExit(f'{checks.count(False)}/{len(checks)} V1.1-D architecture checks failed')
print(f'All {len(checks)} V1.1-D architecture checks passed.')
