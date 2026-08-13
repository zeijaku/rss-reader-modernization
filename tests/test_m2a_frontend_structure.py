from pathlib import Path
import re
import sys

from dashboard_source_utils import dashboard_source
ROOT = Path(__file__).resolve().parents[1]
index = dashboard_source(ROOT)
js = (ROOT / 'public/js/dashboard.js').read_text(encoding='utf-8')
css = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
api = (ROOT / 'app/api.php').read_text(encoding='utf-8')
schema = (ROOT / 'database/schema.sql').read_text(encoding='utf-8')

checks = []
def check(cond, msg):
    checks.append(bool(cond))
    print(('PASS' if cond else 'FAIL') + ': ' + msg)

check((ROOT / 'public/js/dashboard.js').is_file(), 'dashboard JavaScript asset exists')
check((ROOT / 'public/css/dashboard.css').is_file(), 'dashboard CSS asset exists')
check("app_asset_url('css/dashboard.css')" in index, 'dashboard CSS is loaded through the Asset helper')
check("app_asset_url('js/dashboard.js')" in index, 'dashboard JavaScript is loaded through the Asset helper')
check(index.index("app_asset_url('js/jquery-3.7.1.min.js')") < index.index("app_asset_url('js/dashboard.js')"), 'jQuery loads before dashboard JavaScript')
check(index.index("app_asset_url('js/bootstrap.min.js')") < index.index("app_asset_url('js/dashboard.js')"), 'Bootstrap loads before dashboard JavaScript')
check(index.index("app_asset_url('js/drawer.min.js')") < index.index("app_asset_url('js/dashboard.js')"), 'Drawer loads before dashboard JavaScript')
check('<style>' not in index, 'dashboard style block is removed from index.php')
check(not re.search(r'<script(?![^>]*\bsrc=)(?![^>]*\btype="application/json")[^>]*>', index), 'application inline JavaScript is removed from index.php')
check('$window_load' not in index, 'PHP no longer builds a JavaScript Feed load list')
check('fetch_content(' not in index, 'PHP no longer emits Feed JavaScript calls')
check('data-feed-content-id="' in index, 'Feed cards expose Content ID through a data hook')
check('class="content-value"' in index, 'Feed edit value uses a local card hook')
check(bool(re.search(r'class="[^"]*\bcontent-title\b[^"]*"', index)), 'Feed title uses a local card hook')
check('class="content-body"' in index, 'Feed body uses a local card hook')
check('content_id_' not in index and 'content_title_' not in index and 'content_body_' not in index, 'dynamic CSS class selectors are removed')
check("'use strict';" in js and '(function ($, window, document)' in js, 'dashboard code keeps a small IIFE boundary')
check("var eventNamespace = '.iguguruDashboard';" in js, 'dashboard events use one namespace')
check(".off('click' + eventNamespace" in js and ".on('click' + eventNamespace" in js, 'click handlers are replaced before registration')
check(".off('submit' + eventNamespace" in js and ".on('submit' + eventNamespace" in js, 'submit handlers are replaced before registration')
check("iguguru-dashboard-initialized" in js, 'dashboard initialization has a duplicate guard')
check("function requestStart" in js and "function requestEnd" in js, 'request pending state is centralized')
check("data('request-pending')" in js, 'request pending state prevents repeated actions')
check("prop('disabled', true)" in js and "prop('disabled', false)" in js, 'request buttons are disabled only while pending')
check(js.count('.always(function ()') >= 5, 'all mutation request paths release pending state')
check("function apiRequest" in js, 'API request helper remains centralized')
check("url: './api_v1.php'" in js and "method: 'POST'" in js, 'API endpoint and POST method remain unchanged')
check("dataType: 'json'" in js and "cache: false" in js, 'API JSON and cache behavior remain explicit')
check("'csrf_token': appCsrfToken()" in js, 'every API request receives the CSRF token')
for action in ['content.create', 'content.update', 'content.delete', 'stock.create', 'settings.update', 'tabs.update', 'feed.fetch']:
    check(action in js or action in api, f'API action remains represented: {action}')
feed = js[js.find('function fetch_content'):js.find('function bindEvents')]
check("apiRequest('feed.fetch', {'content_id': content_id}" in feed, 'Feed request sends only Content ID')
check('content_value' not in feed and 'data-feed-url' not in index, 'Feed fetch does not use a client-supplied URL')
check("/^\\d+$/.test(content_id)" in feed, 'Feed Content ID is checked before request')
check("rendered < itemLimit" in js, 'Feed display remains capped at five items')
check(js.count('.text(viewTitle)') >= 2 and '.text(title)' in js, 'Feed text remains inserted with text()')
check(".attr('href', itemLink)" in js and "noopener noreferrer" in js, 'Feed links retain hardened attributes')
for unsafe in ['.html(', 'innerHTML', 'insertAdjacentHTML', 'document.write(', 'eval(', 'new Function']:
    check(unsafe not in js, f'unsafe DOM/code operation remains absent: {unsafe}')
check('#page-top' in css and 'table tr:hover' in css, 'moved CSS keeps current dashboard rules')
check('CREATE TABLE' in schema, 'database schema remains present and outside frontend changes')
check('package.json' not in [p.name for p in ROOT.iterdir()], 'M2-A adds no npm/build dependency')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M2-A frontend structure checks passed.')
