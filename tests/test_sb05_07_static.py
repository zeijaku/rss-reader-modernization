from pathlib import Path
import re
import sys

from dashboard_source_utils import dashboard_source
ROOT = Path(__file__).resolve().parents[1]
api_endpoint = (ROOT/'public/api_v1.php').read_text()
api = (ROOT/'app/api.php').read_text()
index = dashboard_source(ROOT)
dashboard = (ROOT / 'public' / 'js' / 'dashboard.js').read_text(encoding='utf-8')
frontend = index + '\n' + dashboard
db = (ROOT/'app/common/common_db.php').read_text()
login = (ROOT/'app/common/common_login.php').read_text()
version = (ROOT/'app/version.php').read_text()

checks=[]
def check(cond,msg):
    print(('PASS' if cond else 'FAIL')+': '+msg)
    checks.append(bool(cond))

check("!== 'POST'" in api_endpoint and "method_not_allowed" in api_endpoint and "405" in api_endpoint, 'API is POST-only with structured 405')
check('app_session_start();' in api_endpoint and 'app_session_user_id()' in api_endpoint and 'unauthenticated' in api_endpoint, 'API requires authenticated application session')
check('app_csrf_is_valid' in api_endpoint and 'csrf_invalid' in api_endpoint and '403' in api_endpoint, 'API validates per-session CSRF token')
check("$_POST['action']" in api_endpoint and 'api_dispatch($action' in api_endpoint, 'endpoint uses explicit action dispatcher')
check("header('Location:" not in api_endpoint, 'API endpoint contains no browser redirect')
check("'ok' => true" in api and "'ok' => false" in api and "'error' => [" in api, 'API success/error JSON contract is centralized')
for action in ['content.create','content.update','content.delete','stock.create','settings.update','tabs.update','feed.fetch','feed.new.clear']:
    check(repr(action).replace('"', "'") in api or f"'{action}'" in api, f'explicit action exists: {action}')

check("$input['content_owner']" not in api and "$input['save_owner']" not in api and "$input['user_id']" not in api, 'dispatcher never reads client-supplied owner/user id fields')
check(('entry_content($userId' in api or 'dashboard_widget_create_feed($userId' in api) and 'info_dbsave($userId' in api, 'create operations derive owner from authenticated user id')
check('find_owned_active_content($userId, $contentId)' in api, 'resource access checks authenticated ownership')
check("WHERE content_id = :content_id AND content_owner = :owner" in db, 'content lookup SQL scopes by resource id and owner')
check("WHERE content_id = :content_id AND content_owner = :owner AND content_flag = 0" in db, 'content update/delete SQL contains owner predicate')
check("$input['steal_content']" not in api and 'FeedFetchService::fromRuntimeConfiguration()' in api and '->load($source)' in api, 'feed.fetch does not accept a client raw fetch URL')
check("'feed.fetch'" in dashboard and "'content_id': content_id" in dashboard, 'browser sends only content id for feed.fetch')
feed_client = dashboard[dashboard.find('function fetch_content'):dashboard.find('function bindEvents')]
check('$window_load' not in index and 'content_value' not in feed_client and "'content_id': content_id" in feed_client, 'DB feed URL is not embedded into dashboard Feed request')
check("'content_owner':" not in index and "'save_owner':" not in index and 'name="user_id"' not in index, 'browser no longer submits owner/user target fields')
check('setting_token' not in index, 'fixed Legacy setting token removed')
check("'csrf_token': appCsrfToken()" in dashboard and "function apiRequest" in dashboard, 'all browser API calls pass CSRF through shared helper')
check(login.count('name="csrf_token"') >= 2, 'login and registration forms carry CSRF token')
check('app_csrf_is_valid($submittedCsrf)' in index and '$authCsrfInvalid' in index, 'login/registration POST validates CSRF before auth/register')
check('app_csrf_is_valid' in (ROOT/'public/logout.php').read_text(), 'logout remains CSRF protected')
check('const APP_VERSION =' in version and 'const APP_VERSION_LABEL =' in version, 'visible release marker infrastructure remains present')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} SB-05/SB-06/SB-07 static checks passed.')
