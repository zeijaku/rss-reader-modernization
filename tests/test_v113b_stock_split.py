#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
INDEX = ROOT / 'public' / 'index.php'
STOCK = ROOT / 'public' / 'stock.php'
SETTINGS = ROOT / 'public' / 'settings.php'

index = INDEX.read_text(encoding='utf-8')
stock = STOCK.read_text(encoding='utf-8')
settings = SETTINGS.read_text(encoding='utf-8') if SETTINGS.is_file() else ''
api = (ROOT / 'public' / 'api_v1.php').read_text(encoding='utf-8')
js = (ROOT / 'public' / 'js' / 'dashboard.js').read_text(encoding='utf-8')

checks: list[bool] = []

def check(condition: bool, message: str) -> None:
    ok = bool(condition)
    checks.append(ok)
    print(('PASS' if ok else 'FAIL') + ': ' + message)

check(STOCK.is_file(), 'public/stock.php exists')
check("} elseif ($content_location === 'stock') {" not in index, 'Stock render branch is removed from index.php')
check('stockTaskTargetModal' not in index and 'stockTaskSingleTarget' not in index, 'Stock-only Task target DOM is removed from index.php')
check("$tabParam = 'stock';" in stock, 'stock.php fixes its page state to Stock')
check("} elseif ($content_location === 'stock') {" in stock, 'existing Stock render branch is retained in stock.php')
check('stockTaskTargetModal' in stock and 'stockTaskSingleTarget' in stock, 'Stock Task target DOM moved to stock.php')

# Old /?tab=stock compatibility is handled by index.php with validated allowlisted query values.
redirect_start = index.index("if ($tabParam === 'stock') {")
redirect_end = index.index("?>", redirect_start)
redirect = index[redirect_start:redirect_end]
for needle, label in [
    ("app_validate_text($_GET['q'] ?? '', 128, true)", 'q validation'),
    ("app_validate_enum($_GET['sort'] ?? 'newest', ['newest', 'oldest', 'title'])", 'sort allowlist'),
    ("app_validate_positive_int($_GET['page'] ?? '1')", 'page positive integer validation'),
    ("app_validate_positive_int($_GET['tag'] ?? null)", 'tag positive integer validation'),
]:
    check(needle in redirect, 'legacy redirect keeps ' + label)
check("$stockRedirectParams = [];" in redirect, 'legacy redirect starts from an empty allowlist')
check("$stockRedirectParams['q']" in redirect, 'legacy redirect can preserve validated q')
check("$stockRedirectParams['sort']" in redirect, 'legacy redirect can preserve validated non-default sort')
check("$stockRedirectParams['page']" in redirect, 'legacy redirect can preserve validated page')
check("$stockRedirectParams['tag']" in redirect, 'legacy redirect can preserve validated tag')
check("$stockRedirectParams['tab']" not in redirect, 'canonical redirect drops legacy tab parameter')
check("http_build_query($stockRedirectParams, '', '&', PHP_QUERY_RFC3986)" in redirect, 'legacy redirect uses RFC3986 query building')
check("$stockRedirectUrl = './stock'" in redirect, 'legacy redirect targets canonical /stock')
check("header('Location: ' . $stockRedirectUrl, true, 302);" in redirect, 'legacy route uses temporary 302 redirect')
check('exit;' in redirect, 'legacy redirect stops index rendering')

# New Stock entry point keeps the existing validation/read path and canonical URLs.
for needle, label in [
    ("app_validate_text($_GET['q'] ?? '', 128, true)", 'q validation'),
    ("app_validate_enum($_GET['sort'] ?? 'newest', ['newest', 'oldest', 'title'])", 'sort validation'),
    ("app_validate_positive_int($_GET['page'] ?? '1')", 'page validation'),
    ("app_validate_positive_int($_GET['tag'] ?? null)", 'tag validation'),
    ('dashboard_widget_task_targets($currentUserId)', 'Task target read'),
    ('stock_tag_list_user($currentUserId)', 'Tag list read'),
    ('count_stock($currentUserId, $stockSearchQuery, $stockTagFilter)', 'Stock count read'),
    ('search_stock($currentUserId, $stockSearchQuery, $stockSort, $stockPerPage, $stockOffset, $stockTagFilter)', 'Stock row read'),
    ('stock_tag_assigned_for_stocks($currentUserId, $stockIds)', 'assigned Tag read'),
    ('stock_tag_domain_tendencies($currentUserId, $result_stock)', 'domain tendency read'),
    ('stock_tag_cooccurrence_tendencies($currentUserId)', 'co-occurrence tendency read'),
]:
    check(needle in stock, 'stock.php keeps ' + label)
