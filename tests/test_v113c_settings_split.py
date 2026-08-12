#!/usr/bin/env python3
from __future__ import annotations
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
INDEX = ROOT / 'public' / 'index.php'
STOCK = ROOT / 'public' / 'stock.php'
SETTINGS = ROOT / 'public' / 'settings.php'
JS = ROOT / 'public' / 'js' / 'dashboard.js'
API = ROOT / 'public' / 'api_v1.php'
APP_API = ROOT / 'app' / 'api.php'

index = INDEX.read_text(encoding='utf-8')
stock = STOCK.read_text(encoding='utf-8')
settings = SETTINGS.read_text(encoding='utf-8')
js = JS.read_text(encoding='utf-8')
api_endpoint = API.read_text(encoding='utf-8')
app_api = APP_API.read_text(encoding='utf-8')
root_ht = (ROOT / '.htaccess').read_text(encoding='utf-8')
public_ht = (ROOT / 'public' / '.htaccess').read_text(encoding='utf-8')

checks: list[bool] = []
def check(condition: bool, message: str) -> None:
    ok = bool(condition)
    checks.append(ok)
    print(('PASS' if ok else 'FAIL') + ': ' + message)

check(SETTINGS.is_file(), 'public/settings.php exists')
check('app_session_start();' in settings and 'app_session_user_id();' in settings, 'Settings starts authenticated session state')
check("if ($currentUserId === null)" in settings and "header('Location: ./', true, 302);" in settings, 'unauthenticated Settings requests return to login entry point')
check('app_send_private_no_store_headers();' in settings, 'Settings keeps private no-store response headers')
check('access_log();' in settings, 'Settings keeps access logging')
check('<meta name="robots" content="noindex,nofollow">' in settings, 'private Settings page is noindex/nofollow')
check('meta name="csrf-token"' in settings, 'Settings exposes CSRF token only through existing meta contract')

for needle, label in [
    ('id="settingsForm"', 'Display Settings form'),
    ('id="tabsForm"', 'Tab names form'),
    ('id="rssHighlightKeywordForm"', 'RSS Highlight form'),
    ('id="rssHighlightKeywordList"', 'RSS Highlight list'),
    ('id="rssHighlightKeywordStatus"', 'RSS Highlight status'),
    ('id="accountSettings"', 'separate Account Settings modal'),
    ('id="drawerMenu"', 'Drawer'),
]:
    check(needle in settings, 'Settings page renders ' + label)

check('id="display"' in settings and 'id="tabs"' in settings and 'id="highlight"' in settings, 'Settings exposes stable section anchors')
check('表示設定' in settings and 'タブ表示変更' in settings and 'RSS Highlight' in settings, 'Settings page contains the three planned setting groups')
check('Account SettingsはV1.13-Cの分離対象外' in settings, 'Account Settings is explicitly kept outside the V1.13-C settings scope')

# The moved settings no longer remain as Dashboard/Stock modals.
for page, text, label in [(INDEX,index,'Dashboard'),(STOCK,stock,'Stock')]:
    check('id="changeConf"' not in text, label + ' no longer embeds Display Settings modal')
    check('id="tabContent"' not in text, label + ' no longer embeds Tab Settings modal')
    check('id="rssHighlightSettings"' not in text, label + ' no longer embeds RSS Highlight modal')
    check('href="./settings#tabs"' in text, label + ' Drawer links Tab Settings to /settings')
    check('href="./settings#display"' in text, label + ' Drawer links Display Settings to /settings')
    check('href="./settings#highlight"' in text, label + ' Drawer links RSS Highlight to /settings')
    check('id="accountSettings"' in text, label + ' keeps Account Settings separate')

# Dashboard still needs keyword JSON for actual title highlighting even though management UI moved.
check('id="rssHighlightKeywordData"' in index, 'Dashboard retains RSS Highlight JSON for feed-title rendering')
check('feed_keyword_list_user((int) $currentUserId)' in index, 'Dashboard still loads active Highlight keywords')

# Existing API write boundary remains unchanged.
for action in ['settings.update', 'tabs.update', 'feed.keyword.create', 'feed.keyword.delete']:
    check(action in app_api, action + ' remains dispatched through central app API')
