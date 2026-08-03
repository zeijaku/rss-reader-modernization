from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
domain = (ROOT / 'app/dashboard_widget.php').read_text(encoding='utf-8')
api = (ROOT / 'app/api.php').read_text(encoding='utf-8')
conf = (ROOT / 'app/common/common_conf.php').read_text(encoding='utf-8')
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
js = (ROOT / 'public/js/dashboard.js').read_text(encoding='utf-8')
css = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
run = (ROOT / 'tests/run.sh').read_text(encoding='utf-8')
local = (ROOT / 'tests/run-local-v1-1-g.sh').read_text(encoding='utf-8') if (ROOT / 'tests/run-local-v1-1-g.sh').exists() else ''
gitignore = (ROOT / '.gitignore').read_text(encoding='utf-8')

checks=[]
def check(cond,msg):
    checks.append(bool(cond)); print(('PASS' if cond else 'FAIL')+': '+msg)

check("'memo'" in domain.split('function dashboard_widget_types',1)[1].split('}',1)[0], 'Memo is an allowed Dashboard Widget type')
check('function dashboard_widget_validate_memo_title' in domain, 'Memo title has a dedicated validator')
check('app_validate_text($value, 32, false)' in domain, 'Memo title is required and limited to 32 characters')
check('function dashboard_widget_validate_memo_body' in domain, 'Memo body has a dedicated validator')
check('app_validate_text($value, 4000, false)' in domain, 'Memo body is required and limited to 4000 characters')
check('str_replace(["\\r\\n", "\\r"], "\\n", $value)' in domain, 'Memo line endings are normalized')
check("in_array($type, ['feed', 'memo'], true)" in domain, 'Memo rows require a reference id')
check("db_table_identifier('memo')" in domain, 'Memo content is stored in its own prefixed table')
check("w.widget_type = 'memo'" in domain and 'm.memo_owner' in domain, 'Dashboard query joins Memo by type, reference and owner')
check("$normalized['widget_type'] === 'memo'" in domain, 'Memo rows are owner and active-state checked before display')
check('function dashboard_widget_create_memo' in domain, 'Memo create is implemented in Widget domain layer')
check('function dashboard_widget_update_memo' in domain, 'Memo update is implemented in Widget domain layer')
check('function dashboard_widget_delete_memo' in domain, 'Memo delete is implemented in Widget domain layer')
check("widget_type = 'memo'" in domain and "'memo', :reference_id" in domain, 'Memo placement uses a typed Widget reference')
check(domain.count('beginTransaction()') >= 6 and domain.count('rollBack()') >= 6, 'Memo writes keep the existing transaction pattern')
check('dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, \'memo\')' in domain, 'Memo update/delete locks an owner-scoped Widget')
check('dashboard_widget_lock_owned_memo' in domain, 'Memo update/delete locks the owned Memo row')
check("'memo'" in conf.split('static $allowed',1)[1].split(';',1)[0], 'memo is an allowed logical table name')
for action in ['widget.memo.create','widget.memo.update','widget.memo.delete']:
    check(action in api, f'Memo API action is registered: {action}')
check('dashboard_widget_create_memo($userId' in api, 'Memo create owner comes from authenticated user')
check('dashboard_widget_update_memo($userId' in api, 'Memo update owner comes from authenticated user')
check('dashboard_widget_delete_memo($userId' in api, 'Memo delete owner comes from authenticated user')
check("$input['widget_owner']" not in api[api.find('function api_widget_memo_create'):api.find('function api_stock_create')], 'Memo API does not trust a client owner field')
check("api_error('memo_unavailable'" in api, 'missing Memo migration has a structured API response')
check('data-dashboard-widget-type="memo"' in index, 'Memo renders as a Dashboard Widget')
check('class="memo-body"' in index, 'Memo body has a text-only render hook')
check("app_html($memoBody)" in index and "app_html($memoTitle)" in index, 'Memo title and body are escaped at output')
check('class="btn btn-link memo-edit-trigger"' in index, 'Memo has a separate edit control')
check('id="registerMemoForm"' in index and 'id="changeMemoForm"' in index, 'Memo add and edit forms render')
check('maxlength="4000"' in index and 'maxlength="32"' in index, 'Memo form bounds match server validation')
check('data-target="#registerMemo"' in index and 'Memo追加' in index, 'Drawer exposes Memo addition')
for name in ['addMemo','editMemo','changeMemo','deleteMemo','memoFormPayload']:
    check(f'function {name}' in js, f'Frontend implements {name}')
for action in ['widget.memo.create','widget.memo.update','widget.memo.delete']:
    check(action in js, f'Frontend uses protected API action {action}')
check("$card.find('.memo-title').first().text()" in js and "$card.find('.memo-body').first().text()" in js, 'Memo edit reads text rather than HTML')
check('.html(' not in js, 'Dashboard JavaScript still avoids HTML insertion')
check('white-space: pre-wrap' in css and '.memo-body' in css, 'Memo preserves line breaks with CSS rather than HTML')
check('.memo-card-header' in css and 'height: 44px' in css, 'Memo header aligns with existing Widget headers')
check('.memo-card-body' in css and 'min-height: calc(13rem - 44px)' in css, 'Memo card keeps the Dashboard height contract')
check('.memo-textarea' in css and 'resize: vertical' in css, 'Memo editor remains usable for longer text')
check((ROOT/'database/migrations/004_v1_1_memo.sql').is_file(), 'existing DB migration 004 is included')
check('!/database/migrations/004_v1_1_memo.sql' in gitignore and '!/database/audit/v1_1_g_postflight.sql' in gitignore, 'sanitized V1.1-G SQL files are not hidden by the SQL dump ignore rule')
check((ROOT/'tools/db_v11g.php').is_file(), 'Memo migration verify/apply tool is included')
check(re.search(r"const APP_VERSION = '1\.1\.0-dev\.[6-9][0-9]*';", version) is not None and any(label in version for label in ['V1.1-G / R1','V1.1-H / R1','V1.1-I / R1','V1.1-I / R2']), 'visible Version marker identifies V1.1-G or later')
check('test_v11g_memo_widget.php' in run and 'test_v11g_sql.py' in run, 'main runner includes V1.1-G checks')
check('test_v11g_memo_widget.php' in local and 'test_v11g_frontend_runtime.js' in local, 'local runner includes V1.1-G checks')

if not all(checks): sys.exit(1)
print(f'All {len(checks)} V1.1-G architecture checks passed.')