check("$params = [];" in stock and "return './stock'" in stock, 'pagination uses canonical /stock URL without tab')
check('action="./stock"' in stock, 'Stock GET filter submits to /stock')
check('name="tab" value="stock"' not in stock, 'Stock GET form no longer emits legacy tab')
check('href="./stock">クリア' in stock, 'Stock filter clear link is canonical')
check('href="./stock">検索条件を解除' in stock, 'filtered empty-state clear link is canonical')
check('href="./stock" class="text-muted drawer-item' in stock, 'Stock Drawer link is canonical')
check('href="./stock" class="text-muted drawer-item' in index, 'Dashboard Drawer points to canonical Stock page')

# Existing DOM / behavior contract intentionally remains present on Stock page.
for needle, label in [
    ('id="articleActionsMenu"', 'shared Article Actions'),
    ('article-action-stock-remove', 'Stock removal action'),
    ('class="stock-grid"', 'Stock grid'),
    ('data-stock-empty-redirect=', 'empty-page recovery hook'),
    ('stock-tag-manager-toggle', 'Tag manager'),
    ('stock-tag-editor-toggle', 'Tag editor'),
    ('stock-tag-attach', 'Tag attach control'),
    ('stock-tag-remove', 'Tag remove control'),
    ('stock-tag-add-form', 'Tag create form'),
    ('stock-tag-rename-form', 'Tag rename form'),
    ('stock-tag-delete', 'Tag delete control'),
    ('id="registerContent"', 'RSS add modal'),
    ('id="accountSettings"', 'Account Settings modal'),
    ('id="drawerMenu"', 'Drawer'),
]:
    check(needle in stock, 'Stock page preserves ' + label)

check('id="settingsForm"' in settings and 'id="tabsForm"' in settings and 'id="rssHighlightKeywordForm"' in settings, 'V1.13-C keeps moved Settings controls on dedicated Settings page')
check('href="./settings#display"' in stock and 'href="./settings#tabs"' in stock and 'href="./settings#highlight"' in stock, 'Stock Drawer keeps access to moved Settings controls')

check('meta name="csrf-token"' in stock, 'Stock page keeps CSRF meta for existing API JS')
check('method="post" action="./logout.php"' in stock and 'name="csrf_token"' in stock, 'Stock logout remains POST + CSRF')
check('app_validate_stock_url' in stock and 'rel="noopener noreferrer"' in stock, 'Stock output keeps URL validation and opener protection')
check("apiRequest('stock.delete'" in js, 'Stock removal still uses central API client')
check("apiRequest('stock.tag." in js, 'Stock Tag mutations still use central API client')
check('app_csrf_is_valid' in api and 'api_dispatch' in api, 'central API retains CSRF-before-dispatch boundary')

# V1.13-B preserved the shared Asset set at that checkpoint. Later frontend modernization
# may replace legacy dependencies, so assert the current shared runtime contract instead.
for asset in [
    'css/dashboard.css', 'css/utility-widgets.css', 'css/mini-game.css', 'css/clock-timer.css',
    'js/jquery-3.7.1.min.js', 'js/bootstrap.bundle-5.3.8.min.js',
    'js/mini-game.js', 'js/lights-out.js', 'js/clock-timer.js',
    'js/dashboard.js', 'js/utility-widgets.js', 'js/calendar.js',
]:
    check(asset in stock, 'Stock page intentionally retains asset: ' + asset)

# Encoding / formatting safety.
for path in [INDEX, STOCK]:
    raw = path.read_bytes()
    check(not raw.startswith(b'\xef\xbb\xbf'), path.name + ' has no UTF-8 BOM')
    check(b'\r\n' not in raw, path.name + ' preserves LF line endings')
    check(all(not line.endswith((b' ', b'\t')) for line in raw.splitlines()), path.name + ' has no trailing whitespace')

# B adds no schema or page-specific write path.
check(not any('v1_13' in p.name.lower() for p in (ROOT / 'database').rglob('*.sql')), 'V1.13-B adds no DB migration')
check('CREATE TABLE' not in stock.upper() and 'ALTER TABLE' not in stock.upper(), 'stock.php contains no schema DDL')
check('INSERT INTO' not in stock.upper() and 'DELETE FROM' not in stock.upper(), 'stock.php contains no direct mutation SQL')

passed = sum(checks)
failed = len(checks) - passed
print(f'RESULT: PASS {passed} / FAIL {failed}')
sys.exit(1 if failed else 0)