check('app_csrf_is_valid' in api_endpoint and api_endpoint.find('app_csrf_is_valid') < api_endpoint.find('api_dispatch('), 'central API still verifies CSRF before dispatch')
check("apiRequest('settings.update'" in js, 'Display Settings still use shared AJAX API client')
check("apiRequest('tabs.update'" in js, 'Tab Settings still use shared AJAX API client')
check("apiRequest('feed.keyword.create'" in js and "apiRequest('feed.keyword.delete'" in js, 'RSS Highlight mutations still use shared AJAX API client')
check(".on('submit' + eventNamespace, '#settingsForm'" in js, 'Display Settings form keeps namespaced submit handler')
check(".on('submit' + eventNamespace, '#tabsForm'" in js, 'Tab Settings form keeps namespaced submit handler')
check(".on('submit' + eventNamespace, '#rssHighlightKeywordForm'" in js, 'RSS Highlight form keeps namespaced submit handler')

# Canonical extensionless route.
check("RewriteRule ^settings/?$ public/settings.php [L,QSA]" in root_ht, 'root .htaccess maps /settings to public/settings.php')
check("RewriteRule ^public/settings\\.php$ /%1settings [R=302,L,NE]" in root_ht, 'root .htaccess canonicalizes /public/settings.php')
check("RewriteRule ^settings\\.php$ /%1settings [R=302,L,NE]" in root_ht, 'root .htaccess canonicalizes /settings.php')
check("RewriteRule ^settings/?$ settings.php [L,QSA]" in public_ht, 'public .htaccess maps /settings to settings.php')
check(public_ht.count("RewriteRule ^settings\\.php$ /%1settings [R=302,L,NE]") >= 2, 'public .htaccess canonicalizes both direct PHP request shapes')
check('%{THE_REQUEST}' in root_ht and '%{THE_REQUEST}' in public_ht, 'Settings canonical redirect is guarded against internal rewrite loops')
check('settings.php?' not in index and 'settings.php?' not in stock and 'settings.php?' not in settings, 'generated application URLs do not expose settings.php query URLs')

# Output escaping / safe controls carried over from the existing forms.
check("app_selected_attr($ui['conf_style'] ?? '', $themeValue)" in settings, 'theme selection uses existing safe selected helper')
check("app_checked_attr($ui[$iconKey] ?? '', $iconOption)" in settings, 'navbar icon selection uses existing safe checked helper')
check("app_html($ui[$linkKey] ?? '')" in settings and "app_html($ui[$viewKey] ?? '')" in settings, 'navbar link values remain HTML escaped')
check("app_html($ui[$tabNameKey] ?? '')" in settings, 'tab names remain HTML escaped')
check("app_html((string) $feedKeyword['keyword_value'])" in settings, 'Highlight keyword values remain HTML escaped')
check('JSON_HEX_TAG' in settings and 'JSON_HEX_AMP' in settings and 'JSON_HEX_APOS' in settings and 'JSON_HEX_QUOT' in settings, 'Highlight JSON retains HTML-safe encoding flags')

# Settings page intentionally avoids Dashboard-only game/timer/calendar assets.
for asset in ['js/mini-game.js', 'js/lights-out.js', 'js/clock-timer.js', 'js/utility-widgets.js', 'js/calendar.js', 'css/mini-game.css', 'css/clock-timer.css', 'css/utility-widgets.css']:
    check(asset not in settings, 'Settings does not load unrelated asset: ' + asset)
for asset in ['js/jquery-3.7.1.min.js', 'js/bootstrap.min.js', 'js/drawer.min.js', 'js/dashboard.js', 'css/dashboard.css']:
    check(asset in settings, 'Settings retains required shared asset: ' + asset)

# No schema or direct DB mutation added by the page split.
check(not any('v1_13' in p.name.lower() for p in (ROOT / 'database').rglob('*.sql')), 'V1.13-C adds no DB migration')
upper = settings.upper()
check('INSERT INTO' not in upper and 'UPDATE ' not in upper and 'DELETE FROM' not in upper and 'ALTER TABLE' not in upper and 'CREATE TABLE' not in upper, 'settings.php contains no direct mutation SQL or DDL')

for path in [INDEX, STOCK, SETTINGS, ROOT/'.htaccess', ROOT/'public/.htaccess']:
    raw = path.read_bytes()
    check(not raw.startswith(b'\xef\xbb\xbf'), str(path.relative_to(ROOT)) + ' has no UTF-8 BOM')
    check(b'\r\n' not in raw, str(path.relative_to(ROOT)) + ' preserves LF line endings')
    check(all(not line.endswith((b' ', b'\t')) for line in raw.splitlines()), str(path.relative_to(ROOT)) + ' has no trailing whitespace')

passed = sum(checks)
failed = len(checks) - passed
print(f'RESULT: PASS {passed} / FAIL {failed}')
sys.exit(1 if failed else 0)
